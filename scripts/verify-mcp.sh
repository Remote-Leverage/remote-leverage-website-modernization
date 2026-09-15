#!/usr/bin/env bash
#
# Verify that an environment's MCP endpoint is reachable and authenticating.
#
# Exists because every failure mode in this stack looks the same from a Claude
# session — the tool list is empty and nothing says why. Each step below fails
# with a distinct message instead, so a bad CloudFront policy, a missing
# capability and a wrong password stop looking alike.
#
# Usage:
#   scripts/verify-mcp.sh https://staging.remoteleverage.com ai-content-agent 'xxxx xxxx xxxx'
#
set -uo pipefail

SITE="${1:-}"
USERNAME="${2:-}"
APP_PASSWORD="${3:-}"

if [[ -z "$SITE" || -z "$USERNAME" || -z "$APP_PASSWORD" ]]; then
    echo "usage: $0 <site-url> <username> <application-password>" >&2
    exit 64
fi

SITE="${SITE%/}"
ENDPOINT="$SITE/wp-json/mcp/mcp-adapter-default-server"
AUTH="$USERNAME:$APP_PASSWORD"

fail() { echo "FAIL  $*" >&2; exit 1; }
pass() { echo "ok    $*"; }

# 1. The REST API itself. Separates "the site is down" from anything MCP.
code=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/wp-json/")
[[ "$code" == "200" ]] || fail "REST API returned $code (expected 200). The site itself is not answering."
pass "REST API reachable"

# 2. Initialize. This is the only MCP call that needs no session, so reaching
#    it proves routing works and isolates the session-header question below.
headers=$(mktemp); body=$(mktemp)
trap 'rm -f "$headers" "$body"' EXIT

curl -s -D "$headers" -o "$body" -u "$AUTH" -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify-mcp","version":"1"}}}' \
    "$ENDPOINT" >/dev/null

if grep -q 'rest_not_logged_in\|rest_forbidden' "$body"; then
    fail "authentication rejected.
      CloudFront is almost certainly not forwarding the Authorization header to
      the origin, which is the documented cause. Confirm with:
        curl -s -o /dev/null -w '%{http_code}' -u '$USERNAME:...' '$SITE/wp-json/wp/v2/users/me'
      A 200 there but a failure here means the header reaches the origin and the
      problem is elsewhere; a 401 in both means CloudFront stripped it."
fi

grep -q '"result"' "$body" || fail "initialize did not return a result: $(head -c 300 "$body")"
pass "authenticated as $USERNAME"

SID=$(tr -d '\r' < "$headers" | awk -F': ' 'tolower($1)=="mcp-session-id"{print $2}')
[[ -n "$SID" ]] || fail "server returned no Mcp-Session-Id header on initialize."
pass "session established"

# 3. Any call after initialize. This is the step CloudFront breaks second: the
#    session id travels as a custom header, which a distribution that forwards
#    only Authorization will still drop.
tools=$(curl -s -u "$AUTH" -H 'Content-Type: application/json' -H "Mcp-Session-Id: $SID" \
    -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' "$ENDPOINT")

if grep -q 'Missing Mcp-Session-Id' <<<"$tools"; then
    fail "the Mcp-Session-Id header did not reach the origin.
      Authorization is getting through but this one is not, so the origin request
      policy is forwarding some headers and not all. Use the managed AllViewer
      policy on the /wp-json/* behavior."
fi

grep -q '"tools"' <<<"$tools" || fail "tools/list failed: $(head -c 300 <<<"$tools")"
pass "tools/list works (MCP transport is fully functional)"

# 4. The abilities themselves. Transport can be perfect while the agent user
#    lacks edit_pages, which presents to a Claude session as an empty toolbox.
result=$(curl -s -u "$AUTH" -H 'Content-Type: application/json' -H "Mcp-Session-Id: $SID" \
    -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"mcp-adapter-execute-ability","arguments":{"ability_name":"app/list-pages","parameters":{"limit":1}}}}' \
    "$ENDPOINT")

if grep -q 'capability is required\|forbidden' <<<"$result"; then
    fail "transport works, but $USERNAME lacks the capability to list pages.
      Run on that environment:  wp acorn rl:ai:agent --status"
fi

grep -q '"success":true' <<<"$result" || fail "app/list-pages failed: $(head -c 300 <<<"$result")"
pass "app/list-pages executed"

echo
echo "MCP is working on $SITE."
