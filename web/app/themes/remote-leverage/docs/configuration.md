# Configuration

Every environment variable, verified on 2026-09-14 against the code that reads it.

The old README listed variables that nothing reads and omitted several the code does read. The tables below are generated from `grep` over `env()` and `Config::define()`, not from memory. **A variable absent from these tables is not configuration — it is dead text.**

Precedence for anything with an admin screen (Calendly tokens, lead settings, referral settings, webhook URLs) is **admin option first, environment variable as fallback**. Changing `.env` will not override a value an admin has set.

---

## WordPress core (Bedrock, `config/`)

Defined via `Config::define()` in `config/application.php` and `config/environments/*.php`.

| Variable | Notes |
| :--- | :--- |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST` | Or a single `DATABASE_URL` DSN. `DB_PREFIX` optional. |
| `WP_ENV` | `development` \| `staging` \| `production`. **Load-bearing** — gates the sync feature, `redirect_canonical` removal, and debug settings. |
| `WP_HOME`, `WP_SITEURL` | `WP_SITEURL` is conventionally `${WP_HOME}/wp` |
| `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT` | Generate at <https://roots.io/salts.html> |
| `APP_KEY` | Laravel key, `base64:` prefixed |
| `ACF_PRO_KEY` | Maps to `ACF_PRO_LICENSE` |
| `WP_DEBUG_LOG` | Optional log path |
| `WP_REDIS_HOST`, `WP_REDIS_PORT`, `WP_REDIS_PASSWORD`, `WP_REDIS_SCHEME`, `WP_REDIS_CLIENT`, `WP_REDIS_PREFIX`, `WP_REDIS_DATABASE`, `WP_REDIS_GRACEFUL` | Leave `WP_REDIS_HOST` empty to disable |

## Scheduling — Calendly

`config/services.php`.

| Variable | Read by |
| :--- | :--- |
| `CALENDLY_API_KEY`, `CALENDLY_API_KEY_2`, `CALENDLY_API_KEY_3`, `CALENDLY_API_KEY_4` | Seeds `CalendlyTokenPool` (option `rl_calendly_token_pool`) |
| `CALENDLY_USER_URI` | `CalendlyClient` |
| `CALENDLY_DEFAULT_EVENT_TYPE` | Fallback event type |
| `CALENDLY_T10_EVENT_TYPE` | High-tier (MRR ≥ $10k) |
| `CALENDLY_T0_EVENT_TYPE` | Standard (MRR < $10k) |
| `CALENDLY_LIVE_CALL_EVENT_TYPE` | Instant live call — **not in `.env`** |
| `CALENDLY_WEBHOOK_SIGNING_KEY` | Webhook signature verification — **not in `.env`** |

The four event-type vars seed `CalendlyEventTypeRoleResolver` (option `rl_calendly_event_type_roles`); once set in wp-admin, the option wins.

## Scheduling — live calls

| Variable | Read by |
| :--- | :--- |
| `LIVE_CALL_MEET_URL` | `LiveCallAvailabilityRouter` — overrides the default Meet room. **Not in `.env` or `.env.example`.** |

The `GOOGLE_CALENDAR_*` keys and `STRATEGY_CONSULTANT_EMAIL` were read by the Google Calendar
booking fallback, removed on 2026-09-21 — see [domains/scheduling.md](domains/scheduling.md) for
why. Nothing reads them now; they are safe to drop from `.env` and from Secrets Manager.

## Lead

| Variable | Read by |
| :--- | :--- |
| `HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_PORTAL_ID` | `HubSpotGateway`, as the fallback behind the admin-configured token. **Neither is in `.env` or `.env.example`** — without one or the other, HubSpot sync silently no-ops. |
| `SLACK_WEBHOOK_URL` | `HandleLeadEventsForSlack` (option `rl_slack_webhook_url` wins) |
| `LEAD_WEBHOOK_URL` | `HandleLeadEventsForWebhook`. **Defaulted in `config/services.php`** to the n8n endpoint `…/webhook/gravityforms-leads`, so an environment that sets nothing still posts. This var overrides the default; the admin setting (`rl_lead_webhook_url`) is the last resort behind both. An explicit empty value is the off switch. |
| *(no variable)* | The other two n8n flows — `services.webhooks.lead_form_url` (`…/webhook/lead-form`) and `services.webhooks.hubspot_lead_url` (`…/webhook/hubspot-lead-creation`) — are **literals in `config/services.php` with no `env()` and no admin setting**, by request. They are destinations rather than credentials and are the same in every environment; moving one is an edit to that file and a deploy. Both fire on the partial capture only. |

## Tracking

| Variable | Read by |
| :--- | :--- |
| `POSTHOG_API_KEY`, `POSTHOG_HOST` | `PostHogClient`, front-end snippet. Host defaults to `https://us.i.posthog.com`. |
| `CUSTOMERIO_SITE_ID`, `CUSTOMERIO_API_KEY` | `CustomerIOClient` (Track API v1). `CUSTOMERIO_APP_API_KEY` was **removed 2026-09-16** — only `config/services.php` ever referenced it, nothing read it. The App API is a different Customer.io product (broadcasts, segments) that this codebase does not use. |
| `CUSTOMERIO_CDP_WRITE_KEY` | `TrackingHooks` — the browser CDP snippet (`window.cioanalytics`), added 2026-09-15 when v2 switched from the classic `_cio` tracker to match production. **Not the site id**: CDP takes a write key, a different Customer.io product, and a site id here 404s the asset URL and silently queues every event forever. Blank → no snippet, every client call site no-ops. Server-side Track API v1 still uses `SITE_ID`/`API_KEY`. |

