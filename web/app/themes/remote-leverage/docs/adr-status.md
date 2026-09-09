# ADR Compliance & Launch Readiness

Verified against current codebase state, 2026-09-09. Checked by reading the actual files, not by trusting `docs/jira-adr-compliance-backlog.md`'s prior claims.

## Note: ADR-0008 formally accepted (2026-09-09)

**ADR-0008** (Lead domain, retire Gravity Forms) is now `Status: Accepted`. The transport question it left open is resolved as **synchronous subscriber** (matches the implementation — no listener uses `ShouldQueue`), consistent with WR-106 being on hold (no queue worker deployed).

## Backlog tickets (WR-98–106) — verified status

| Ticket | Title | Status | Evidence |
|---|---|---|---|
| WR-98 | LeadAbandoned lifecycle event processor | **Done** (2026-09-09) | `ProcessAbandonedLeadsAction` + `lead:process-abandoned {--hours=2}` command, hourly WP-Cron hook registered in `LeadServiceProvider::boot()`, dual dispatch logging to `LeadActivityLog`, Pest coverage added |
| WR-99 | Centralize GTM, LinkedIn Insight, Meta Pixel | **Config, not code** (rescoped 2026-09-09) | Google Site Kit is installed (`composer.json: wp-plugin/google-site-kit`) and ships a `Tag_Manager` module (`web/app/plugins/google-site-kit/includes/Modules/Tag_Manager.php`) that self-injects the GTM `<head>` snippet + `wp_body_open` noscript once a container is connected in the Site Kit admin UI. LinkedIn Insight and Meta Pixel will be added as tags *inside* that GTM container, not as custom TrackingHooks code. Remaining work is admin configuration (connect container, add tags in GTM), not engineering — dropping the custom PHP injection plan entirely. |
| WR-100 | Production Elementor content ingestion & batch conversion | **Partial** (CLI done 2026-09-09, ingestion still blocked) | `ApplyElementorConversionAction` + `content:convert-elementor {--dry-run} {--post_id=}` command now exist and persist converted markup + `_rl_conversion_status=needs_review` to real posts. Still blocked on the actual production DB dump ingestion (Blocker-priority per ADR-0005's amendment) — that step is ops, not engineering, and wasn't run here. |
| WR-101 | Editorial review queue for AI-converted posts | **Done** (2026-09-09) | New `ContentAuditAdmin` class: "Conversion Status" column + badges on Posts/Pages list, status filter dropdown, "Approve & Mark Clean" row action (sets `_rl_conversion_status=approved`, archives `_elementor_data` → `_elementor_data_archived`). Registered via `DomainServiceProvider`. Pest coverage for the underlying action. |
| WR-102 | WP Admin configurable form & routing settings | **Done** (2026-09-09) | New `LeadSettingsService` (`rl_lead_settings` option, 30-day retention floor enforced, email validation) + "Leads → Settings" admin screen (notification emails, optional-field toggles, retention days, HubSpot/Slack/webhook overrides). Wired to real consumers: `HubSpotGateway` now prefers the configured token, new `HandleLeadEventsForEmailNotification` listener emails configured recipients on `LeadCreated`. Field-visibility toggles are exposed via `isFieldEnabled()`, but not yet wired into the booking wizard blade — that form doesn't currently render `company`/`notes` inputs at all, so there's nothing to toggle yet. Pest coverage added. |
| WR-103 | Legacy URL 301 redirects & visual regression suite | **Partial** (redirects done 2026-09-09, visual regression not started) | New `config/redirects.php` map + `LegacyRedirectMiddleware`, wired via `template_redirect` in `app/setup.php` (WP pages aren't routed through Acorn's HTTP kernel, so this follows the same hook pattern as the existing HTTPS redirect). Preserves query string/UTM params. Pest coverage added. Visual regression suite intentionally not built — no Playwright/tooling installed, and it needs real staging/production URLs this environment doesn't have; building a non-functional stub would be worse than leaving it explicit. |
| WR-104 | Real-time availability polling for InstantLiveCallButton | **On hold** (deprioritized 2026-09-09) | Component calls `LiveCallAvailabilityRouter` on mount, but the router just reads a cache flag — no real calendar integration, no live polling. Not being worked for now. |
| WR-105 | GitHub Actions CI (Pint, Pest, asset build) | **On hold** (deprioritized 2026-09-09) | No `.github/workflows` directory anywhere in the repo. Not being worked for now. |
| WR-106 | Production queue worker provisioning & monitoring | **On hold** (deprioritized 2026-09-09) | No Supervisor/systemd config for `wp acorn queue:work`. Not being worked for now. |

## Already done

- **WR-93 — Gutenberg Block/Pattern Library (parent)**: all 5 subtasks (WR-109–113: editor CSS parity, block previews, pattern library, landing page patterns, cross-browser QA) — consistent with recent commits.
- **Lead domain core (ADR-0008 baseline)**: Model, LeadActivityLog, CaptureLeadAction, PurgeOldLeadsAction + PurgeLeadsCommand, ProcessAbandonedLeadsAction + ProcessAbandonedLeadsCommand (WR-98), all 4 lifecycle events, LeadFormSubmitted extension point, HubSpotGateway, PhoneValidationService, Slack/webhook listeners — all wired via LeadServiceProvider.

## Biggest gap for launch

WR-100's remaining piece — ingesting the production DB dump and running `content:convert-elementor` against real content — is the last blocker on the Elementor retirement completion gate ADR-0005 treats as mandatory before cutover (zero posts may carry `_elementor_data`). The conversion + review-queue engineering is now in place (WR-100 CLI, WR-101); what's left is an ops step (get the prod dump onto staging) plus the actual human editorial review pass once real content is converted.
