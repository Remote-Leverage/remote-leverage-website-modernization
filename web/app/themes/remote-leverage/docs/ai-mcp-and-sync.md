# AI MCP abilities & local↔remote sync — setup

This covers one-time setup for the two features added on top of `roots/acorn-ai`
and the `WordPress/mcp-adapter` plugin:

1. **MCP-callable landing-page abilities** (`app/Ai/Abilities/*`) — compose a page
   from already-registered `remote-leverage/*` block patterns. See
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

- Role: `editor` (has `edit_pages`; grant `publish_pages` only if you want this
  identity able to publish directly rather than always leaving a draft).
- Generate an Application Password for it from wp-admin → Users → (this user) →
  Application Passwords, or via WP-CLI:
  ```
  wp user create ai-content-agent ai-content-agent@remoteleverage.com --role=editor
  wp user application-password create ai-content-agent "mcp-client"
  ```
- This is the identity an MCP client (Claude Code / Claude Desktop) authenticates
  as when calling `app/list-patterns`, `app/create-landing-page`, and
  `app/update-landing-page-content`.

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

## Connecting an MCP client to the landing-page abilities

The `WordPress/mcp-adapter` plugin's default server exposes any ability with
`meta.mcp.public => true` — that's `app/list-patterns`, `app/create-landing-page`,
and `app/update-landing-page-content` — discoverable/executable via
`mcp-adapter/discover-abilities` and `mcp-adapter/execute-ability` on that server.

For a **remote** environment (staging/production), configure the MCP client to
go through the `@automattic/mcp-wordpress-remote` proxy, authenticated as
`ai-content-agent`:

```json
{
  "mcpServers": {
    "remote-leverage-staging": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://staging.remoteleverage.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "ai-content-agent",
        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

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
