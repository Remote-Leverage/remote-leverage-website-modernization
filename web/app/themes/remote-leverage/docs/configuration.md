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

GTM, LinkedIn Insight and Meta Pixel are configured inside the Google Site Kit / GTM container, not here.

## Referral

| Variable | Read by |
| :--- | :--- |
| `STRIPE_KEY`, `STRIPE_SECRET` | `StripeConnectGateway` |
| `STRIPE_CONNECT_CLIENT_ID` | Connect onboarding |
| `STRIPE_WEBHOOK_SECRET` | `StripeWebhookController` |
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

## Keys in `.env` that nothing reads

Verified by grepping the whole theme (`app/`, `config/`, `resources/`). Each of these appears in the current `.env` and in the archived README's environment reference, and is read by no code:

| Variable | Why it is dead |
| :--- | :--- |
| `ZEROBOUNCE_API_KEY` | No email-validation integration exists |
| `REFERRAL_WEBHOOK_SECRET` | The code reads `REFERRAL_WEBHOOK_URL` |
| `BARBA_ENABLED` | No Barba.js in the codebase |
| `LOCOMOTIVE_ENABLED` | No Locomotive Scroll in the codebase |
| `PRISM_SERVER_ENABLED` | `PrismAiAuditor` does not read it |
| `STRIPE_TEST_KEY`, `STRIPE_TEST_SECRET` | Test mode is selected by using test values in `STRIPE_KEY`/`STRIPE_SECRET` |
| `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` | The code reads `GOOGLE_CALENDAR_CLIENT_ID`/`_SECRET` |

Also in `.env` and unread by the theme: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` — WordPress mail is not configured through Laravel's mailer here, so these only matter if an SMTP plugin or `wp_mail` filter is added.

Proposed cleanup in [known-issues.md](known-issues.md).

## `.env.example` is incomplete

It covers only the Bedrock core set — database, environment, salts, `APP_KEY`, `ACF_PRO_KEY`, Redis. None of the ~30 integration keys the application actually reads are in it, so a fresh clone boots WordPress but has no working Calendly, HubSpot, Stripe, PostHog, Customer.io or sync.

Proposed fix: regenerate `.env.example` from the tables above, with every key present and empty, grouped by domain, and a one-line comment saying which feature degrades when it is blank.
