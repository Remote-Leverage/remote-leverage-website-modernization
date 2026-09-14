# Known issues

Live bugs, dead configuration, and documentation that contradicts the code. Found while writing this documentation set on 2026-09-14, by reading the code rather than the docs.

Nothing here has been fixed — each entry names the problem and a proposed fix so it can be scheduled.

---

## Bugs

### 1. `/partner-dashboard` throws — three inconsistent definitions of one URL

Three places disagree about where `/partner-dashboard` goes, and none of them point anywhere real:

| Location | Says |
| :--- | :--- |
| `routes/web.php:69-71` | `redirect()->route('partner.portal')` |
| `config/redirects.php` | `'partner-dashboard' => 'partner-portal'` |
| `resources/views/archive-rl_partner.blade.php:77,84` | Links to `/partner-dashboard` and `/partner-dashboard#register` |

No route is named `partner.portal` — the registered names are `referrer.portal` and `referrer.register`. No path `partner-portal` exists either; the real one is `referrer-portal`.

**Effect:** the two CTA buttons on the partner directory raise a `RouteNotFoundException`. The legacy redirect map entry is also dead.

**Proposed fix:** point the route at `referrer.portal`, change the redirect map entry to `referrer-portal`, and repoint the blade links at `route('referrer.portal')` and `route('referrer.register')` rather than a hardcoded path. Add a route-name assertion to `tests/Feature/RoutesTest.php` so a missing name fails CI instead of production.

### 2. Calendly webhook signatures are unverified

`config/services.php` reads `CALENDLY_WEBHOOK_SIGNING_KEY`, but the key is absent from `.env` and from `.env.example`. With no key configured, `/api/webhooks/calendly` accepts unsigned payloads — anyone who can reach the endpoint can flip a lead to `booked` or `canceled`.

**Proposed fix:** set the key in every environment, and make `CalendlyWebhookController` reject requests when no signing key is configured rather than falling through to accept.

### 3. Sentry is installed but silent

`sentry/sentry-laravel` is a dependency, `config/sentry.php` is fully populated, and `@sentry/browser` is in `package.json` — but no `SENTRY_LARAVEL_DSN` or `SENTRY_DSN` is set in `.env`. Nothing is reported from any environment.

**Proposed fix:** set the DSN for staging now, and treat "errors reported" as a cutover gate for production.

## Dead configuration

Eight keys sit in `.env` and are read by nothing. All eight are documented in the archived README's environment reference as though they were live, which is how they survived.

| Key | Reality |
| :--- | :--- |
| `ZEROBOUNCE_API_KEY` | No email-validation integration exists |
| `REFERRAL_WEBHOOK_SECRET` | The code reads `REFERRAL_WEBHOOK_URL` |
| `BARBA_ENABLED` | No Barba.js anywhere |
| `LOCOMOTIVE_ENABLED` | No Locomotive Scroll anywhere |
| `PRISM_SERVER_ENABLED` | `PrismAiAuditor` does not read it |
| `STRIPE_TEST_KEY`, `STRIPE_TEST_SECRET` | Test mode comes from using test values in `STRIPE_KEY`/`STRIPE_SECRET` |
| `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` | The code reads `GOOGLE_CALENDAR_CLIENT_ID`/`_SECRET` |

Conversely, five keys the code *does* read appear in no `.env` and no `.env.example`:

`HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_PORTAL_ID`, `CALENDLY_WEBHOOK_SIGNING_KEY`, `CALENDLY_LIVE_CALL_EVENT_TYPE`, `LIVE_CALL_MEET_URL`, `STRATEGY_CONSULTANT_EMAIL`.

HubSpot is the consequential one: with neither an environment token nor an admin-configured one, `HubSpotGateway` silently no-ops. A lead is captured, the audit log records the dispatch, and no CRM contact is ever created.

**Proposed fix:** delete the eight dead keys from `.env`, and regenerate `.env.example` from [configuration.md](configuration.md) so every key the code reads is present and empty, grouped by domain, each with a one-line note on what degrades when it is blank.

## Stale documentation

The user's instinct that the old README was "just a proposal" was right, and the drift was not limited to it.

### `plan.md` (repo root) — worst offender

Phases 3–8 all read `0% Completed`. In reality Phase 3 (design tokens), Phase 4 (Livewire), Phase 5 (38 blocks) and Phase 6 (Blade templates) are essentially complete, and Phase 7 (testing/CI) is done bar visual regression. Anyone reading `plan.md` for status gets a badly wrong picture.

Phase 7 also still lists *"Verify Customer.io lead identification upon Gravity Forms submission"* — Gravity Forms was retired by ADR-0008.

