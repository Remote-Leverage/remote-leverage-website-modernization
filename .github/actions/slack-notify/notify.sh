#!/usr/bin/env bash
set -euo pipefail

# Resolves a Slack mention for the GitHub author and writes a chat.postMessage payload.
# Outputs: skip, mode, payload (path)

github_user="${GITHUB_USER:-}"
author_email="${AUTHOR_EMAIL:-}"
author_name="${AUTHOR_NAME:-}"
status="${NOTIFY_STATUS:-failure}"
title="${NOTIFY_TITLE:-Pipeline}"
details="${NOTIFY_DETAILS:-}"
subject="${NOTIFY_SUBJECT:-}"
run_url="${RUN_URL:-}"
pr_url="${PR_URL:-}"
branch="${BRANCH:-}"
repo="${GITHUB_REPOSITORY:-}"
map_json="${SLACK_GITHUB_USER_MAP:-}"
map_file="${GITHUB_WORKSPACE}/.github/slack-user-map.json"
token="${SLACK_BOT_TOKEN:-}"
channel="${SLACK_CHANNEL_ID:-}"
webhook="${SLACK_WEBHOOK_URL:-}"
payload_path="${RUNNER_TEMP}/slack-payload.json"

if [[ -z "$token" && -z "$webhook" ]]; then
  echo "Slack is not configured (set SLACK_BOT_TOKEN + SLACK_CHANNEL_ID, or SLACK_WEBHOOK_URL). Skipping."
  echo "skip=true" >> "$GITHUB_OUTPUT"
  exit 0
fi

if [[ -n "$token" && -z "$channel" ]]; then
  if [[ -z "$webhook" ]]; then
    echo "SLACK_CHANNEL_ID is required with SLACK_BOT_TOKEN. Skipping."
    echo "skip=true" >> "$GITHUB_OUTPUT"
    exit 0
  fi
  token=""
fi

login_lc="$(printf '%s' "$github_user" | tr '[:upper:]' '[:lower:]')"
slack_id=""

lookup_map() {
  local json="$1"
  [[ -z "$json" || "$json" == "{}" ]] && return 0
  jq -r --arg u "$login_lc" '
    to_entries
    | map(select((.key | ascii_downcase) == $u and (.value | type == "string") and (.value | length) > 0))
    | first
    | .value // empty
  ' <<<"$json"
}

if [[ -f "$map_file" ]]; then
  slack_id="$(lookup_map "$(cat "$map_file")")"
fi

if [[ -z "$slack_id" && -n "$map_json" ]]; then
  slack_id="$(lookup_map "$map_json")"
fi

if [[ -z "$slack_id" && -n "$token" && -n "$author_email" ]]; then
  resp="$(curl -sS -G \
    --data-urlencode "email=${author_email}" \
    -H "Authorization: Bearer ${token}" \
    https://slack.com/api/users.lookupByEmail || true)"
  if jq -e '.ok == true' >/dev/null 2>&1 <<<"$resp"; then
    slack_id="$(jq -r '.user.id' <<<"$resp")"
  else
    echo "Slack email lookup did not resolve ${github_user}."
  fi
fi

if [[ -n "$slack_id" ]]; then
  mention="<@${slack_id}>"
  echo "Resolved Slack mention for ${github_user}."
else
  mention="@${github_user}"
  echo "No Slack user id for ${github_user}; posting a non-pinging @handle. Add them to .github/slack-user-map.json."
fi

subject_one="$(printf '%s' "$subject" | tr '\n' ' ' | cut -c1-120)"
if [[ "$status" == "success" ]]; then
  emoji=":white_check_mark:"
  headline="${title} succeeded"
else
  emoji=":x:"
  headline="${title} failed"
fi

author_line="${mention}"
if [[ -n "$author_name" && "$author_name" != "$github_user" ]]; then
  author_line="${mention} (${author_name})"
fi

body="*${emoji} ${headline}*
*Author:* ${author_line}
*Repo:* <https://github.com/${repo}|${repo}>
*Branch:* \`${branch}\`"
if [[ -n "$subject_one" ]]; then
  body="${body}
*Subject:* ${subject_one}"
fi
if [[ -n "$details" ]]; then
  body="${body}
*Jobs:* ${details}"
fi
body="${body}
<${run_url}|View workflow run>"
if [[ -n "$pr_url" ]]; then
  body="${body}
<${pr_url}|Open pull request>"
fi

fallback="${headline} for ${repo} (${github_user}) — ${run_url}"

if [[ -n "$token" ]]; then
  jq -n \
    --arg channel "$channel" \
    --arg text "$fallback" \
    --arg body "$body" \
    '{
      channel: $channel,
      text: $text,
      unfurl_links: false,
      unfurl_media: false,
      link_names: true,
      blocks: [
        {type: "section", text: {type: "mrkdwn", text: $body}}
      ]
    }' > "$payload_path"
  echo "mode=api" >> "$GITHUB_OUTPUT"
else
  jq -n \
    --arg text "$fallback" \
    --arg body "$body" \
    '{
      text: $text,
      unfurl_links: false,
      blocks: [
        {type: "section", text: {type: "mrkdwn", text: $body}}
      ]
    }' > "$payload_path"
  echo "mode=webhook" >> "$GITHUB_OUTPUT"
fi

echo "skip=false" >> "$GITHUB_OUTPUT"
echo "payload=${payload_path}" >> "$GITHUB_OUTPUT"