GTM, LinkedIn Insight and Meta Pixel are configured inside the Google Site Kit / GTM container, not here.

## Referral

| Variable | Read by |
| :--- | :--- |
| `STRIPE_KEY`, `STRIPE_SECRET` | `StripeConnectGateway` (Connect payouts) and `StripePaymentIntentGateway` (live inbound charges) |
| `STRIPE_TEST_KEY`, `STRIPE_TEST_SECRET` | `StripePaymentIntentGateway` when `STRIPE_TEST_MODE` is on |
| `STRIPE_TEST_MODE` | Selects the test key pair for inbound charges |
| `STRIPE_CONNECT_CLIENT_ID` | Connect onboarding |
| `STRIPE_WEBHOOK_SECRET` | `StripeWebhookController` signature verification |
| `STRIPE_WEBHOOK_FORWARD_URL` | Where `payment_intent.succeeded` payloads are forwarded |
| `STRIPE_DEFAULT_THANKYOU_URL` | **Optional absolute override**, read by `PaymentGatewayBlock`. Blank falls back to `home_url(PaymentGatewayBlock::DEFAULT_THANKYOU_PATH)` — this site's own `/referral-program-thank-you-page-deposit/` — so it is correct on every host with no value set. Set it only to send payment to a *different* host. **Do not put a local host here:** `seed-staging-secrets.sh` copies it verbatim into staging (fixed 2026-09-15, it held a `.test` URL) |
| `REFERRAL_WEBHOOK_URL` | `DispatchReferralWebhook` |
| `REFERRAL_DEFAULT_REWARD_AMOUNT` | Default commission |

## PartnerHub

| Variable | Read by |
| :--- | :--- |

## Sync

Local only — read by the sync commands to call *out* to a remote. Never added to `scripts/seed-staging-secrets.sh`.

| Variable | Notes |
| :--- | :--- |
| `STAGING_SYNC_URL`, `STAGING_SYNC_USER`, `STAGING_SYNC_APP_PASSWORD` | The `sync-service` user's application password on staging |
| `PRODUCTION_SYNC_URL`, `PRODUCTION_SYNC_USER`, `PRODUCTION_SYNC_APP_PASSWORD` | Present so gate 3 has a URL to refuse. Setting these does not make production syncable — three other gates still refuse. |
| `STAGING_SYNC_BODY_AUTH` | **Temporary, and now removable.** Sends the sync credential in the request body as well as the `Authorization` header, for a CDN that strips the header. Read by `SyncClient`; the receiving half is `web/app/mu-plugins/rl-sync-body-auth.php`. **Verified 2026-09-15: CloudFront now forwards `Authorization` on `/wp-json/wp-abilities/*`, so this flag is no longer doing anything** — a header-only call to `app/export-syncable-settings` on staging returns 200, and the same call with no credential returns 401. Set it to `false`, confirm a push still runs, then delete both halves. `SyncClient` refuses to attach it when either side is production. See [domains/sync.md](domains/sync.md). |

