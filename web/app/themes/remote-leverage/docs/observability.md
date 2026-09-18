# Observability

Two things live here, and they answer opposite questions.

`rl_lead_activity_logs` records **what each domain decided to do** — "dispatched the booking
webhook", "synced to HubSpot". `rl_integration_calls` records **what actually crossed the wire**
when it did it. Sentry is the third surface, and most of the work on it has been making it quiet
enough that anyone still reads it.

---

## 1. Integration call log

### Why it exists

The activity log's payload is written by hand at each call site, *before* the call returns. That
is the wrong shape for diagnosis. A real entry from before this existed:

```
OutgoingWebhook  CONSUMPTION  succeeded
Dispatched lead.booking_completed outgoing webhook
{ "event": "lead.booking_completed", "webhook_url": "https://n8n.srv1338052.hstgr.c..." }
```

That says the dispatch was attempted. It cannot say whether the endpoint accepted it, what was
sent, or what came back — and when HubSpot rejects a contact, the property it objected to is
nowhere. There were nineteen outbound call sites across Calendly, HubSpot, Slack, Stripe and
Google, each with its own hand-rolled logging, and none of them logged both directions.

### How it captures everything

Almost every outbound call goes through Laravel's `Http` facade, which dispatches events carrying
the real request and response. `ObservabilityServiceProvider` listens to three:

| Event | Recorded as |
| :--- | :--- |
| `RequestSending` | start time only, for duration |
| `ResponseReceived` | the full call — `succeeded` (2xx/3xx) or `failed` (4xx/5xx) |
| `ConnectionFailed` | `error` — no response exists to inspect anywhere else |

`error` is deliberately distinct from `failed`. A Calendly timeout otherwise appears in the
activity log as a booking that simply did not happen, with no indication why.

The outgoing lead webhook uses WordPress' HTTP API rather than Laravel's, so it is captured
separately via the `http_api_debug` action. That hook is narrowed to the configured webhook URL
only — WordPress calls api.wordpress.org for update checks on a schedule, and those would swamp
the table.

Because the capture is central, a new call site is covered the day it is written.

### Timing is keyed on method + URL, not object identity

Laravel wraps the outgoing request in a fresh `Illuminate\Http\Client\Request` for *each* of the
two events, so the instance seen on the way out is never the instance seen on the way back.
`IntegrationCallRecorder` keys start times on `METHOD url` with a queue per key, so several calls
to the same endpoint in one request are timed in the order they return.

### Credentials are never stored

Recording full request headers means recording `Authorization`. These rows are readable by any
WordPress administrator and live for weeks, so storing bearer tokens would make the diagnostic log
the easiest place in the system to harvest credentials from.

Every secret is replaced by a fingerprint that still answers the operational question:

```
Bearer ****9f3a (sha256:1b7d40e2)
```

Stable per token, distinct between tokens, useless for authenticating. The last four characters
survive so a fingerprint can be matched against Secrets Manager by eye; a secret shorter than
twelve characters is masked entirely, because showing the last four of a six-character secret
gives away most of it.

Redaction covers request and response headers (`redact_headers`), JSON bodies walked key by key at
any depth (`redact_keys`), and credentials that travel in the query string. A body that is not
JSON is kept verbatim — a form post or an HTML error page has no reliable structure to walk, and
mangling it with regexes costs more legibility than it buys.

### Naming the account, not the hash

A fingerprint distinguishes one token from another but says nothing about *which* is which. The
question actually asked of these logs is "whose token is rate limited?", because the answer
determines who to contact.

`CredentialRegistry` maps a credential to a human name, stored in its own indexed
`credential_label` column. For the Calendly pool, `SchedulingServiceProvider` registers a resolver
that reads the account email from the identity lookup `CalendlyClient` already performs and caches:

```
as admin@remoteleverage.com (Primary)
```

Two rules matter:

- **A resolver must never make a network call.** Resolvers run while an outbound call is being
  recorded, so anything that fetched would trigger a request, which triggers a recording, which
  runs the resolver. `CalendlyClient::cachedAccountEmail()` reads cache only and returns `null`
  rather than fetching.
- **It is a resolver, not a map built at boot.** The pool is edited through wp-admin, and a fixed
  map would keep attributing calls to whoever held that slot before the edit. The registry is
  flushed on every pool save, toggle and removal.

A token that has never been used shows no email, which is itself worth seeing.

### Retention

These rows hold full request and response bodies, which for a lead sync means a complete copy of
that person's contact details. They exist to diagnose a problem in the days after it happens;
keeping them longer turns a debugging aid into a second, unmanaged store of personal data that
nobody remembers during a deletion request.

- Default 30 days (`RL_INTEGRATION_CALL_RETENTION_DAYS`).
- Pruned by a daily WP-Cron hook, `rl_prune_integration_calls`.
- Or manually: `wp acorn rl:prune-integration-calls [--days=N] [--dry-run]`.
- `lead_id` cascades on delete, so erasing a lead erases the copies of their data these bodies
  necessarily contain.

Pruning is scheduled rather than left to a deploy task because retention is a data-protection
commitment, and one that only holds if it runs on a site nobody has deployed to in a month.

### Host allowlist

`config/observability.php` maps hosts to integration names, matched as a suffix so
`api.calendly.com` and `calendly.com` both resolve to `calendly`. Unknown hosts are **not**
recorded by default (`RL_RECORD_UNKNOWN_HOSTS`): the bulk content-import commands fetch hundreds
of pages of HTML in a single run, and recording those would bury the integration traffic this
table exists to make visible. A genuinely new integration should earn a line in `hosts` rather
than arriving through a catch-all.