**Proposed fix:** either update the percentages and drop the Gravity Forms line, or retire `plan.md` into `docs/archive/` and let the README's status section be the single source. Recommendation: archive it. A hand-maintained percentage tracker drifts by default; the README's gate table does not, because it is derived from verifiable facts.

### `PAGE-MIGRATION-STATUS.md` (repo root)

Lists the four comparison pages and `/referral/` as "🔧 Needs migration". The checklist and the database both show them migrated. Two overlapping trackers is a liability — this was already flagged in [content-migration-next-phase-plan.md](content-migration-next-phase-plan.md) and has not been acted on.

**Proposed fix:** fold its review notes into [content-migration-checklist.md](content-migration-checklist.md) and archive the file. *(A "superseded" banner has been added to it in the meantime.)*

### `docs/adr-status.md`

WR-105 says *"No `.github/workflows` directory anywhere in the repo. Not being worked for now."* Both `ci.yml` and `deploy-staging.yml` now exist and run. WR-105 is done, not on hold.

**Proposed fix:** mark WR-105 done with the workflow files as evidence, and re-verify the other "on hold" rows on the same pass.

### `docs/content-migration-checklist.md`

Refers to `App\Support\LegalDocument` with 9 Pest tests. The class is `App\Support\DocumentOutline` (renamed, tests in `tests/Unit/DocumentOutlineTest.php`).

**Proposed fix:** one-line correction.

### The archived READMEs

Both are now in `docs/archive/` with a banner. For the record, what they got wrong:

| Claim | Reality |
| :--- | :--- |
| "Sage 11 + Acorn 5" (root README) | Sage 10 + Acorn 6 |
| "Vite 6" | Vite 8 |
| "~16 unified blocks" | 38 |
| "21+ block patterns" | 54 |
| "53 tests, 251 assertions" | 404 tests, 1320 assertions |
| "5 bounded contexts" | 7 (Sync and Lead are absent from the proposal entirely) |
| `PartnerPortalDashboard`, `PartnerRegistrationForm` | `ReferrerPortalDashboard`, `ReferrerRegistrationForm` |
| `RegisterPartnerAction` | `RegisterReferrerAction` |
| Magenta `#F8248A` / `#E91E63` | `#F90066` / `#D90057` |
| Block table listing 18 blocks | 20 blocks omitted, including every `about-*`, `comparison-*` and `case-study` block |
| No mention of | Sync domain, AI/MCP abilities, `case_study` CPT, Calendly/Referral/Marketing/Environment Sync admin screens, the Calendly token pool |

## Design and process observations

These are not bugs, but they are things a newcomer should know and the team may want to revisit.

### Synchronous listeners are a latency risk at production volume

No listener implements `ShouldQueue` and no queue worker is deployed (WR-106, on hold). This is internally consistent and was a deliberate decision — but it means a single lead submission makes outbound HTTP calls to HubSpot, Slack, PostHog, Customer.io and a webhook **inside the user's request**, and the latency is additive. Staging traffic will not surface this; production traffic on the booking funnel might.

**Proposal:** before cutover, measure the p95 of `CaptureLeadAction` end to end with all integrations live. If it exceeds ~800ms, provisioning a queue worker becomes a launch blocker rather than a deferred nicety.

### Livewire components are registered under two names each

`LivewireServiceProvider` registers all seven components twice — `booking.multistep-booking-wizard` and `multistep-booking-wizard`, and so on. That is presumably backwards compatibility for existing templates, but it means there is no single canonical name, and a grep for usage misses half the call sites.

**Proposal:** pick the namespaced form as canonical, migrate the templates, and drop the bare aliases.

### The documentation tree is split across two directories

ADRs live in `doc/adr/` at the repository root; everything else lives in `web/app/themes/remote-leverage/docs/`. The split is historical and mildly confusing — `doc` and `docs` one character apart is a genuine footgun.

Merging them was deliberately **not** done in this pass: about ten source files carry `docs/…` references in comments that are relative to the theme, and moving the tree would silently break all of them for no functional gain.

**Proposal:** if this is worth tidying, move `doc/adr/` → `web/app/themes/remote-leverage/docs/adr/` (the smaller move, and ADRs have no inbound code references), rather than moving the larger tree.

### `web/app/themes/remote-leverage/plan.md` is a second stale plan

The theme carries its own `plan.md` alongside the root one. Same drift problem, doubled.

**Proposal:** archive both.

### A stray SQL file sits in the theme root

`remoteleveragev2-2026-09-14-a2d62b7.sql` — zero bytes, presumably an interrupted `wp db export`. It is correctly gitignored (`*.sql`, theme `.gitignore:7`), so this is housekeeping rather than a repository problem — just delete it.
