# AI MCP abilities & local↔remote sync — setup

This covers one-time setup for the two features added on top of `roots/acorn-ai`
and the `WordPress/mcp-adapter` plugin:

1. **MCP-callable landing-page abilities** (`app/Ai/Abilities/*`) — read, clone and
   edit the pages that already exist, and compose new ones from already-registered
   `remote-leverage/*` block patterns. See
   [docs/page-migration-and-design-system-workflow.md](page-migration-and-design-system-workflow.md)
   for the underlying pattern/block conventions these abilities assemble.
2. **Local↔remote sync abilities** (`app/Domains/Sync/Abilities/*`) — push/pull the
   whitelisted `wp_options` rows in `config/rl-sync.php` and individual landing
   pages between environments, via `wp rl:sync:settings` / `wp rl:sync:page`.

Both are built on the WordPress Abilities API (core 6.9+, confirmed on this
project's WP 7.1). Every ability is permission-gated by its own `permission()`
method — the sections below explain which WordPress user/credential should be
used for each one, and why they're kept separate.

## Two dedicated WordPress users per environment (staging, production)

Do not reuse an existing admin account's credentials for either of these — each
is scoped to the minimum it needs, so a leaked/misused Application Password has
a bounded blast radius.

### 1. `ai-content-agent` — for MCP (landing-page abilities)

- Role: `editor`. What that role grants is the whole permission story, and each
  capability is a separate lever worth setting deliberately:

  | Capability | Without it |
  | --- | --- |
  | `edit_pages` | No landing-page ability works at all. |
  | `publish_pages` | `clone-page` can only ever leave a draft for a human to publish. |
  | `edit_published_pages` | `update-page-sections` refuses to touch a live page. |

  On production, consider an agent with `edit_pages` but **not** `publish_pages`
  or `edit_published_pages`: it can draft a whole campaign page for review while
  being unable to change anything already live.

- **You do not create this user by hand.** `rl:deploy` calls
  `ContentAgentProvisioner::ensure()` on every container start, so the user
  exists and its capabilities match `config/ai-wordpress.php` on each release.
  The same reconciliation is available directly:
  ```
  wp acorn rl:ai:agent            # create / align capabilities (idempotent)
  wp acorn rl:ai:agent --status   # show what it can currently do
  wp acorn rl:ai:agent --rotate   # issue an application password (printed once)
  wp acorn rl:ai:agent --revoke   # drop its passwords and managed capabilities
  ```
  Deploy reconciles but **never mints a credential** — deploy output goes to
  CloudWatch, and a password printed there is a password leaked. `--rotate` is
  the only thing that issues one, and the plaintext is shown once.

  Capabilities are driven by environment variables, all defaulting to **off**,
  so an environment that sets nothing gets an agent that can only draft:

  | Variable | Grants |
  | --- | --- |
  | `AI_AGENT_CAN_PUBLISH` | `publish_pages` — `clone-page` may publish directly |
  | `AI_AGENT_CAN_EDIT_PUBLISHED` | `edit_published_pages` — may change live pages |
  | `AI_AGENT_CAN_READ_LEADS` | `rl_read_business_data` — may read enquiry data |
  | `AI_AGENT_PROVISION_ON_DEPLOY` | set `false` to skip reconciliation entirely |

  Because reconciliation runs every deploy, these tighten as well as widen:
  removing `AI_AGENT_CAN_PUBLISH` from the task definition actually revokes
  `publish_pages` on the next release rather than leaving a grant made by hand
  months earlier.
- This is the identity an MCP client (Claude Code / Claude Desktop) authenticates
  as for every `meta.mcp.public` ability.
- Reading enquiry data is **not** part of this role. To let this identity call
  `app/lead-stats` and `app/query-leads`, grant the capability explicitly:
  ```
  AI_AGENT_CAN_READ_LEADS=true   # preferred: survives the next deploy
  wp acorn rl:ai:grant-insights ai-content-agent          # one-off, any user
  wp acorn rl:ai:grant-insights ai-content-agent --revoke
  ```
  Prefer the environment variable for the agent user itself. A grant made only
  with `rl:ai:grant-insights` is reverted the next time `rl:deploy` reconciles
  against config.
  `query-leads` returns real customer contact details, so grant it only where
  someone actually needs it — most "how is this page performing" questions are
  answerable from `lead-stats`, which returns counts only.

### 2. `sync-service` — for `wp rl:sync:*`

- Role: doesn't matter on its own — what matters is the custom
  `rl_manage_ai_sync` capability (`App\Domains\Sync\SyncCapability`), which is
  what actually gates the four sync abilities. Give it the lowest built-in role
  available (e.g. `subscriber`) and grant just this one capability:
  ```
  wp user create sync-service sync-service@remoteleverage.com --role=subscriber
  wp acorn rl:sync:grant sync-service
  wp user application-password create sync-service "local-sync-script"
  ```
- Run `wp acorn rl:sync:grant <user_login>` once per environment (idempotent —
  safe to re-run).
- This identity never has `edit_pages`/`publish_pages` and is never used by an
  MCP client — it's only called by the local `wp rl:sync:*` commands below.

## Environment variables (local `.env` / `env` file only)

Add per remote environment (see `config/rl-sync.php`):

```
STAGING_SYNC_URL=https://staging.remoteleverage.com
STAGING_SYNC_USER=sync-service
STAGING_SYNC_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx

PRODUCTION_SYNC_URL=https://remoteleverage.com
PRODUCTION_SYNC_USER=sync-service
PRODUCTION_SYNC_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
```

These are a separate, opposite-direction credential set from
`scripts/seed-staging-secrets.sh` (which pushes local `.env` values *up* into
AWS Secrets Manager for the container's own runtime env) — don't add these
`*_SYNC_*` keys to that script; they're read locally by the sync commands to
call *out* to a remote environment's REST API, not baked into any container.

## Using the sync commands (run locally)

```
# Push local settings (Calendly tokens, webhook URLs, etc.) to staging
wp acorn rl:sync:settings --push --target=staging

# Pull staging's current settings down to local
wp acorn rl:sync:settings --pull --target=staging

# Push a local landing page (by local post ID) to staging
wp acorn rl:sync:page 123 --push --target=staging

# Pull a page down from staging (by its post ID *on staging*) into local
wp acorn rl:sync:page 0 --pull --remote-post-id=456 --target=staging
```

Swap `--target=staging` for `--target=production` once `PRODUCTION_SYNC_*` is set.
Both commands print which keys/pages actually changed — they never silently
overwrite without saying so.

## The landing-page abilities

Nine abilities carry `meta.mcp.public`, so they are the ones an MCP client sees.
They fall into three groups.

**Read — always start here.**

| Ability | What it is for |
| --- | --- |
| `app/list-pages` | Resolve a page a human named ("the Athena comparison") into a post ID. |
| `app/describe-page` | Break a page into named sections and their editable field values. |
| `app/list-patterns` | List the `remote-leverage/*` patterns a page can be composed from. |

**Write.**

| Ability | What it is for |
| --- | --- |
| `app/clone-page` | Copy an existing page and replace the copy in named sections. |
| `app/update-page-sections` | Edit named sections of a page in place, leaving the rest untouched. |
| `app/create-landing-page` | Compose a new page from whole patterns, verbatim. |
| `app/update-landing-page-content` | Replace a page's whole content from a list of patterns. |

**Enquiry data** — gated on `rl_read_business_data`, not `edit_pages`:

| Ability | What it is for |
| --- | --- |
| `app/lead-stats` | Counts grouped by landing URL, UTM, source or status. No contact details. |
| `app/query-leads` | Individual lead rows **including name, email and phone**. |

### The workflow these are built for

> "Make a Belay comparison page like the Athena one, with this copy."

```
app/list-pages     search: "Athena"        -> post_id 382
app/describe-page  post_id: 382            -> 23 sections, each with typed fields
app/clone-page     source_post_id: 382, title/slug, overrides[]
```

`clone-page` and `update-page-sections` both return `applied` and `skipped`.
**Always read `skipped`.** An override naming a section or field that does not
exist is reported there rather than applied, because ACF resolves a value
through its companion `_field` key — inventing one would write markup that
looks wired up and renders nothing.

### How sections are addressed

`describe-page` returns the address to use; nothing has to be derived.

- Sections declared in a pattern have a stable name: `hero`, `comparing-costs`.
- Everything else is positional: `#0`, `#2.1`. These move if the page is
  restructured, so re-run `describe-page` rather than reusing an old address.

Field types tell copy apart from structure: `text` is safe to rewrite,
`image_id` is an attachment ID, and `repeater_count` is the number of rows in a
repeater and should generally be left alone.

### What cloning does to the pattern convention

CLAUDE.md says page content lives in `patterns/*.php` in git, and that expanded
block markup does not belong in `post_content`. A cloned page is a deliberate
exception, and the trade is worth being explicit about:

- Of the site's pages, 12 are a single `wp:pattern` reference. `describe-page`
  and `clone-page` resolve that reference before doing anything, which is the
  only reason a 70-byte page is editable at all.
- The clone cannot stay a bare reference — the new copy has to live somewhere —
  so it is written as expanded markup in the database.
- **A cloned page is therefore not pattern-backed.** It is not in git, and a
  database refresh from another environment will take it with them.

That is the price of letting marketing ship a page without a deploy, and it is
fine for a campaign page with a short life. When a cloned page earns a permanent
place, promote it back into git: export it with `wp acorn rl:sync:page <id>
--push` or rebuild it as a `patterns/<slug>.php` file and point the page at it.

## Connecting an MCP client to the landing-page abilities

The `WordPress/mcp-adapter` plugin's default server exposes any ability with
`meta.mcp.public => true` — the nine listed above — discoverable and executable
via `mcp-adapter/discover-abilities` and `mcp-adapter/execute-ability` on that
server. Adding an ability to that set is a one-line `meta()` change; everything
else in `config/ai-wordpress.php` stays REST-only and invisible to MCP.

For a **remote** environment (staging/production), the repository root ships an
`.mcp.json` so nobody has to hand-write client config. It interpolates
credentials from the environment rather than storing them, so the file itself is
safe in git — each person exports their own:

```
export STAGING_MCP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
export PRODUCTION_MCP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
```

These are the `ai-content-agent` Application Passwords, and they are a different
credential set from the `*_SYNC_*` variables above, which belong to
`sync-service`. Do not cross them: the whole point of two users is that the
content agent cannot run a sync and the sync user cannot edit a page.

### Prerequisites on the remote environment

Three things have to be true before any of this works, and all three fail
silently in ways that look like an ability problem rather than a transport one.
Run `scripts/verify-mcp.sh <site> <user> <app-password>` to tell them apart —
each step reports a distinct cause.

1. **`WP_MCP_AUTOLOAD` must be defined** (`config/application.php`). The plugin
   is installed from VCS and relocated by composer/installers, so it has no
   nested `vendor/` and its own autoloader bails before the plugin ever boots.
   Symptom: an admin notice about a missing Composer autoloader, and no MCP
   server at all. Check with `wp mcp-adapter list`.
2. **Nginx must forward the `Authorization` header** (`docker/nginx.conf`).
   Stock `fastcgi_params` drops it, so PHP never sees the Basic credentials and
   every request answers `rest_not_logged_in`.
3. **CloudFront must forward both `Authorization` and `Mcp-Session-Id`.**

### The CloudFront requirement

This one is worth stating precisely, because MCP needs **two** headers and a
distribution that forwards only the obvious one still fails:

| Header | Used for | Symptom when stripped |
| --- | --- | --- |
| `Authorization` | Application Password auth | `rest_not_logged_in` on every call |
| `Mcp-Session-Id` | Transport session, issued by `initialize` | `initialize` succeeds, everything after it fails with `Missing Mcp-Session-Id header` |

`Mcp-Session-Id` is read straight off the request
(`HttpRequestContext::__construct`) with no query-string or body fallback, and
the adapter has no stateless mode — the only session filters it exposes are
limits and timeouts.

This was previously read as meaning MCP cannot be tunnelled past a distribution
that strips custom headers. It does not: the adapter reads the header off the
`WP_REST_Request`, and `rest_pre_dispatch` hands WordPress that same object after
authentication has resolved and before the route callback runs.
`web/app/mu-plugins/rl-mcp-session-bridge.php` uses that window to restore the
header from the authenticated user's own live session
(`SessionManager::get_all_user_sessions()`), so calls after `initialize` resolve
normally. It never mints a session — only restores one the client already
established — and it goes inert as soon as a real header arrives. See
[known-issues.md](known-issues.md) item 20 for the cost: clients sharing a
WordPress user share a transport session.

**The bridge is a stopgap, not the fix.** Configure the distribution as below and
delete it. The `_rl_sync_auth` body bridge in
`web/app/mu-plugins/rl-sync-body-auth.php` is a separate thing again: it solves
credentials, not the session header, and it only matches the
`/wp-json/wp-abilities/` path.

### Measured against staging, 2026-09-15

`Authorization` is fixed; `Mcp-Session-Id` is not. Probed directly against
`staging.remoteleverage.com`:

| Step | Result |
| :--- | :--- |
| `initialize` **with** credentials | **HTTP 200**, session id `…` returned in the `Mcp-Session-Id` response header |
| `initialize` **without** credentials (control) | HTTP 401 `rest_forbidden` |
| `tools/list` echoing that session id back | **HTTP 400 — `Invalid Request: Missing Mcp-Session-Id header`** |

That is exactly the failure this section predicted: `initialize` succeeds because
it needs no session, and every call after it fails because the header the server
just issued does not survive the round trip. The 401 control proves the
`Authorization` half now reaches the origin, so **only the custom header is still
being stripped.**

`docker/nginx.conf` is not the cause — it touches only `HTTP_AUTHORIZATION` and
`HTTP_X_LIVEWIRE`, and does nothing per-path, so it cannot explain a difference
between `/wp-json/wp/v2/*` and `/wp-json/mcp/*`.

**Consequence: MCP against staging is still unusable past `initialize`**, which is
what an empty tool list in a Claude session looks like from the outside. Run
`scripts/verify-mcp.sh` after any distribution change — it fails at the session
step with a named error rather than an empty list.

What to configure: a cache behavior for `/wp-json/*` with

- **Origin request policy:** `Managed-AllViewer` — forwards all viewer headers,
  which covers both of the above and the `X-Livewire` header that
  `docker/nginx.conf` currently reconstructs by hand.
- **Cache policy:** `Managed-CachingDisabled`.

Disabling the cache on that behavior is not optional. Forwarding
`Authorization` while still caching lets one user's authenticated response be
served to another — a far worse bug than the one being fixed. These are
authenticated, side-effecting API calls and none of them should ever be cached.

Once this lands, two temporary workarounds can be removed: the `rl_livewire_header`
map in `docker/nginx.conf` and the `rl-sync-body-auth.php` mu-plugin.

For **local** development, STDIO works directly without the proxy:

```json
{
  "mcpServers": {
    "remote-leverage-local": {
      "command": "wp",
      "args": [
        "--path=/path/to/web/wp",
        "mcp-adapter", "serve",
        "--server=mcp-adapter-default-server",
        "--user=ai-content-agent"
      ]
    }
  }
}
```

## Why the sync abilities set `show_in_rest` but not `public`

WordPress 7.1 gates the `/wp-abilities/v1/abilities/{name}/run` REST endpoint
on `meta.show_in_rest` (seeded from `meta.public` unless set explicitly) — an
ability without it 404s before its own `permission()` callback ever runs. The
four sync abilities set `show_in_rest => true` directly (without setting the
broader `public` flag) so the REST run endpoint will consider them, while
staying out of general ability listings and MCP's `public`-keyed
default-server auto-discovery. Actual authorization is still enforced by each
ability's own `permission()` check against `rl_manage_ai_sync` — `show_in_rest`
only controls whether the endpoint exists at all, not who may call it.

## Onboarding a non-technical editor

[docs/claude-desktop-for-editors.md](claude-desktop-for-editors.md) is the hand-to-the-colleague
guide: Claude Desktop, the Automattic STDIO proxy, and an `ai-content-agent` application
password. It is written for someone who does not know WordPress, and it deliberately does not
explain anything they cannot act on.

**A claude.ai custom connector is not an option here, and it is worth recording why** so nobody
spends a day rediscovering it. Custom connectors authenticate as authless or OAuth; the
`static_headers` beta is org-admin-only and the HTTP Basic case — which is exactly what a
WordPress Application Password is — is an open, unresolved bug ([claude-ai-mcp#990][mcp990], and
duplicates #112, #240, #506, #644, #690): the configured header is not sent on the initial
request and the connector falls back to OAuth discovery, 401ing. Claude Desktop with the STDIO
proxy sidesteps this entirely because the proxy holds the credential locally.

If the connector experience is wanted later, the path is the
[Enable Abilities for MCP][enable-abilities] plugin, which ships an embedded OAuth 2.1 server
with CIMD and **coexists with** `wordpress/mcp-adapter` rather than replacing it. Two caveats
before reaching for it: it enables all 112 of its own abilities by default and would need
locking down to this theme's nine, and it does nothing about the session-header requirement
below.

[mcp990]: https://github.com/anthropics/claude-ai-mcp/issues/990
[enable-abilities]: https://wordpress.org/plugins/enable-abilities-for-mcp/

### Four prerequisites, none of which the editor can do themselves

All four fail as "Claude has no tools", so check them in order rather than guessing.

1. **The session header must reach the origin.** Measured again on 2026-09-16: `/wp-json/`
   answers 200 and the MCP endpoint answers **401** unauthenticated, which confirms the
   `Authorization` half reaches the origin. The session half is still stripped by CloudFront, and
   is covered in the meantime by `rl-mcp-session-bridge.php` — so this no longer blocks an
   editor, but the distribution should still be fixed and the bridge deleted.
   `scripts/verify-mcp.sh` distinguishes the failure modes.
2. **Application Passwords must be available site-wide.** WordFence disables them by default and
   turned every machine-to-machine credential off on 2026-09-16; see
   [known-issues.md](known-issues.md) item 22. The reliable test, because core adds this key only
   when they are available:
   ```bash
   curl -s "https://staging.remoteleverage.com/wp-json/" | jq .authentication
   # {"application-passwords": {...}}  -> available
   # {}                                -> disabled; nothing can authenticate
   ```
3. **The credential must exist.** For a colleague, create it in the admin — Users →
   `ai-content-agent` → Edit → Application Passwords — and **name it anything except
   `mcp-client`**. That string is `ContentAgentProvisioner::PASSWORD_NAME`, and `--rotate` revokes
   every password carrying it, so `wp acorn rl:ai:agent --rotate` would silently cut off whoever
   was issued one before. A distinctly-named password (`claude-desktop-<name>`) is invisible to
   that sweep and stays individually revocable. Reserve `--rotate` for the shared credential in
   `.mcp.json`.
4. **The agent must be allowed to edit published pages.** Every capability flag defaults to
   `false`. `AI_AGENT_CAN_EDIT_PUBLISHED` and `AI_AGENT_CAN_READ_LEADS` are now staging
   environment secrets and are on the allowlist in `scripts/sync-app-secrets-from-env.py` — the
   allowlist is what `main()` iterates, so a key missing from it is dropped in silence. They
   reach the container only when the **Sync app secrets** workflow runs. Staging only.

### What an edit does to a pattern-backed page

`update-page-sections` resolves `wp:pattern` references before applying overrides — it has to,
because a reference carries no content for an override to attach to — and writes the expanded
result back. **The first edit therefore converts a git-backed page into database-resident
markup**, with the same "lost on the next refresh" consequence documented above for
`clone-page`.

The ability now detects this and returns `detached_from_patterns` plus a `warning` naming the
`patterns/<slug>.php` file the change should be ported into, and its description instructs the
model to relay that warning verbatim. The editor-facing guide tells them to forward it. Treat an
incoming forward as a small, real task: the alternative is silently losing their work.
