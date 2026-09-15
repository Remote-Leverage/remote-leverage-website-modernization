# ADR Compliance & Launch Readiness

Verified against current codebase state, 2026-09-09, with WR-105, WR-106 and the ADR-0008 transport note re-verified **2026-09-15**. Checked by reading the actual files, not by trusting `docs/jira-adr-compliance-backlog.md`'s prior claims.

**Rows still carrying only a 2026-09-09 date were not re-checked on the 2026-09-15 pass** — treat them as unaudited rather than confirmed.

## Note: ADR-0008 formally accepted (2026-09-09)

**ADR-0008** (Lead domain, retire Gravity Forms) is now `Status: Accepted`. The transport question it left open is resolved as **synchronous subscriber** (matches the implementation — no listener uses `ShouldQueue`), consistent with WR-106 being on hold (no queue worker deployed).

**Amended 2026-09-15 — "synchronous subscriber" is now imprecise.** No listener implements `ShouldQueue`, but that is not the same as running in the user's request. `LeadServiceProvider::boot()` and `TrackingServiceProvider::boot()` wrap the HubSpot, Slack, webhook, email and PostHog/Customer.io listeners in `dispatch(…)->afterResponse()`, and Acorn's `Bootable` calls `fastcgi_finish_request()` before `$kernel->terminate()` — so they run **after the response is flushed**, in-process, with no worker. The only listener still inside the request is `HandleLeadCreatedForBooking` (`SchedulingServiceProvider`), and only on the final booking submit. Read the transport as *deferred in-process subscriber*, not *synchronous*.

## Backlog tickets (WR-98–106) — verified status

| Ticket | Title | Status | Evidence |
|---|---|---|---|
| WR-98 | LeadAbandoned lifecycle event processor | **Done** (2026-09-09) | `ProcessAbandonedLeadsAction` + `lead:process-abandoned {--hours=2}` command, hourly WP-Cron hook registered in `LeadServiceProvider::boot()`, dual dispatch logging to `LeadActivityLog`, Pest coverage added |
| WR-99 | Centralize GTM, LinkedIn Insight, Meta Pixel | **Config, not code** (rescoped 2026-09-09) | Google Site Kit is installed (`composer.json: wp-plugin/google-site-kit`) and ships a `Tag_Manager` module (`web/app/plugins/google-site-kit/includes/Modules/Tag_Manager.php`) that self-injects the GTM `<head>` snippet + `wp_body_open` noscript once a container is connected in the Site Kit admin UI. LinkedIn Insight and Meta Pixel will be added as tags *inside* that GTM container, not as custom TrackingHooks code. Remaining work is admin configuration (connect container, add tags in GTM), not engineering — dropping the custom PHP injection plan entirely. |
| WR-100 | Production Elementor content ingestion & batch conversion | **Closed by a different path** (2026-09-15) | `ApplyElementorConversionAction` + `content:convert-elementor {--dry-run} {--post_id=}` exist and persist converted markup + `_rl_conversion_status=needs_review`. **The production DB dump was never ingested and will not be:** all 119 posts came in via `wp acorn content:import-posts` from captured production JSON instead. ADR-0005's actual requirement is met — zero posts carry `_elementor_data` (verified 2026-09-14). See the closed gap section below. |
| WR-101 | Editorial review queue for AI-converted posts | **Done** (2026-09-09) | New `ContentAuditAdmin` class: "Conversion Status" column + badges on Posts/Pages list, status filter dropdown, "Approve & Mark Clean" row action (sets `_rl_conversion_status=approved`, archives `_elementor_data` → `_elementor_data_archived`). Registered via `DomainServiceProvider`. Pest coverage for the underlying action. |
| WR-102 | WP Admin configurable form & routing settings | **Done** (2026-09-09) | New `LeadSettingsService` (`rl_lead_settings` option, 30-day retention floor enforced, email validation) + "Leads → Settings" admin screen (notification emails, optional-field toggles, retention days, HubSpot/Slack/webhook overrides). Wired to real consumers: `HubSpotGateway` now prefers the configured token, new `HandleLeadEventsForEmailNotification` listener emails configured recipients on `LeadCreated`. Field-visibility toggles are exposed via `isFieldEnabled()`, but not yet wired into the booking wizard blade — that form doesn't currently render `company`/`notes` inputs at all, so there's nothing to toggle yet. Pest coverage added. |
| WR-103 | Legacy URL 301 redirects & visual regression suite | **Partial** (redirects done 2026-09-09, visual regression not started) | New `config/redirects.php` map + `LegacyRedirectMiddleware`, wired via `template_redirect` in `app/setup.php` (WP pages aren't routed through Acorn's HTTP kernel, so this follows the same hook pattern as the existing HTTPS redirect). Preserves query string/UTM params. Pest coverage added. Visual regression suite intentionally not built — no Playwright/tooling installed, and it needs real staging/production URLs this environment doesn't have; building a non-functional stub would be worse than leaving it explicit. |
| WR-104 | Real-time availability polling for InstantLiveCallButton | **On hold** (deprioritized 2026-09-09) | Component calls `LiveCallAvailabilityRouter` on mount, but the router just reads a cache flag — no real calendar integration, no live polling. Not being worked for now. |
| WR-105 | GitHub Actions CI (Pint, Pest, asset build) | **Done** (verified 2026-09-15) | `.github/workflows/ci.yml` runs Pint, Pest and the Vite production build on every PR, plus a guard that `public/images` was regenerated; `.github/workflows/deploy-staging.yml` builds to ECR and rolls the ECS service on push to `main`. The "no workflows directory" note was written before either existed. |
| WR-106 | Production queue worker provisioning & monitoring | **On hold** — reclassified 2026-09-15 as **throughput/reliability, not latency** | No Supervisor/systemd config for `wp acorn queue:work`. The ~800 ms latency rule that would have made this a launch blocker is **withdrawn**: `CaptureLeadAction::execute()` measures **6.75 ms p95** on the step-1 capture (all its outbound calls are already `dispatch(…)->afterResponse()`), and the booking-submit latency that did breach it was a Calendly duplicate-booking preflight bug — **4 435 ms p50 / 5 495 ms p95 → 521 ms p50 / 1 159 ms p95** once fixed. What remains for a real worker is worker-pool occupancy (~2.4–4.5 s per submission after the response), no retry and no dead-letter on `afterResponse` closures, and four deferred calls with no timeout set. See [`known-issues.md`](known-issues.md) and [`performance-baseline.md`](performance-baseline.md) Part 2. Deliberately still deferred. |