## Security — WordFence

`config/wordfence.php` (theme) and one Bedrock constant. Settings are applied on deploy by
`rl:deploy`; the repository is the source of truth and wp-admin drift is reverted.

| Variable | Notes |
| :--- | :--- |
| `WORDFENCE_APPLY_ON_DEPLOY` | Default `true`. Set `false` to leave WordFence entirely under wp-admin control on an environment. |
| `WFWAF_STORAGE_ENGINE` | **Not an env var** — defined as `mysqli` in `config/application.php`. Keeps firewall state in the database rather than in `wp-content/wflogs/`, which does not survive a container rebuild. Do not remove it. |

`wp acorn rl:wordfence --dry-run` reports drift without writing.

**One WordFence default is deliberately reversed.** `loginSec_disableApplicationPasswords` ships
as `true` and switches WordPress Application Passwords off site-wide, which breaks Environment
Sync, `rl:sync:page` and both MCP servers with an error (`rest_not_logged_in`) indistinguishable
from a wrong password. `config/wordfence.php` sets it to `false`, and the setting must stay
**present** — absent means WordFence's default wins again. See
[known-issues.md](known-issues.md) entry 22 for the full account and the bounded risk.

## Monitoring

`config/sentry.php` reads a large Sentry option set. The one that matters:

| Variable | Notes |
| :--- | :--- |
| `SENTRY_LARAVEL_DSN` (or `SENTRY_DSN`) | Optional. **The DSN is committed as the `config/sentry.php` default** since 2026-09-16 — a DSN is not a secret, and ECS maps Secrets Manager keys to env vars one at a time, so waiting on a task-definition change left Sentry silent. Set this only to point an environment somewhere else. |
| `APP_VERSION` | Optional — already wired, and not a `SENTRY_*` name because it feeds more than Sentry's config. Read by `config/sentry.php` as the `release`. The Docker build writes it into `/var/www/html/.env`: the `v-YYYYMMDD-vN` release tag in production, the commit SHA in staging (not tag-triggered). Bedrock's own Dotenv loader (`config/application.php`) picks it up from there, and never overwrites a real environment variable, so PHP-side events are tagged without a task-definition change. `config('sentry.release')` also feeds `window.APP_VERSION` (`app.blade.php`) for the browser SDK's `release` option (`resources/js/app.js`). Set the env var only to override the baked value. |
| `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE`, `SENTRY_PROFILES_SAMPLE_RATE` | Optional |

Every other `SENTRY_*` key in `config/sentry.php` is the package's own default set — breadcrumb and tracing toggles — and needs no project value.

`ignore_exceptions` and `ignore_transactions` in that file, plus the browser-side filters in
`resources/js/app.js`, exist to keep the alert channel readable. Both are covered in
[observability.md](observability.md).

## Monitoring — integration call log

`config/observability.php`. Records the full request and response of every outbound integration
call into `rl_integration_calls`; see [observability.md](observability.md).

| Variable | Notes |
| :--- | :--- |
| `RL_RECORD_INTEGRATION_CALLS` | Default `true`. Master switch. |
| `RL_RECORD_UNKNOWN_HOSTS` | Default `false`. Records hosts outside the allowlist as `other`. Off because the bulk content-import commands would bury real integration traffic. |
| `RL_RECORD_WP_HTTP` | Default `true`. Captures the outgoing lead webhook, which uses WordPress' HTTP API rather than Laravel's. |
| `RL_INTEGRATION_CALL_RETENTION_DAYS` | Default `30`. These rows contain full lead PII by design, so they are pruned daily rather than kept. |

Credentials are never stored — `Authorization` is replaced by a stable fingerprint, and the
Calendly pool additionally records which **account** a call authenticated as.

## AI providers

`config/ai.php` ships with `roots/acorn-ai` and enumerates every provider the package supports (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `AWS_BEDROCK_*`, `AZURE_OPENAI_*`, `COHERE_API_KEY`, `GROQ_API_KEY`, `MISTRAL_API_KEY`, `OLLAMA_*`, `OPENROUTER_API_KEY`, `DEEPSEEK_API_KEY`, `XAI_API_KEY`, `VOYAGEAI_API_KEY`, `JINA_API_KEY`, `ELEVENLABS_API_KEY`, and more).

