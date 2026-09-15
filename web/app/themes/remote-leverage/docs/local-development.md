# Local development

---

## Prerequisites

| Tool | Version |
| :--- | :--- |
| PHP | 8.3+ (CI runs 8.4) — with `mbstring`, `xml`, `ctype`, `iconv`, `intl`, `pdo_mysql`, `bcmath`, `zip` |
| Node | 20.19+ or 22.12+ |
| Composer | 2.x |
| MySQL | 8.0 |
| WP-CLI | any recent |

**ACF Pro is licensed.** `composer install` fails without credentials for `connect.advancedcustomfields.com`. Either put them in `auth.json` at the repo root (gitignored) or configure them globally:

```bash
composer config --global http-basic.connect.advancedcustomfields.com "$ACF_PRO_KEY" "https://remoteleverage.com"
```

## Setup

```bash
git clone git@github.com:Remote-Leverage/remote-leverage-website-modernization.git
cd remote-leverage-website-modernization

# Bedrock root
composer install
cp .env.example .env     # fill DB_*, WP_HOME, WP_SITEURL, the 8 salts, APP_KEY, ACF_PRO_KEY

# Theme
cd web/app/themes/remote-leverage
composer install
npm install

# Schema
wp acorn migrate
```

Salts: <https://roots.io/salts.html>. `APP_KEY` must be a `base64:` Laravel key.

The env var reference — which keys are actually read, and by what — is in [configuration.md](configuration.md).

## Running

```bash
# From web/app/themes/remote-leverage
npm run dev      # Vite dev server + HMR
npm run build    # production assets into public/build, page art into public/images
```

Serve the site however you normally serve a Bedrock install (Herd, Valet, nginx) with the document root at `web/`. The local host used throughout the docs is `https://remoteleverage-v2.test`.

### Or run the staging image

```bash
docker compose --env-file env up --build      # http://127.0.0.1:8080
```

This is the same `Dockerfile` staging runs, plus MySQL 8 and Redis 7. `app/` and `resources/` are bind-mounted, so PHP and Blade changes are live; asset changes still need `npm run build`.

`public/` is gitignored and produced entirely by that build, page art included — the sources live
in `resources/images/pages/`. A fresh clone shows no images until the first `npm run build`; a cold
run takes roughly 20-30s for the 629 page rasters under `resources/images/pages/` (they expand to
~1,340 files in `public/images/`, since every raster also gets a `.webp` sibling); a warm rebuild is
~3-5s from the content-hash cache in `node_modules/.cache/`. Both scale with the image count, so
treat these as orders of magnitude, not benchmarks. Never add an image by writing into `public/images/`: it survives locally
and disappears on deploy. Compare against <https://staging.remoteleverage.com>.

Two local-only behaviours worth knowing:

- The HTTPS redirect in `app/setup.php` is skipped when `WP_HOME` is `http://`, so the Docker stack works without TLS.
- `redirect_canonical` is removed when `WP_ENV === 'development'`, because WordPress 301-loops on URLs that include a port.

## Testing

```bash
./vendor/bin/pest                                   # 774 tests, 3677 assertions, ~9s
./vendor/bin/pest tests/Unit/LeadDomainTest.php     # one file
./vendor/bin/pest --filter="attribution"            # by name
```

The suite runs with **no WordPress and no database** — `tests/stubs.php` and `tests/bootstrap.php` provide WordPress function stubs. That is why it is fast enough to gate every PR, and also the bound on what it proves: domain logic, DTOs, attribution, block/pattern grammar and sync mechanics, not real WordPress integration.

There is no browser or visual-regression layer. Verify visual work with screenshots against production — see [design-system.md](design-system.md#verification-before-you-call-a-page-done).

## Linting

```bash
./vendor/bin/pint          # format
./vendor/bin/pint --test   # check only — this is what CI runs
```

Config: `pint.json` at the repo root. CI runs Pint from the theme directory.

## Command reference

All via `wp acorn <command>`, run from the theme directory (or anywhere, with `wp --path=web/wp`).

### Lead

```bash
wp acorn lead:purge                    # retention purge; 30-day floor always enforced
wp acorn lead:purge --days=90
wp acorn lead:process-abandoned        # default --hours=2; also runs hourly on WP-Cron
```

### Content migration

```bash
wp acorn content:audit-elementor                    # read-only readiness report
wp acorn content:audit-elementor --post_id=123
wp acorn content:convert-elementor --dry-run        # preview
wp acorn content:convert-elementor --post_id=123    # apply, queued for review
wp acorn content:import-posts                       # blog import from captured JSON
```

### Environment sync

```bash
wp acorn rl:sync:grant sync-service                 # one-time per environment, idempotent
wp acorn rl:sync:push --target=staging
wp acorn rl:sync:pull --target=staging
wp acorn rl:sync:settings --push --target=staging
wp acorn rl:sync:page 123 --push --target=staging
wp acorn rl:sync:page 0 --pull --remote-post-id=456 --target=staging
wp acorn rl:sync:purge
wp acorn rl:sync:rollback <session>                 # undo on the target
wp acorn rl:sync:rollback-local <session>           # undo an import into this environment
```

### Deploy

```bash
wp acorn rl:deploy                # migrations + rewrite flush under a cross-container lock
wp acorn rl:deploy --timeout=120
```

Normally invoked by `docker/entrypoint.sh`, not by hand.

### Useful WordPress-side

```bash
wp acorn migrate
wp acorn optimize
wp rewrite flush
npm run db:dump          # wp db export --add-drop-table
npm run db:import
```

## Database inspection

```bash
wp db query "SELECT id, email, status, source_type, monthly_revenue FROM wp_rl_leads ORDER BY id DESC LIMIT 5;"
wp db query "SELECT stage, action, status, created_at FROM wp_rl_lead_activity_logs WHERE lead_id = 123 ORDER BY id;"
wp db query "SELECT id, referrer_user_id, ip_address, created_at FROM wp_rl_referral_clicks ORDER BY created_at DESC LIMIT 10;"
```

Table names carry the WordPress prefix — `wp_rl_leads`, not `rl_leads`.

## Manual QA

Runbooks for attribution, webhook and analytics verification: [qa-attribution-webhooks.md](qa-attribution-webhooks.md).

Quick checks:

```bash
# Referral attribution
open "https://remoteleverage-v2.test/?via=testpartner"   # then check the rl_referrer cookie

# Webhooks
curl -X POST https://remoteleverage-v2.test/api/webhooks/calendly \
  -H "Content-Type: application/json" \
  -d '{"event":"invitee.created","payload":{"email":"founder@acme.com","scheduled_event":{"uri":"https://api.calendly.com/scheduled_events/test-123","start_time":"2026-09-18T15:00:00Z"}}}'

# Health
curl https://remoteleverage-v2.test/api/health
```

## Installed plugins

Only three, all Composer-managed:

| Plugin | Status (local, 2026-09-14) | Why |
| :--- | :--- | :--- |
| `advanced-custom-fields-pro` 6.8.9 | active | Block fields and the partner field group |
| `mcp-adapter` 0.6.1 | active | Exposes the Abilities API to MCP clients |
| `google-site-kit` 1.187.0 | **inactive** | GTM container injection (and the home for LinkedIn/Meta tags) — nothing is injected while it is off |

`redis-cache` is not in `composer.json` — it is downloaded in the `Dockerfile` and activated by the entrypoint, so it exists in containers only.

**Yoast SEO is not installed.** Production runs Yoast SEO Premium 28.4, and installing the matching plugin is a prerequisite for SEO parity at cutover — see [production-cutover.md](production-cutover.md).
