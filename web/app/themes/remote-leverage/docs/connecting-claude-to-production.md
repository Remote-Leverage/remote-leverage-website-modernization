# Connecting Claude to production

How the marketing team edits live landing pages, reads enquiry data and uploads media by asking
Claude, rather than by opening WordPress.

This is the **production** counterpart to
[claude-desktop-for-editors.md](claude-desktop-for-editors.md), which covers staging and is the
gentler document to hand a colleague. The difference is not cosmetic: on staging the worst outcome
is a lost afternoon, and on production every change is live the moment it is made. Read
[What production changes about all of this](#what-production-changes-about-all-of-this) before
granting anyone access.

Two audiences, split deliberately:

- [**Part 1 — operator setup**](#part-1--operator-setup) is done once, by any WordPress
  administrator. It no longer needs AWS or a shell.
- [**Part 2 — connecting a client**](#part-2--connecting-a-client) is what each person on the
  marketing team does, and is the part worth copying into a message.

---

## What the team gets

Ten abilities, all reachable from an ordinary chat. Nobody needs to name them — Claude picks.

| Group | Abilities | What it means in practice |
| :--- | :--- | :--- |
| Read | `app/list-pages`, `app/describe-page`, `app/list-patterns` | "Show me the Athena comparison page and list its sections." |
| Write | `app/clone-page`, `app/update-page-sections`, `app/create-landing-page`, `app/update-landing-page-content` | "Change the hero headline on the Belay page." |
| Media | `app/upload-media` | "Here's the new hero image — put it on the page." |
| Enquiries | `app/lead-stats`, `app/query-leads` | "How many enquiries came from `/hire-va/` last month?" |

`app/upload-media` is the newest and closes a gap worth understanding: every image on a page is
addressed by **attachment ID**, so before it existed a session could rearrange art already in the
library but could never introduce new art. That step had to happen by hand in wp-admin — exactly
the handoff this is meant to remove. It takes either a `source_url` or an inline base64 file
(capped at 8MB; larger files belong in wp-admin) and returns the attachment ID that
`update-page-sections` then writes into an `image_id` field.

---

## Part 1 — operator setup

### Step 0: what is already true on production

Verified against `remoteleverage.com` on 2026-09-17, so these are **not** steps — they are
the reasons the rest of this is short:

| Prerequisite | State |
| :--- | :--- |
| `WP_MCP_AUTOLOAD` defined, adapter booted | ✅ REST index registers the `mcp` and `wp-abilities/v1` namespaces |
| Application Passwords available (the WordFence trap, [known-issues.md](known-issues.md) item 22) | ✅ `/wp-json/` reports an `application-passwords` block |
| `Authorization` reaching the origin through CloudFront | ✅ the MCP endpoint answers `401`, not a connection error |
| `ai-content-agent` exists | ✅ `rl:deploy` runs `ContentAgentProvisioner::ensure()` on every container start |

The one prerequisite that **cannot** be checked without a credential is whether `Mcp-Session-Id`
survives CloudFront. If it does not, `rl-mcp-session-bridge.php` covers it
([known-issues.md](known-issues.md) item 20) — but the distribution should still be fixed and the
bridge deleted. `scripts/verify-mcp.sh` in step 3 is what tells you which.

### Step 1: capabilities — nothing to do

All four capability flags **default to `true`** as of 2026-09-18, so a deployed environment comes
up able to publish, edit live pages, read enquiry data and upload media without any secret being
set. This changed deliberately: they were previously per-environment secrets, and a forgotten
secret produced an agent that could only draft, with nothing anywhere reporting why.

Confirm what actually landed under **Settings → AI Access**, or from a shell:

```bash
wp acorn rl:ai:agent --status
```

To **close** one, set its variable to `false` as a `production` environment secret and deploy:

```
AI_AGENT_CAN_PUBLISH=false          # drafts only; a human publishes
AI_AGENT_CAN_EDIT_PUBLISHED=false   # cannot touch a live page
AI_AGENT_CAN_READ_LEADS=false       # no enquiry data at all
AI_AGENT_CAN_UPLOAD_MEDIA=false     # no uploads
```

`ensure()` reconciles in both directions on every container start, so `false` genuinely revokes
rather than leaving an earlier grant in place. That applies to `upload_files` too, even though the
`editor` role grants it — the denial is written explicitly and beats the role.

### Step 2: issue a credential

**Settings → AI Access** in wp-admin. Requires `manage_options`; no shell, no AWS.

Name it after the person who will hold it — `claude-jane`. The screen refuses the name
`mcp-client`, which is reserved for the shared credential that `--rotate` sweeps as a set; a
colleague given one of those loses access the next time anybody rotates, with no notice and no
obvious cause. Named credentials are individually revocable, which is the whole point: somebody
leaves, you revoke one row, and nobody else reconnects.

The screen shows, exactly once:

- the username and application password,
- the full `Authorization: Basic …` header value for a claude.ai custom connector,
- a ready-to-paste Claude Desktop config block.

It is hashed on save and cannot be shown again. If it is lost, revoke it and issue another.

The CLI equivalents still exist for anyone with shell access:

```bash
wp acorn rl:ai:agent --rotate   # the SHARED credential, revokes previous ones
wp acorn rl:ai:agent --status
```

### Step 3: verify the transport before handing anything out

```bash
scripts/verify-mcp.sh https://remoteleverage.com ai-content-agent 'xxxx xxxx xxxx xxxx xxxx xxxx'
```

Every failure in this stack looks identical from a chat window — the tool list is simply empty,
with no error. This script is the thing that tells a stripped header apart from a missing
capability apart from a wrong password, and it names each one. Run it after any CloudFront change.

---

## Part 2 — connecting a client

Three routes. **Option A is the one to try first** if your organisation has it, because it is the
only one where nobody installs anything.

The endpoint is the same in all three:

```
https://remoteleverage.com/wp-json/mcp/mcp-adapter-default-server
```

The path is **not** optional. The proxy falls back to a deprecated endpoint if given a bare domain.

### Option A — claude.ai custom connector

Works across claude.ai, Claude Desktop and mobile, on Free through Enterprise. An **organisation
owner** adds it under Organization settings → Connectors; members then connect it from Customize →
Connectors.

1. **Add custom connector**, URL as above.
2. Choose **No sign-in**. This is counter-intuitive and load-bearing: OAuth owns the
   `Authorization` header and Anthropic's docs state it "cannot be configured as a request header
   on an OAuth connection", so selecting OAuth makes step 3 impossible.
3. Under **Request headers**, add `Authorization` with the value `Basic <base64>`, where
   `<base64>` encodes `ai-content-agent:<application password>`. Claude sends the value exactly as
   typed and adds no prefix, so the word `Basic` and its trailing space must be there.

```bash
printf '%s' 'ai-content-agent:xxxx xxxx xxxx xxxx xxxx xxxx' | base64
```

Spaces in the password are safe either way — WordPress strips every non-alphanumeric character
from a submitted application password before checking it (`wp_authenticate_application_password`),
so the spaced and unspaced forms are equivalent.

**Two caveats.** Request-header authentication is **beta and gated per organisation** — if there
is no *Request headers* section in the dialog, your org does not have it and you want Option B.
And auth settings **cannot be edited after the connector is added**: changing the credential means
removing and re-adding it, after which every member must reconnect.

> This corrects earlier guidance in [ai-mcp-and-sync.md](ai-mcp-and-sync.md), which recorded that
> a custom connector was not an option because HTTP Basic was an open bug. That bug is fixed and
> Basic is now documented as supported. The beta gate is what remains.

### Option B — Claude Desktop with the local proxy

The route that always works, at the cost of each person installing Node. This is what
[claude-desktop-for-editors.md](claude-desktop-for-editors.md) walks through step by step for
staging; only the URL and the credential change.

**Settings → Developer → Edit Config**, then:

```json
{
  "mcpServers": {
    "remote-leverage-production": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://remoteleverage.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "ai-content-agent",
        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

Quit Claude Desktop completely and reopen — not just the window.

No `OAUTH_ENABLED` is needed: the proxy reads it as `process.env.OAUTH_ENABLED === 'true'`, so it
is **off unless explicitly enabled**, and its auth precedence is JWT → OAuth → Basic. With only
`WP_API_USERNAME` and `WP_API_PASSWORD` set, Basic is what it uses.

Note that `claude_desktop_config.json` accepts **STDIO servers only**. Putting the MCP URL straight
into that file does not work; a remote server goes through the Connectors UI (Option C).

### Option C — Claude Desktop Connectors UI

Same mechanism and the same beta gate as Option A, added per user rather than per organisation.
Useful when one person needs access and the org-wide connector is not wanted.

### For engineers: this repository's `.mcp.json`

Already wired for Claude Code, and it interpolates from the environment rather than storing
anything, so the file stays safe in git:

```bash
export PRODUCTION_MCP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
```

These are `ai-content-agent` passwords. They are a **different credential set** from the
`*_SYNC_*` variables, which belong to `sync-service`. Do not cross them — the entire point of two
users is that the content agent cannot run a sync and the sync user cannot edit a page.

---

## What production changes about all of this

### Edits are live immediately

There is no review step. `update-page-sections` on a published page changes what customers see as
soon as it returns. If that is not wanted, leave `AI_AGENT_CAN_EDIT_PUBLISHED` unset: the agent can
still compose a complete campaign page as a draft for a human to publish, which is the safer
posture and costs only the publish click.

### The pattern-detachment warning stops being a footnote

Most pages are a single `wp:pattern` reference to a `patterns/<slug>.php` file in git.
`update-page-sections` has to resolve that reference before it can apply an override — a reference
carries no content to attach one to — and writes the expanded result back.

**The first edit therefore converts a git-backed page into database-resident markup**, which a
database refresh will overwrite. The ability detects this and returns `detached_from_patterns`
plus a `warning` naming the `patterns/<slug>.php` file the change must be ported into.

On staging this costs an afternoon. On production it means a live page has silently stopped
matching the code that is supposed to define it, and the next refresh reverts marketing's work
with no warning. **Treat a forwarded warning as a real task, not a notification.**

### Read `skipped`, every time

`clone-page` and `update-page-sections` both return `applied` and `skipped`. An override naming a
section or field that does not exist is reported in `skipped` rather than applied — ACF resolves a
value through its companion `_field` key, so inventing one would write markup that looks correctly
wired and renders nothing. A skipped item means **the page did not change in the way it was
asked to**, on the live site.

### `query-leads` returns real customer PII

Names, emails and phone numbers. It shares a single flag with `lead-stats`, so there is no way to
grant counts without also granting contact details — worth knowing when deciding who gets a
credential. Most "how is this page performing" questions are answerable from `lead-stats` alone,
and the ability's own description tells Claude to prefer it.

### Uploads have no undo

`upload-media` writes to the shared uploads volume on EFS. Nothing here deletes a file, so a
mistaken upload stays until somebody removes it in wp-admin. This is why the capability is off by
default rather than inherited from the `editor` role.

---

## When the tool list is empty

Always the same symptom, four common causes, in the order worth checking:

1. **Capabilities never reached the container.** `wp acorn rl:ai:agent --status`. If the flags read
   false, the *Sync app secrets* workflow has not run for `production` since they were added.
2. **The credential is wrong or was swept.** Someone ran `wp acorn rl:ai:agent --rotate` and
   revoked a password named `mcp-client` that had been handed out.
3. **Application Passwords got disabled site-wide.** WordFence does this by default and it looks
   like a bad password, because core returns `rest_not_logged_in` for missing *and* wrong
   credentials alike:
   ```bash
   curl -s https://remoteleverage.com/wp-json/ | jq .authentication
   # {"application-passwords": {...}}  -> available
   # {}                                -> disabled; nothing can authenticate
   ```
4. **The session header is being stripped.** `scripts/verify-mcp.sh` fails at the session step by
   name. See [known-issues.md](known-issues.md) item 20.

## See also

- [ai-mcp-and-sync.md](ai-mcp-and-sync.md) — the abilities themselves, the two-user model, and the
  local↔remote sync commands
- [claude-desktop-for-editors.md](claude-desktop-for-editors.md) — the staging guide written for a
  non-technical colleague
- [configuration.md](configuration.md) — every environment variable this project reads
