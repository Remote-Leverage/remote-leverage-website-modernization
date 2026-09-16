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

## Scheduling — Google Calendar

| Variable | Read by |
| :--- | :--- |
| `GOOGLE_CALENDAR_CLIENT_ID`, `GOOGLE_CALENDAR_CLIENT_SECRET`, `GOOGLE_CALENDAR_REFRESH_TOKEN`, `GOOGLE_CALENDAR_ID` | `GoogleCalendarClient` (`GOOGLE_CALENDAR_ID` defaults to `primary`) |
| `LIVE_CALL_MEET_URL` | `LiveCallAvailabilityRouter` — overrides the default Meet room. **Not in `.env` or `.env.example`.** |
| `STRATEGY_CONSULTANT_EMAIL` | Instant-call routing. **Not in `.env` or `.env.example`.** |

## Lead

| Variable | Read by |
| :--- | :--- |
| `HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_PORTAL_ID` | `HubSpotGateway`, as the fallback behind the admin-configured token. **Neither is in `.env` or `.env.example`** — without one or the other, HubSpot sync silently no-ops. |
| `SLACK_WEBHOOK_URL` | `HandleLeadEventsForSlack` (option `rl_slack_webhook_url` wins) |
| `LEAD_WEBHOOK_URL` | `HandleLeadEventsForWebhook` (option `rl_lead_webhook_url` wins) |

## Tracking

| Variable | Read by |
| :--- | :--- |
| `POSTHOG_API_KEY`, `POSTHOG_HOST` | `PostHogClient`, front-end snippet. Host defaults to `https://us.i.posthog.com`. |
| `CUSTOMERIO_SITE_ID`, `CUSTOMERIO_API_KEY`, `CUSTOMERIO_APP_API_KEY` | `CustomerIOClient` |
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

## Monitoring

`config/sentry.php` reads a large Sentry option set. The one that matters:

| Variable | Notes |
| :--- | :--- |
| `SENTRY_LARAVEL_DSN` (or `SENTRY_DSN`) | **Not set in `.env`.** Sentry is installed and configured but reports nothing. |
| `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, `SENTRY_TRACES_SAMPLE_RATE`, `SENTRY_PROFILES_SAMPLE_RATE` | Optional |

Every other `SENTRY_*` key in `config/sentry.php` is the package's own default set — breadcrumb and tracing toggles — and needs no project value.

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
| `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` | The code reads `GOOGLE_CALENDAR_CLIENT_ID`/`_SECRET` |

`STRIPE_TEST_KEY` / `STRIPE_TEST_SECRET` were on the same kill list in an earlier draft of [known-issues.md](known-issues.md) and **were kept** — see the Referral table above: the embedded card checkout reads them through `services.stripe.test_publishable_key` / `test_secret_key`.

Also in `.env` and unread by the theme: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` — WordPress mail is not configured through Laravel's mailer here, so these only matter if an SMTP plugin or `wp_mail` filter is added. They were left in place.

Notion was deleted on 2026-09-15: `NOTION_API_KEY` and `NOTION_PARTNERS_DATABASE_ID` are in neither file and should not reappear.

## `.env.example` — regenerated 2026-09-15

It used to cover only the Bedrock core set — database, environment, salts, `APP_KEY`, `ACF_PRO_KEY`, Redis — so a fresh clone booted WordPress with no working Calendly, HubSpot, Stripe, PostHog, Customer.io or sync.

It is now generated from the tables above: every key the code reads, grouped by domain, each with a one-line note on what degrades when it is blank.

**`env()` does not fall back over an empty value.** `KEY=` sets the value to `''`, which *overrides* the default in `env('KEY', 'default')` rather than falling back to it. Confirmed with `wp eval 'var_dump(env("LIVE_CALL_MEET_URL", "DEFAULT-KEPT"));'` → `string(0) ""`. So in both `.env` and `.env.example`:

- a key with **no** code default is present and blank (`CALENDLY_WEBHOOK_SIGNING_KEY=`);
- a key **with** a working code default is commented out with the default shown (`# GOOGLE_CALENDAR_ID='primary'`), so copying the template does not silently blank it.

The keys that fall in the second group: `DB_HOST`, `DB_PREFIX`, `CALENDLY_DEFAULT_EVENT_TYPE`, `CALENDLY_T10_EVENT_TYPE`, `CALENDLY_T0_EVENT_TYPE`, `GOOGLE_CALENDAR_ID`, `LIVE_CALL_MEET_URL`, `STRATEGY_CONSULTANT_EMAIL`, `POSTHOG_HOST`, `STRIPE_TEST_MODE`, `REFERRAL_DEFAULT_REWARD_AMOUNT`, and every `AI_AGENT_*`.

## AI content agent

`config/ai-wordpress.php`. All defaulted; see [ai-mcp-and-sync.md](ai-mcp-and-sync.md).

| Variable | Read by |
| :--- | :--- |
| `AI_AGENT_LOGIN`, `AI_AGENT_EMAIL`, `AI_AGENT_ROLE` | The provisioned agent user (`ai-content-agent`, `…@remoteleverage.com`, `editor`) |
| `AI_AGENT_CAN_PUBLISH`, `AI_AGENT_CAN_EDIT_PUBLISHED`, `AI_AGENT_CAN_READ_LEADS` | Capability grants; all default `false` |
| `AI_AGENT_PROVISION_ON_DEPLOY` | Default `true`; set `false` to skip reconciliation |
