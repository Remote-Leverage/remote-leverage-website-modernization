# Documentation

All project documentation lives here. Start with the [repository README](../../../../../README.md) for the overview and cutover status.

## Reference — how the system works

| Document | Covers |
| :--- | :--- |
| [architecture.md](architecture.md) | Boot order, service providers, the two request paths, events, schema |
| [design-system.md](design-system.md) | Tokens, the 56 blocks, the patterns, templates, `BlockDefaults` |
| [block-inventory.md](block-inventory.md) | **Generated.** Every block mapped to the production section it renders, with fields and usages. Read before building a section; regenerate with `wp acorn blocks:inventory` |
| [admin-screens.md](admin-screens.md) | Every WP Admin surface the theme adds, and where each is registered |
| [configuration.md](configuration.md) | Environment variables, verified against the code that reads them |
| [local-development.md](local-development.md) | Setup, Docker, WP-CLI inventory, running tests |
| [deployment.md](deployment.md) | Image build, CI, ECS deploy, post-deploy tasks |
| [observability.md](observability.md) | The integration call log, credential fingerprinting, the merged lead timeline, and Sentry noise control |
| [slack-app.md](slack-app.md) | The one Slack app: what it posts, the thread-per-lead model, wiring the action buttons, and the scopes it does not have |

## Domains

| Document | Bounded context |
| :--- | :--- |
| [domains/lead.md](domains/lead.md) | Lead capture, attribution, dual-write audit log, retention |
| [domains/scheduling.md](domains/scheduling.md) | Calendly, Google Meet, revenue-tier routing, instant live calls |
| [domains/tracking.md](domains/tracking.md) | PostHog + Customer.io dual dispatch, feature flags |
| [domains/referral.md](domains/referral.md) | Referrer portal, click attribution, Stripe Connect payouts |
| [domains/partner-hub.md](domains/partner-hub.md) | `rl_partner` CPT, co-branded hubs, CPT-backed partner directory |
| [domains/content-audit.md](domains/content-audit.md) | Elementor audit/conversion, blog import, Yoast meta import, block inventory |
| [domains/sync.md](domains/sync.md) | Environment-to-environment dataset transfer |
| [stripe-payments.md](stripe-payments.md) | `app/Domains/Payment` — Stripe deposit/checkout gateway, funnel telemetry |
| [social-media-kit.md](social-media-kit.md) | `/social-media-kit/` route — email-signature generator + brand asset library (ported `rl-social-kit`); the sole home of signature generation since 2026-09-15 |

## Migration & launch

| Document | Covers |
| :--- | :--- |
| [production-cutover.md](production-cutover.md) | What stands between v2 and the DNS flip, and in what order |
| [content-migration-checklist.md](content-migration-checklist.md) | Per-page inventory of everything live on production (audited 2026-09-10) |
| ~~content-migration-next-phase-plan.md~~ | **Archived 2026-09-15** — all four blocking decisions resolved. Moved to [archive/](archive/content-migration-next-phase-plan.md) |
| [cutover-decisions.md](cutover-decisions.md) | **Every decision that was blocking the cutover, and what was decided (2026-09-15).** Where it contradicts another doc, it wins and the other is stale |
| [seo-meta-migration.md](seo-meta-migration.md) | Yoast install, and `content:import-seo` — carrying production's `_yoast_wpseo_*` meta onto v2 |
| [page-migration-and-design-system-workflow.md](page-migration-and-design-system-workflow.md) | How to migrate a page: extract, dissect, build blocks, publish |
| [performance-baseline.md](performance-baseline.md) | The Phase 8 performance gate: mobile Lighthouse, the `CaptureLeadAction` p95 rule, and Parts 3–5 covering the font subsetting, preload, Calendly preflight and blog image work of 2026-09-15 |
| [mobile-parity-audit.md](mobile-parity-audit.md) | The 390px sweep of all 54 URLs against production (2026-09-15): the fixed-header occlusion that hit 10 pages, the 1,644px mobile footer, the invisible header on `/ecommerce-virtual-assistant/`, and the four things that look like defects but are not |
| [adr-status.md](adr-status.md) | ADR compliance and WR-98–106 backlog status (2026-09-09; WR-100, WR-105, WR-106 and the ADR-0008 transport note re-verified 2026-09-15) |
| [jira-adr-compliance-backlog.md](jira-adr-compliance-backlog.md) | The original backlog write-up |

## Runbooks & design notes

| Document | Covers |
| :--- | :--- |
| [environment-sync.md](environment-sync.md) | Design of the bi-directional sync, including its four production gates |
| [ai-mcp-and-sync.md](ai-mcp-and-sync.md) | One-time setup: MCP users, application passwords, client config |
| [building-forms-with-livewire.md](building-forms-with-livewire.md) | Building a form the way this codebase does it |
| [qa-attribution-webhooks.md](qa-attribution-webhooks.md) | Manual QA runbook for attribution and webhook integrations |
| [partner-hub-gap-analysis.md](partner-hub-gap-analysis.md) | Legacy plugin vs. ported hub (gap closed 2026-09-09; kept for reference) |

## Problems

| Document | Covers |
| :--- | :--- |
| [known-issues.md](known-issues.md) | Live bugs, dead configuration, and documentation that contradicts the code |

## Archive

Superseded documents, kept for rationale. **They describe intent, not the codebase.**

| Document | Was |
| :--- | :--- |
| [archive/architecture-proposal.md](archive/architecture-proposal.md) | The original repository README — an architecture proposal |
| [archive/theme-readme-proposal.md](archive/theme-readme-proposal.md) | The original theme README — aspirational feature documentation |
| [archive/plan-root.md](archive/plan-root.md) | The repository phase plan — a hand-maintained percentage tracker that drifted; archived 2026-09-15 |
| [archive/plan-theme.md](archive/plan-theme.md) | The theme's near-duplicate of the same plan; archived 2026-09-15 |
| [archive/content-migration-next-phase-plan.md](archive/content-migration-next-phase-plan.md) | The next-phase migration plan; archived 2026-09-15 once its four blocking decisions were resolved |

Architecture decisions live outside this tree, in [`doc/adr/`](../../../../../doc/adr/) at the repository root (ADR-0001 through ADR-0008).
