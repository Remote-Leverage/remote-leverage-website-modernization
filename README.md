# Remote Leverage v2

A ground-up rebuild of [remoteleverage.com](https://remoteleverage.com) on **Roots Bedrock + Sage + Acorn**, replacing an Elementor site and eight bespoke plugins with one version-controlled WordPress application.

This repository *is* the new platform. It is not live yet — it runs locally and on staging while content is migrated off production. See [Replacing production](#replacing-production) for exactly what stands between here and the DNS flip.

> **Status of this document.** Verified against the code and the local database on **2026-09-14**. Production-side counts (233 pages, 119 posts, 2 partners) come from the audit in [`docs/content-migration-checklist.md`](web/app/themes/remote-leverage/docs/content-migration-checklist.md) (2026-09-10); everything describing *local* state was re-queried directly.

---

## Table of contents

- [What this is](#what-this-is)
- [Stack](#stack)
- [Repository layout](#repository-layout)
- [Quick start](#quick-start)
- [How it fits together](#how-it-fits-together)
- [The seven domains](#the-seven-domains)
- [Replacing production](#replacing-production)
- [Documentation index](#documentation-index)
- [Known issues](#known-issues)

---

## What this is

Production today is `hello-elementor` + Elementor Pro + eight in-house plugins (`rl-elementor-blocks`, `rl-referral-program`, `rl-customer-io`, `rl-partners-hub`, `rl-join-live-call`, `rl-posthog-feature-flags`, `rl-social-kit`, `rl-content-auditor`). Business logic lives in procedural hooks, each plugin talks to its own tables, and layout changes require the page builder.

v2 folds all of it into a single Sage theme with an Acorn (Laravel) container inside it:

- **Business logic** lives in seven bounded contexts under `app/Domains/`, each with its own actions, DTOs, events, models and gateways.
- **Content** is native Gutenberg — 38 code-first ACF blocks with Blade views, composed into 53 registered block patterns.
- **Interactive funnels** (booking wizard, live-call button, referrer portal, partner directory) are Livewire 4 components, not iframes or shortcodes.
- **Schema** is version-controlled Acorn migrations, applied automatically on deploy.
- **Everything is tested** — 422 Pest tests, 1368 assertions, green as of this writing, gated in CI on every PR.

## Stack

| Layer | Technology | Version | Notes |
| :--- | :--- | :--- | :--- |
| WordPress stack | Roots Bedrock | — | 12-factor layout, `.env` config, Composer-managed core |
| WordPress core | `roots/wordpress` | 7.1 | Abilities API (6.9+) is relied on by the sync/AI features |
| Theme | Roots Sage | 10.x | `web/app/themes/remote-leverage` |
| App container | Roots Acorn | ^6.0 | Laravel service container, Eloquent, events, Artisan inside WP |
| Reactive UI | Livewire | ^4.4 | 7 components |
| Blocks | Log1x ACF Composer | ^3.4 | 38 code-first blocks |
| Fields | ACF Pro | * | Licensed; `ACF_PRO_KEY` required to `composer install` |
| Styling | Tailwind CSS | ^4.0 | `@theme` tokens in `resources/css/app.css` |
| Build | Vite | ^8.0 | `@roots/vite-plugin` |
| Tests | Pest | ^5.1 | 422 tests |
| Lint | Laravel Pint | ^1.20 | `pint.json` at repo root |
| AI / MCP | `roots/acorn-ai`, `WordPress/mcp-adapter` | ^0.1.2 / ^0.6.1 | Abilities API–backed |
| Monitoring | Sentry Laravel | ^4.27 | Config present; **DSN not set in `.env`** |
| Phone parsing | libphonenumber-for-php | ^9.0 | E.164 normalisation |

PHP **8.3+** (CI runs 8.4), Node **20.19+ / 22.12+**.

## Repository layout

```
remoteleverage-v2/
├── .github/workflows/          ci.yml (Pint + Pest + Vite build), deploy-staging.yml (ECR → ECS)
├── config/                     Bedrock environment config
├── doc/adr/                    ADR 0001–0008 — the decisions this build rests on
├── docker/                     entrypoint.sh, nginx.conf, php.ini, www.conf
├── scripts/                    seed-staging-secrets.sh (local .env → AWS Secrets Manager)
├── Dockerfile                  PHP-FPM + nginx image used by staging and docker-compose
├── docker-compose.yml          Local preview of the staging image (MySQL + Redis + app)
├── PAGE-MIGRATION-STATUS.md    Source of truth for migration scope and per-URL state
└── web/app/themes/remote-leverage/
    ├── app/                    240 PHP files — the application (see below)
    ├── config/                 services, redirects, post-types, rl-sync, ai, sentry
    ├── docs/                   ← all project documentation lives here
    ├── patterns/               53 Gutenberg block patterns
    ├── resources/              css, js, views (Blade), fonts, images
    ├── routes/                 web.php, api.php (Acorn routes, not WP rewrites)
    └── tests/                  Pest suite (Unit + Feature)
```

Inside the theme's `app/`:

```
app/
├── Ai/                 MCP-callable landing-page abilities + LandingPageComposer
├── Application/        Livewire components, HTTP controllers, middleware
├── Blocks/             38 ACF Composer block definitions
├── Domains/            ← business logic: 7 bounded contexts
├── Fields/             ACF field groups (PartnerHubFields)
├── Infrastructure/     Providers, migrations, console commands, WP admin & hooks
├── Support/            BlockDefaults, MediaLibrary, DocumentOutline, Pattern, ReadingTime
└── View/               Blade composers, nav walkers
```

## Quick start

```bash
# 1. Dependencies (ACF Pro is licensed — you need ACF_PRO_KEY in auth.json)
composer install
cd web/app/themes/remote-leverage && composer install && npm install

# 2. Environment
cp .env.example .env      # at the repo root; fill DB_*, WP_HOME, salts, APP_KEY, ACF_PRO_KEY

# 3. Schema
wp acorn migrate

# 4. Dev server (from the theme directory)
npm run dev               # Vite + HMR
npm run build             # production assets
```

Or run the staging image locally:

```bash
docker compose --env-file env up --build   # http://127.0.0.1:8080
```

Full detail, including the WP-CLI command inventory: [`docs/local-development.md`](web/app/themes/remote-leverage/docs/local-development.md).

## How it fits together

Acorn boots inside WordPress, so there are two request paths and they behave differently — this trips people up more than anything else in the codebase.

```mermaid
flowchart TB
    subgraph Requests
        A["WordPress page request<br/>(/, /vapricing/, /case-study/…)"]
        B["Acorn route<br/>(routes/web.php, routes/api.php)"]
    end

    A --> C["Blade template<br/>resources/views/*.blade.php"]
    C --> D["ACF blocks<br/>app/Blocks/*Block.php"]
    D --> E["Livewire components<br/>app/Application/Livewire/*"]
    B --> E
    B --> F["Webhook controllers<br/>Stripe · Calendly"]

    E --> G["Domain actions<br/>app/Domains/*/Actions"]
    F --> G
    G --> H["Events<br/>LeadCreated, LeadBookingCompleted, …"]
    H --> I["Listeners<br/>Tracking · Scheduling · Referral · Slack · Webhook · Email"]
    G --> J["Gateways<br/>Calendly · Google · HubSpot · Stripe · PostHog · Customer.io · Notion"]
    G --> K["Eloquent models<br/>rl_leads, rl_referrers, …"]

    L["WP Admin screens<br/>Leads · Referrers · Calendly · Environment Sync"] --> G
    M["WP-CLI / wp acorn"] --> G
```

Two things worth knowing up front:

1. **WordPress pages do not pass through Acorn's HTTP kernel.** Anything that needs to run on a normal page load (the legacy 301 map, HTTPS redirect) hooks `template_redirect` in `app/setup.php` rather than being registered as Laravel middleware.
2. **Event listeners are synchronous.** No listener implements `ShouldQueue` and no queue worker is deployed — a deliberate decision recorded in ADR-0008 and [`docs/adr-status.md`](web/app/themes/remote-leverage/docs/adr-status.md). A slow HubSpot or Slack call is inside the user's request.

Deeper: [`docs/architecture.md`](web/app/themes/remote-leverage/docs/architecture.md).

## The seven domains

Each has its own document with the classes, the data it owns, the wiring and how to exercise it.

| Domain | Replaces | Owns | Doc |
| :--- | :--- | :--- | :--- |
| **Lead** | Gravity Forms + `GF_HubSpot` | `rl_leads`, `rl_lead_activity_logs`; capture, validation, attribution stamping, dual-write audit log, retention purge | [lead.md](web/app/themes/remote-leverage/docs/domains/lead.md) |
| **Scheduling** | Calendly embeds, `rl-join-live-call` | Calendly token pool + event-type routing, Google Meet creation, booking retry, `rl_live_call_sessions` | [scheduling.md](web/app/themes/remote-leverage/docs/domains/scheduling.md) |
| **Tracking** | `rl-customer-io`, `rl-posthog-feature-flags` | Dual dispatch to PostHog + Customer.io, server-side flag evaluation, script injection | [tracking.md](web/app/themes/remote-leverage/docs/domains/tracking.md) |
| **Referral** | `rl-referral-program` | `rl_referrers`, `rl_referrals`, `rl_referral_clicks`, `rl_referral_rewards`, `rl_payouts`; attribution cookie, Stripe Connect payouts | [referral.md](web/app/themes/remote-leverage/docs/domains/referral.md) |
| **PartnerHub** | `rl-partners-hub` | `rl_partner` CPT, 9-tab co-branded hubs, Notion-synced directory | [partner-hub.md](web/app/themes/remote-leverage/docs/domains/partner-hub.md) |
| **ContentAudit** | `rl-content-auditor`, `rl-social-kit` | Elementor AST audit/conversion, blog import, email-signature HTML | [content-audit.md](web/app/themes/remote-leverage/docs/domains/content-audit.md) |
| **Sync** | *(new)* | Environment-to-environment dataset transfer over the Abilities REST API | [sync.md](web/app/themes/remote-leverage/docs/domains/sync.md) |

`rl-elementor-blocks` is replaced by the block/pattern library rather than by a domain — see [`docs/design-system.md`](web/app/themes/remote-leverage/docs/design-system.md).

---

## Replacing production

The engineering is largely done. **The cutover is blocked on content, not on code.**

> **Migration scope was closed on 2026-09-14.** A 44-URL transfer list, plus `/hire-va-4/` and
> the 4 partner-hub URLs, is the **entire** migration — 48 URLs. The other 191 published pages on
> production are **discarded**: not deferred, not "phase 2". They need a redirect-or-drop decision
> at cutover, not a migration plan. Source of truth:
> [`PAGE-MIGRATION-STATUS.md`](PAGE-MIGRATION-STATUS.md).

```mermaid
flowchart LR
    subgraph PROD["Production today — remoteleverage.com"]
        P1["Elementor + hello-elementor"]
        P2["8 bespoke plugins"]
        P3["235 pages · 119 posts · 2 partners"]
        P4["Yoast SEO Premium"]
    end

    subgraph V2["v2 — this repo"]
        direction TB
        V1["✅ Platform<br/>Bedrock · Sage · Acorn · 422 tests green"]
        V2b["✅ 7 domains<br/>all 8 plugins ported"]
        V3["✅ Design system<br/>38 blocks · 53 patterns"]
        V4["✅ CI + staging deploy<br/>GitHub Actions → ECR → ECS"]
        V5["🟡 Content<br/>14 of 48 in-scope URLs<br/>119 posts + 23 case studies done"]
        V6["🟡 SEO parity<br/>Yoast 28.5 installed (inactive)<br/>164 discarded URLs redirected<br/>meta import built, not yet run"]
        V7["🔴 Cutover ops<br/>owned elsewhere<br/>perf measured locally, not on staging"]
    end

    PROD -.->|"must carry over"| V2
    V2 ==>|"blocked by 🟡 + 🔴"| GO["DNS flip"]

    style V1 fill:#d1fae5,stroke:#059669,color:#064e3b
    style V2b fill:#d1fae5,stroke:#059669,color:#064e3b
    style V3 fill:#d1fae5,stroke:#059669,color:#064e3b
    style V4 fill:#d1fae5,stroke:#059669,color:#064e3b
    style V5 fill:#fef3c7,stroke:#d97706,color:#78350f
    style V6 fill:#fef3c7,stroke:#d97706,color:#78350f
    style V7 fill:#fee2e2,stroke:#dc2626,color:#7f1d1d
    style GO fill:#e0e7ff,stroke:#4f46e5,color:#312e81
```

### Where each workstream stands

| Workstream | State | What remains |
| :--- | :--- | :--- |
| Platform & infrastructure | ✅ Done | — |
| Domain logic (8 plugins → 7 contexts) | ✅ Done | Real-time live-call availability is a cache flag, not a calendar (WR-104, on hold) |
| Block & pattern library | ✅ Done | Case-study sub-nav tab bar built 2026-09-15 (0.00% pixel diff vs production at 1440px and 400px) |
| Test suite + CI | ✅ Done | No visual-regression suite (WR-103, deliberately not built) |
| Staging deploy pipeline | ✅ Done | Production target does not exist yet |
| Environment sync | ✅ Done | Production is gated off by design, in four independent places |
| **In-scope URLs** | 🟡 30 of 48 | P0 and P2 cleared; see [`PAGE-MIGRATION-STATUS.md`](PAGE-MIGRATION-STATUS.md) for the live count |
| **Case studies** | ✅ 23 of 23 | Migrated to a real `case_study` CPT |
| **Blog posts** | ✅ 119 of 119 | Imported clean; category counts match production |
| **Taxonomies** | ✅ 7 of 7 categories, 8 of 8 tags | `Live Sessions` is empty locally (2 on production) |
| **Media** | ✅ Out of scope | **Production's media library is not being ported** (decided 2026-09-15). No reconciliation against production is planned; local art is sourced from `resources/images/pages/` and built by the `themeImages()` Vite plugin |
| **Partners** | ✅ 3 of 3 | Rebuilt CPT-backed 2026-09-15; content seeded from `resources/partners/partners.php` (in git). Three gaps remain that only the partners can fill: intake forms for Lexgo and Lano, a referral destination for Oyster, logos for all three |
| **SEO parity** | 🟡 Mechanism done, not yet run | Done: `robots.txt`, real 404s, non-production `noindex`; **Yoast 28.5 installed** (inactive — activating opens the config wizard); **`wp acorn content:import-seo`** built, dry-run matches 187 items by slug; **redirect map now 170 entries** covering the 164 discarded URLs. Remaining: activate Yoast, settle `/guides/` vs `/blog/` canonical, run the import for real |
| **Elementor retirement gate** | 🟡 Partially met | Zero local posts carry `_elementor_data` — but nothing has `_rl_conversion_status` either, so the ADR-0005 human sign-off gate has no record |
| **Performance baseline** | 🟡 Targets partly met | [performance-baseline.md](web/app/themes/remote-leverage/docs/performance-baseline.md). After the 2026-09-15 font and image work: `/`, `/case-study/`, `/blog/` and blog posts all score **99–100** mobile (from 60–93), and the heaviest post fell **3.27MB → 0.59MB**. **LCP < 1.2s still passes on nothing** — best is 1.54s. Staging numbers, which is what the gate actually asks for, not yet taken |
| **Production cutover** | 🔴 Not started | **Owned elsewhere** (2026-09-15) — the production deploy pipeline, DNS runbook and rollback plan are being handled outside this workstream |

### What actually remains

The three business decisions that used to block ~107 pages (paid-traffic experiments, operational
funnel pages, the role/industry template question) are **resolved by scope closure**: none of those
sets is being migrated. What is left is concrete build work plus one small decision batch.

**18 in-scope URLs to build**, in priority order:

1. **P0 — 5 pages the v2 nav already links to**, so the site currently points at its own 404s:
   `/vacalendar/`, `/samples/`, `/contractor-management/`, `/contractor-payments/`, `/impact-report-2026/`.
2. **P1 — 5 partnership items**: the `/remote-leverage-x-oyster/` and `/remote-leverage-x-lano/`
   landing pages, plus `oyster` / `lano` / `lexgo` hub entries. Note the asymmetry — Lano has no
   production hub entry and Lexgo no production landing page, so two of the five are new content,
   not migrations.
3. ~~**P2 — 6 funnel/operational pages**~~ — **built 2026-09-15.** `/payment/`, `/signedup/`,
   `/vaonboardingform/`, `/referral-program/`, `/referral-program-thank-you-page-deposit/` and
   `/virtual-assistant-hiring-manager-refundable-deposit/`. The last of those is visually
   complete but **cannot take a payment until Stripe credentials are moved** from
   `rl-elementor-blocks` — see [`docs/stripe-payments.md`](web/app/themes/remote-leverage/docs/stripe-payments.md).
4. **P3–P4 — 18 marketing and campaign pages.**

**Plus one decision batch:** v2 already contains 4 pages that are *not* on the list, built before
scope closed — `/vapricing/`, `/affiliate-program/`, `/referral/`, `/comparison-wing-assistant-ads/`.
Each needs a keep/redirect/delete call. `/referral/` was the urgent one and is **resolved
(2026-09-15): deleted.** It had been migrated from the wrong source and rendered the `hire-va-4`
landing page, when production's `/referral/` is actually a homepage variant (verified: 27 of 27
headings match the homepage, 4 of 27 match `/hire-va-4/`). The other three are still open.

Per-URL status, the out-of-scope inventory and the open decisions: [`PAGE-MIGRATION-STATUS.md`](PAGE-MIGRATION-STATUS.md).

Full plan, per-page inventory and sequencing: [`docs/production-cutover.md`](web/app/themes/remote-leverage/docs/production-cutover.md).

---

## Documentation index

Everything lives under [`web/app/themes/remote-leverage/docs/`](web/app/themes/remote-leverage/docs/) — start at its [README](web/app/themes/remote-leverage/docs/README.md).

| Area | Document |
| :--- | :--- |
| Application architecture, boot order, request paths | [architecture.md](web/app/themes/remote-leverage/docs/architecture.md) |
| Local setup, CLI inventory, testing | [local-development.md](web/app/themes/remote-leverage/docs/local-development.md) |
| Docker image, CI, ECS deploy, post-deploy tasks | [deployment.md](web/app/themes/remote-leverage/docs/deployment.md) |
| Verified environment-variable reference | [configuration.md](web/app/themes/remote-leverage/docs/configuration.md) |
| Tokens, blocks, patterns, templates | [design-system.md](web/app/themes/remote-leverage/docs/design-system.md) |
| WP Admin surfaces this theme adds | [admin-screens.md](web/app/themes/remote-leverage/docs/admin-screens.md) |
| **Migration scope & per-URL status** | [**PAGE-MIGRATION-STATUS.md**](PAGE-MIGRATION-STATUS.md) |
| Cutover plan & content inventory | [production-cutover.md](web/app/themes/remote-leverage/docs/production-cutover.md) |
| Domain guides (7) | [domains/](web/app/themes/remote-leverage/docs/domains/) |
| Bugs, dead config, stale docs | [known-issues.md](web/app/themes/remote-leverage/docs/known-issues.md) |
| Architecture decisions | [`doc/adr/`](doc/adr/) 0001–0008 |

## Known issues

A short list of things that are wrong right now and are worth knowing before you touch the code. Detail and proposed fixes in [`docs/known-issues.md`](web/app/themes/remote-leverage/docs/known-issues.md).

- **Both webhook endpoints now fail closed, and neither secret is set.** As of 2026-09-15 `/api/webhooks/stripe` and `/api/webhooks/calendly` return **503** with no signing secret configured, rather than processing unverified payloads. `STRIPE_WEBHOOK_SECRET` and `CALENDLY_WEBHOOK_SIGNING_KEY` are empty in every environment, so **both endpoints are dark until someone sets them** — Stripe Connect payout events included. Set them before this reaches staging.
- **Sentry is configured but has no DSN**, so nothing is reported.
- **`/referral/` was deleted (2026-09-15).** It held the wrong content — the `hire-va-4` landing page, where production's `/referral/` is a homepage variant. Out of scope, so the call was delete, not rework. Page 213 is trashed and recoverable; nothing linked to it. It still needs `'referral' => ''` in `config/redirects.php` so the live production URL does not 404 at cutover.
- **`/robots.txt` and `/favicon.ico` report 404 locally** while serving correct content. A Herd/nginx artifact affecting only those two filenames; production returns 200. Do not chase it.

**Fixed on 2026-09-14** (kept here briefly because they changed behaviour you may remember differently):

- ~~`/partner-dashboard` throws~~ — the route, the redirect map and both partner-directory links now point at the real `referrer.portal` / `referrer.register`.
- ~~Every unknown URL served the homepage with a 200~~ — caused by WordPress's verbose page rules silently emptying the query. Unresolved paths now return a real 404.
- ~~Every environment forced itself to be indexable~~ — the theme override was removed entirely, so `DISALLOW_INDEXING` works again and non-production emits `noindex`.
- ~~`PAGE-MIGRATION-STATUS.md` contradicts the migration checklist~~ — it was rewritten against the live database and is now the source of truth for migration scope; the checklist was demoted to a read-only production inventory.

---

Maintained by the Remote Leverage engineering team. Built on Roots Sage (MIT); domain logic and brand assets are proprietary.