**These are vendor defaults, not project configuration.** Set only the provider you actually use. The config's declared defaults are `openai` generally and `gemini` for images.

## Keys removed from `.env` because nothing reads them — done 2026-09-15

Verified by grepping `app/`, `config/`, `resources/`, `routes/` and the Bedrock `config/` before deleting each one. All seven were in the archived README's environment reference as though they were live, which is how they survived:

| Variable | Why it was dead |
| :--- | :--- |
| `ZEROBOUNCE_API_KEY` | No email-validation integration exists |
| `REFERRAL_WEBHOOK_SECRET` | The code reads `REFERRAL_WEBHOOK_URL` |
| `BARBA_ENABLED` | No Barba.js in the codebase |
| `LOCOMOTIVE_ENABLED` | No Locomotive Scroll in the codebase |
| `PRISM_SERVER_ENABLED` | `PrismAiAuditor` does not read it |
| `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`, `GOOGLE_CALENDAR_*`, `STRATEGY_CONSULTANT_EMAIL` | Nothing — the Google Calendar booking fallback was removed on 2026-09-21 |

`STRIPE_TEST_KEY` / `STRIPE_TEST_SECRET` were on the same kill list in an earlier draft of [known-issues.md](known-issues.md) and **were kept** — see the Referral table above: the embedded card checkout reads them through `services.stripe.test_publishable_key` / `test_secret_key`.

Also in `.env` and unread by the theme: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` — WordPress mail is not configured through Laravel's mailer here, so these only matter if an SMTP plugin or `wp_mail` filter is added. They were left in place.

Notion was deleted on 2026-09-15: `NOTION_API_KEY` and `NOTION_PARTNERS_DATABASE_ID` are in neither file and should not reappear.

## `.env.example` — regenerated 2026-09-15

It used to cover only the Bedrock core set — database, environment, salts, `APP_KEY`, `ACF_PRO_KEY`, Redis — so a fresh clone booted WordPress with no working Calendly, HubSpot, Stripe, PostHog, Customer.io or sync.

It is now generated from the tables above: every key the code reads, grouped by domain, each with a one-line note on what degrades when it is blank.

**`env()` does not fall back over an empty value.** `KEY=` sets the value to `''`, which *overrides* the default in `env('KEY', 'default')` rather than falling back to it. Confirmed with `wp eval 'var_dump(env("LIVE_CALL_MEET_URL", "DEFAULT-KEPT"));'` → `string(0) ""`. So in both `.env` and `.env.example`:

- a key with **no** code default is present and blank (`CALENDLY_WEBHOOK_SIGNING_KEY=`);
- a key **with** a working code default is commented out with the default shown (`# GOOGLE_CALENDAR_ID='primary'`), so copying the template does not silently blank it.

The keys that fall in the second group: `DB_HOST`, `DB_PREFIX`, `CALENDLY_DEFAULT_EVENT_TYPE`, `CALENDLY_T10_EVENT_TYPE`, `CALENDLY_T0_EVENT_TYPE`, `LIVE_CALL_MEET_URL`, `POSTHOG_HOST`, `STRIPE_TEST_MODE`, `REFERRAL_DEFAULT_REWARD_AMOUNT`, and every `AI_AGENT_*`.

## AI content agent

`config/ai-wordpress.php`. All defaulted; see [ai-mcp-and-sync.md](ai-mcp-and-sync.md).

| Variable | Read by |
| :--- | :--- |
| `AI_AGENT_LOGIN`, `AI_AGENT_EMAIL`, `AI_AGENT_ROLE` | The provisioned agent user (`ai-content-agent`, `…@remoteleverage.com`, `editor`) |
| `AI_AGENT_CAN_PUBLISH`, `AI_AGENT_CAN_EDIT_PUBLISHED`, `AI_AGENT_CAN_READ_LEADS`, `AI_AGENT_CAN_UPLOAD_MEDIA` | Capability grants; **all default `true`** since 2026-09-18. Set one to `false` to close it — `ContentAgentProvisioner::ensure()` reconciles in both directions, so that actively revokes on the next deploy rather than leaving an earlier grant in place |
| `AI_AGENT_PROVISION_ON_DEPLOY` | Default `true`; set `false` to skip reconciliation |