### Failure is always soft

Every entry point is wrapped. Recording is diagnostic — a broken recorder must never turn a
successful HubSpot sync into a failed one, and a table that does not exist yet (a request arriving
between deploy and migration) must not take the site down.

---

## 2. The lead timeline

`LeadsAdminDashboard` interleaves both sources chronologically. Reading them as separate lists
loses the thing that makes them useful together: a HubSpot 400 sits immediately under the entry
that claimed the sync succeeded, and the ordering is what makes that visible.

Within the same second the decision sorts before the call it caused, because several rows are
written inside one second and an unstable comparison would let them swap between page loads.

Each row carries a 16px icon so its source is identifiable without reading it — the Remote
Leverage mark for the Lead and Referral domains, brand marks for Calendly, HubSpot, Slack, Stripe,
Google, Customer.io, PostHog and ZeroBounce, a share glyph for webhooks and an envelope for email.
`IntegrationIcons` emits one SVG sprite of `<symbol>`s per page and a seven-element `<use>` per
row; inlining the artwork per row would add roughly 4KB per entry to a timeline that routinely
runs to fifty of them. The RL mark is read from `resources/images/logo-icon-black.svg` so it
cannot drift from the brand asset.

---

## 3. Sentry

### Server side — `config/sentry.php`

`ignore_exceptions` drops nine classes that are normal operation, not defects: a 404 from a
scanner, a visitor mistyping an email, an expired CSRF token. `ignore_transactions` drops `/up`,
`/health`, `/favicon.ico`, `/robots.txt`, `/wp-cron.php` and `/wp-admin/admin-ajax.php` — hit
constantly, with nobody waiting on them, and tracing them dominates the performance data with
traffic that has no customer behind it.

`tests/Unit/SentryNoiseTest.php` asserts every ignored class actually **exists**. A renamed or
misspelled class fails silently: the filter matches nothing and the noise returns with no error
anywhere to explain it.

### Browser side — `resources/js/app.js`

The browser SDK had no filtering at all, so it would have inherited production's noise profile at
cutover. In rough order of how much each removes:

| Filter | What it stops |
| :--- | :--- |
| `allowUrls` scoped to `window.location.host` | the big one — only errors whose stack frames point at our own code report |
| `SENTRY_BOT_UA` | gates `Sentry.init` entirely, so crawlers and headless engines never initialise the SDK |
| `ignoreErrors` | `Script error.`, ResizeObserver loop, aborted fetches, `QuotaExceededError`, in-app-browser noise |
| `denyUrls` | browser extensions plus the nine third-party scripts this site embeds |
| `beforeSend` | drops stackless events — the cross-origin case that groups into one huge unactionable issue |

`allowUrls` is strict by design. **If the theme bundle is ever served from a CDN on a different
host than the page, real errors stop reporting** — and the failure mode is silence, not an error.
The fix at that point is to add the asset host to the array.

### Release tagging

Both SDKs tag events with the version the running image was built from. The Docker build (`ARG
RELEASE_VERSION`) writes it straight into `/var/www/html/.env` as `APP_VERSION=...` — not a
`SENTRY_*` name, since the browser side reads it too — and Bedrock's own Dotenv loader in
`config/application.php` picks it up from there — no extra entrypoint step needed, and a real
environment variable still wins over `.env` if one is ever set. `config/sentry.php` reads
`APP_VERSION` as its `release`. `deploy-production.yml` passes `$RELEASE_TAG`, the
`v-YYYYMMDD-vN` tag that triggered the run, as that build arg; `deploy-staging.yml` isn't
tag-triggered, so it passes `$GITHUB_SHA` instead. The browser SDK reuses the same value: `app.blade.php`
exposes `config('sentry.release')` as `window.APP_VERSION`, which `app.js` passes as `release` to
`Sentry.init()`. One value, one source, both sides — so an issue can be filtered to the deploy that
introduced it instead of showing up tagged with no release at all.

### Production runs a different SDK

Production is still on `wp-sentry-integration`, not this stack's `@sentry/browser` +
`sentry/sentry-laravel`. Nothing in this repo changes what production reports; an alert like
`ReferenceError: oaiq is not defined` comes from `rl-elementor-blocks/assets/js/headless-calendly.js`
on the legacy site and has no counterpart here. v2 replaces that stack at cutover, which is when
these filters take effect.

---

## Reference

| Thing | Where |
| :--- | :--- |
| Config | `config/observability.php` |
| Provider | `app/Infrastructure/Providers/ObservabilityServiceProvider.php` |
| Model / recorder / labels | `app/Infrastructure/Observability/` |
| Icons | `app/Infrastructure/WordPress/Admin/IntegrationIcons.php` |
| Table | `rl_integration_calls` (migration `2026_09_16_000006`) |
| Prune command | `wp acorn rl:prune-integration-calls` |
| Tests | `tests/Unit/IntegrationCallRecordingTest.php`, `tests/Unit/SentryNoiseTest.php` |

### Environment variables

| Variable | Default | Effect |
| :--- | :--- | :--- |
| `RL_RECORD_INTEGRATION_CALLS` | `true` | master switch |
| `RL_RECORD_UNKNOWN_HOSTS` | `false` | record hosts not in the allowlist, as `other` |
| `RL_RECORD_WP_HTTP` | `true` | capture the outgoing webhook via `http_api_debug` |
| `RL_INTEGRATION_CALL_RETENTION_DAYS` | `30` | prune window |