## Already done

- **WR-93 — Gutenberg Block/Pattern Library (parent)**: all 5 subtasks (WR-109–113: editor CSS parity, block previews, pattern library, landing page patterns, cross-browser QA) — consistent with recent commits.
- **Lead domain core (ADR-0008 baseline)**: Model, LeadActivityLog, CaptureLeadAction, PurgeOldLeadsAction + PurgeLeadsCommand, ProcessAbandonedLeadsAction + ProcessAbandonedLeadsCommand (WR-98), all 4 lifecycle events, LeadFormSubmitted extension point, HubSpotGateway, PhoneValidationService, Slack/webhook listeners — all wired via LeadServiceProvider.

## ~~Biggest gap for launch~~ — closed 2026-09-15

**WR-100's DB-dump ingestion never happened and no longer needs to.** The 119 blog posts came in
through `wp acorn content:import-posts` (captured production JSON → `ElementorProseExtractor` →
Gutenberg) rather than through a database dump and `content:convert-elementor`. The outcome
ADR-0005 actually requires is met: **zero posts carry `_elementor_data`**, verified across the whole
install on 2026-09-14.

No post carries `_rl_conversion_status` either, so nothing passed through the WR-101 review queue.
[cutover-decisions.md §11](cutover-decisions.md) records the explicit decision that the import path
made that pass unnecessary. The queue stays the route for anything converted from Elementor later.

**WR-100 is therefore closed by a different path than it specified, and the ADR-0005 gate is green.**
The remaining launch blockers are not content: webhook signing secrets, the Stripe success URL and
credentials, staging performance numbers, and production infrastructure. See
[production-cutover.md](production-cutover.md).
