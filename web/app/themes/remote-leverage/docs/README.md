# Documentation

All project documentation lives here. Start with the [repository README](../../../../../README.md) for the overview and cutover status.

## Reference — how the system works

| Document | Covers |
| :--- | :--- |
| [architecture.md](architecture.md) | Boot order, service providers, the two request paths, events, schema |
| [design-system.md](design-system.md) | Tokens, the 45 blocks, the patterns, templates, `BlockDefaults` |
| [block-inventory.md](block-inventory.md) | **Generated.** Every block mapped to the production section it renders, with fields and usages. Read before building a section; regenerate with `wp acorn blocks:inventory` |
| [admin-screens.md](admin-screens.md) | Every WP Admin surface the theme adds, and where each is registered |
| [configuration.md](configuration.md) | Environment variables, verified against the code that reads them |
| [local-development.md](local-development.md) | Setup, Docker, WP-CLI inventory, running tests |
| [deployment.md](deployment.md) | Image build, CI, ECS deploy, post-deploy tasks |

## Domains

| Document | Bounded context |
| :--- | :--- |
| [domains/lead.md](domains/lead.md) | Lead capture, attribution, dual-write audit log, retention |
| [domains/scheduling.md](domains/scheduling.md) | Calendly, Google Meet, revenue-tier routing, instant live calls |
| [domains/tracking.md](domains/tracking.md) | PostHog + Customer.io dual dispatch, feature flags |
| [domains/referral.md](domains/referral.md) | Referrer portal, click attribution, Stripe Connect payouts |
| [domains/partner-hub.md](domains/partner-hub.md) | `rl_partner` CPT, co-branded hubs, Notion-synced directory |
| [domains/content-audit.md](domains/content-audit.md) | Elementor audit/conversion, blog import, email signatures |
| [domains/sync.md](domains/sync.md) | Environment-to-environment dataset transfer |

## Migration & launch

| Document | Covers |
| :--- | :--- |
| [production-cutover.md](production-cutover.md) | What stands between v2 and the DNS flip, and in what order |
| [content-migration-checklist.md](content-migration-checklist.md) | Per-page inventory of everything live on production (audited 2026-09-10) |
| [content-migration-next-phase-plan.md](content-migration-next-phase-plan.md) | The four blocking decisions, and the work that needs no sign-off |
| [page-migration-and-design-system-workflow.md](page-migration-and-design-system-workflow.md) | How to migrate a page: extract, dissect, build blocks, publish |
| [adr-status.md](adr-status.md) | ADR compliance and WR-98–106 backlog status (verified 2026-09-09) |
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

Architecture decisions live outside this tree, in [`doc/adr/`](../../../../../doc/adr/) at the repository root (ADR-0001 through ADR-0008).
