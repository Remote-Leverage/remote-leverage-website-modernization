# Replacing production

What stands between this codebase and `remoteleverage.com`, and in what order to clear it.

Production-side figures come from the audit in [content-migration-checklist.md](content-migration-checklist.md) (2026-09-10). **Local figures were re-queried directly from the database on 2026-09-14** and supersede the older numbers in [archive/content-migration-next-phase-plan.md](archive/content-migration-next-phase-plan.md), which predate the blog import and was archived on 2026-09-15.

---

## The short version

> **⚠️ Parts of this document predate scope closure (2026-09-14) and the 2026-09-15 work.**
> The per-page counts below and "The three decisions" section were written when the plan was to
> migrate all 235 production pages. **That is no longer the plan.** Scope is a closed 48-URL list;
> the other 164 published pages are discarded and now carry 301s in `config/redirects.php`.
> For per-URL state read [`PAGE-MIGRATION-STATUS.md`](../../../../../PAGE-MIGRATION-STATUS.md);
> read this file for the **cutover gates**, which are current.

The platform is done. Posts, case studies, taxonomies and partners are done. Migration scope is
closed at 48 URLs and tracked per-URL elsewhere.

Production infrastructure — the ECS service, the deploy pipeline, the DNS runbook and the rollback
plan — is **owned outside this workstream** as of 2026-09-15. It remains a hard gate on the flip;
it is simply not this document's work item.

The media library is **not being ported** (2026-09-15). Page art is tracked as source in
`resources/images/pages/` and generated at build time, so nothing about the cutover depends on
reconciling production's library.

## Where each workstream stands

```mermaid
flowchart TB
    subgraph DONE["✅ Complete"]
        A1["Platform — Bedrock · Sage · Acorn"]
        A2["8 domains — all 8 legacy plugins ported"]
        A3["56 blocks · 102 patterns"]
        A4["774 tests · CI on every PR"]
        A5["Staging deploy — ECR → ECS"]
        A6["Environment sync"]
        A7["23 of 23 case studies"]
        A8["119 of 119 posts · 7 categories · 8 tags"]
        A9["48-URL migration scope — 54 of 54 built"]
    end

    subgraph PART["🟡 Partial"]
        B3["ADR-0005 gate — no _elementor_data,<br/>but no review record either"]
        B5["Performance — mobile 99-100 and CLS met;<br/>LCP &lt; 1.2s passes on nothing"]
        B4["SEO parity — meta imported (190 items),<br/>media URLs still on production"]
    end

    subgraph BLOCKED["🔴 Not started"]
        C6["Production infra · DNS · rollback<br/>(owned outside this workstream)"]
    end

    DONE --> GATE{"Cutover gates"}
    PART --> GATE
    BLOCKED --> GATE
    GATE --> LAUNCH["DNS flip"]

    style DONE fill:#d1fae5,stroke:#059669,color:#064e3b
    style PART fill:#fef3c7,stroke:#d97706,color:#78350f
    style BLOCKED fill:#fee2e2,stroke:#dc2626,color:#7f1d1d
    style LAUNCH fill:#e0e7ff,stroke:#4f46e5,color:#312e81
```

### Content

Local counts re-queried **2026-09-15**; production counts from the 2026-09-10 audit.

**Read the `page` row against the closed 48-URL scope, not against production's 235.** The
"remaining" column is meaningless for pages: 191 production pages are discarded, not pending.

| Type | Production | Migrated | Remaining |
| :--- | ---: | ---: | ---: |
| `page` (in scope) | 48 in scope of 235 published | 54 built of 54 — 100% | 0 |
| `case_study` (pages on production) | 23 | 23 | 0 |
| `post` | 119 | 119 | 0 |
| `rl_partner` | 2 | 3 (Oyster, Lexgo, Lano) | 0 |
| Categories | 7 | 7 | 0 |
| Tags | 8 | 8 | 0 |
| `attachment` | **not being ported** | 513 | — (see below) |

The page denominator is `48 + 5 + 1` — the closed transfer list, the five non-indexed pages found
in the 2026-09-15 sweep, and one addition. Per-URL state lives in
[`PAGE-MIGRATION-STATUS.md`](../../../../../PAGE-MIGRATION-STATUS.md); **one page still cannot go
live** (Stripe credentials, §P2 there).

~~Migrated pages: `home`, `about-us`, `reviews`, …~~ **This 15-page list is superseded.** All 54 in-scope pages are built; the per-URL list lives in [`PAGE-MIGRATION-STATUS.md`](../../../../../PAGE-MIGRATION-STATUS.md), which is re-queried against the database rather than hand-maintained. *(`referral` (ID 213) was deleted on 2026-09-15 — [cutover-decisions.md §12](cutover-decisions.md).)*

Per-category post counts match production exactly — Business Growth 91, Outsourcing 95, Case Studies 13, Salary Guides 12, Real Estate Posts 9, News 2. The apparent **Live Sessions: 2 on production, 0 locally** discrepancy was **not real** (checked 2026-09-15): both items are `post_type=page`, not posts — production registers `category` on pages too, and a term count is not post-type-scoped. Nothing was dropped. Details in [content-migration-checklist.md](content-migration-checklist.md).

### ~~The 218 remaining pages~~ — superseded 2026-09-14

**This breakdown and "The three decisions" below are kept only for rationale.** Scope closed at
48 URLs on 2026-09-14 and every decision here was answered in
[cutover-decisions.md](cutover-decisions.md); the 191 discarded pages carry 301s in
`config/redirects.php`. Nothing in the next two sections is an open work item.

| Group | Count | State |
| :--- | ---: | :--- |
| §2 Role/industry SEO pages | ~45 | Blocked on decision 3 |
| §4 Noindex operational/funnel pages | ~19 | Blocked on decision 2 |
| §4 Confirmed-dead old homepage variants | ~13 | Just redirect to `/` |
| §5 In-sitemap experiment/ad landing pages | ~43 | Blocked on decision 1 |
| §7 Tools & lead magnets | ~10 | Interactive apps — need their own scoping pass |
| Everything else | remainder | Utility/nav pages needing keep/kill calls |

## ~~The three decisions~~ — all answered 2026-09-15

> Resolved in [cutover-decisions.md](cutover-decisions.md). Retained for the reasoning only.

Each needs a named owner and a yes/no. Each has a recommended default so the owner approves a proposal rather than authoring one.

### 1. Paid-traffic owner — 43 experiment pages (§5) + the funnel half of §4

**Needed:** which URLs currently have live ad spend pointed at them.

**Recommended default:** anything with spend in the last 90 days gets rebuilt; everything else 301s to its nearest live equivalent.

This is the single largest unblocking action available. Rebuilding a dead page wastes a week; killing a live one costs revenue, so nobody can guess it.

### 2. Sales/ops owner — 19 operational pages (§4)

`/payment/`, `/deposit-received/`, `/signedup/`, `/contractoragreement/`, `/onboardingform/`, `/vaonboardingform/`, `/onboardingguide/`, `/vainterview/`, `/vainterview2/`, `/service-cor/`, `/service-hiring/`, `/referral-program/`, `/va-form/`, `/hmchecklists/`, `/recruiterchecklists/`, `/saleschecklists/` and the two consultation-scheduled pages.

**Needed:** which are still in the hiring/onboarding flow, and who the audience is for the three `*checklists` pages — internal staff or clients. That last one decides whether they need authentication, which changes the build.

### 3. §2 architecture — one template or 45 pages?

**Recommendation: one data-driven template.**

The set already has an explicit role/industry/region axis, and the LatAm variants are the same page with a region swap. One Blade template fed by a CPT or ACF options set turns ~45 page builds into one build plus ~45 content records, and makes future role additions free. Building them individually re-creates the exact duplication the audit already found (`marketing-assistants` / `-2` / `-legacy`; `sales-virtual-assistants` / `-2`; `virtual-ecommerce-assistants` vs `ecommerce-virtual-assistants` vs `ecommerce-virtual-assistant`).

Pair it with a canonical-URL pass: pick one page per role, 301 the rest.

### Resolved: §8 blog

All 119 posts are imported, with all 7 categories and 8 tags, and **zero posts carry `_elementor_data`**. Per-category counts match production.

They came in through `wp acorn content:import-posts` (captured production JSON → `ElementorProseExtractor` → Gutenberg), not through the `content:audit-elementor` / `content:convert-elementor` / review-queue pipeline. That matters for the ADR-0005 gate: the *letter* of it is satisfied (no `_elementor_data` anywhere), but no post carries `_rl_conversion_status`, so nothing passed through `ContentAuditAdmin`'s editorial sign-off and there is no record of human review.

**Closed 2026-09-15:** `/blog/` is the canonical post archive, which is what production already does — `/guides/` 301s onto it on both sites, and Yoast is configured accordingly. A local `blog` page exists (ID 799) and carries the `/blog/` canonical. (The **Live Sessions** question is closed too — those two items are pages, not posts; see the content table above.)

## Work that needs no sign-off

Startable today, in parallel with chasing the decisions.

1. ~~**Install and configure Yoast SEO, and carry production's meta.**~~ ✅ **Done 2026-09-15.** Free 28.5 via `wp-plugin/wordpress-seo`, now **active** and configured by `wp option patch update` rather than through the first-time wizard — site representation mirrored off production's `Organization` schema node, social profiles, sitemap on, IndexNow off. `wp acorn content:import-seo` run for real: **190 local items, 863 postmeta rows, from 0**. All three gating decisions are closed — `/blog/` is the canonical archive; `referral-program` and `ecommerce-virtual-assistant` are excluded from production's stale `noindex` via the new `--force-index` option (defaulted, so plain re-runs honour it); Yoast Premium is not being bought, so redirects stay in `config/redirects.php` and the redirect manager is not configured. See [seo-meta-migration.md](seo-meta-migration.md).
2. ~~**Case-study sub-nav tab bar.**~~ ✅ **Done 2026-09-15.** Built as `App\Support\CaseStudySubnav` + `resources/views/partials/case-study-subnav.blade.php`, rendered once from `layouts.app` above `@yield('content')` so no template holds its own copy. TALENT PROFILES points at `/samples/` (page 1000005).

   **Correction to the original entry, which was wrong about placement.** Production does *not* share this bar site-wide with `/reviews/`. Verified 2026-09-15 — the string "TALENT PROFILES" does not appear in the served HTML of `/reviews/` or of `/case-study/` (the archive), and a Playwright probe at 1440px finds no bar on either. Production renders it **only on single `case_study` posts**, as an Elementor library template (element `521f30f0`, template `34011`).

   **Reverted to strict parity 2026-09-15.** v2 briefly also showed the bar on the `case_study` archive and on `/reviews/`, as a deliberate extension beyond production (a tab group whose tabs lead to pages that then drop the bar is broken wayfinding). Those two entries have been removed from `CaseStudySubnav::surfaces()`, which now matches `is_singular('case_study')` and nothing else. `/samples/` was never a surface either, so TALENT PROFILES and REVIEWS both lead to pages that lose the bar — accepted, because that is exactly what production does. Verified by curl: the bar renders on `/case-study/chick-fil-a/` with `aria-current="page"` on CASE STUDIES, and is absent from `/case-study/` and `/reviews/`.

   Measured off `/case-study/chick-fil-a/`: band `#330034` / 60px min-height; tabs Inter 16px/24px, idle `#FFFFFF` weight 300, active `#3DC53D` weight 500 underlined; row with 40px gap, stacking to a 10px-gap column below 768px. Pixel diff against production is 0.00% at 400px, and 0.00% at 1440px after allowing the 22px horizontal offset that comes from v2's case-study container being 1260px+`px-8` where production's is 1240px with no side padding.
3. ~~**`booking-footer` headline regression sweep.**~~ ✅ **Done 2026-09-15 — no page was still broken.** The `headline` ACF field was dead code until the affiliate-program review fixed it; `BookingFooterBlock::with()` now reads `$this->block->data['headline']` before falling back to `get_field()`, which also sidesteps the deep-in-page `get_field()` miss. All fifteen pages that set an override were re-rendered through `do_blocks()` on their real `post_content` and every one emits its own headline: `/hire-va-4/`, `/comparison/`, `/samples/`, `/hire-for-less/`, `/hire-va-1st-month-free/`, `/hire-va-6/` (all six via `hire-va-4-booking-footer`), `/spanish/`, `/affiliate-program/`, `/ecommerce-virtual-assistant/`, `/contractor-management/`, `/contractor-payments/`, `/steal-back-your-time/`, `/stealing-jobs/`, `/stealing-jobs-lp/`. `/referral/` renders the default `Book a free consultation` correctly — it sets no override. Worth noting for future patterns: `contractor-management` and `contractor-payments` pass `headline`/`description` **without** the `_headline`/`_description` field-key pairs and still render, because the raw-`data` read does not need them — but `map_image` is still resolved through `get_field()` only, so that one does (see the scoped override in `resources/patterns/steal-campaign.php`).
4. ~~**Lexgo partner content.**~~ ✅ **Done 2026-09-15.** All three entries (Oyster, Lexgo, Lano) render and appear in the directory. The real blocker was never content: `/partners/` read from an unconfigured Notion database rather than the CPT. Notion is deleted, the directory is CPT-backed, and partner content is seeded from `resources/partners/partners.php` (in git). What remains needs the partners, not code — intake forms for Lexgo and Lano, a referral destination for Oyster, logos for all three. See [domains/partner-hub.md](domains/partner-hub.md#known-gaps).
5. ~~**Reconcile the trackers.**~~ ✅ **Done 2026-09-14.** Resolved by inverting the relationship rather than merging: `PAGE-MIGRATION-STATUS.md` is the source of truth for migration scope, and `content-migration-checklist.md` was demoted to a read-only inventory of what exists on production. They no longer track the same thing, so they can no longer contradict each other.

## Cutover gates

These must all be green before DNS moves. One is green; the redirect gate closed on 2026-09-15.

| Gate | Source | State |
| :--- | :--- | :--- |
| Zero posts carry `_elementor_data` | ADR-0005 | ✅ Verified 2026-09-14 — 0 posts across the whole install |
| Every converted post human-approved | ADR-0005 amendment | ✅ **Closed 2026-09-15 by decision, not by queue.** No post carries `_rl_conversion_status` and none will: [cutover-decisions.md §11](cutover-decisions.md) records that the `content:import-posts` path made the review queue unnecessary. Zero posts carry `_elementor_data`, so the letter of ADR-0005 is met |
| SEO parity — Yoast installed, meta carried, redirect map complete | §9 of the checklist | 🟡 **Import done 2026-09-15.** Yoast 28.5 active and deliberately configured; `wp acorn content:import-seo` run for real — 190 local items, 863 postmeta rows, from 0. Every canonical rewritten onto the local host (0 rows still on `remoteleverage.com`); re-running reports 0 changes. `/blog/` is the canonical archive; `referral-program` and `ecommerce-virtual-assistant` excluded from production's stale `noindex`; redirect map complete in `config/redirects.php` (no Yoast Premium). **Not green yet:** (a) `services`, `store` and `contractoragreement` — built as v2 pages after the decision — inherited production's `noindex` and need a ruling; (b) `company_logo` and 171 OpenGraph image URLs still point at production, pending the media library; (c) the import and the Yoast configuration have to be repeated on staging and production. See [seo-meta-migration.md](seo-meta-migration.md) |
| Every killed URL has a 301 | ADR-0006 | ✅ **Closed 2026-09-15.** `config/redirects.php` holds 166 entries, grouped by bucket with the rule stated per group. Every target verified to resolve 200; no 301 chains; no key shadows a live v2 page. Revised 2026-09-15 after the client decisions: `contractoragreement`, `services` and `store` are being built as real v2 pages, so their keys were dropped rather than 301ing the live pages away; `hire-us-uk-now` was re-pointed from `/hire-for-less/` (LATAM VAs at $6-$10/hr) to `/hire-va-4/`, since the production page sells American and British professionals at $10-$15/hr; and `anyshore` now 301s off-site to `https://anyshore.ai/`, the first absolute external target the map carries |
| Performance baseline met (mobile 96+, LCP < 1.2s, CLS 0.00) | [performance-baseline.md](performance-baseline.md) | 🟡 **Two of three targets met locally, 2026-09-15.** After the font and image work every measured page scores **99–100** mobile (from 60–93) and CLS is at or near 0. **LCP < 1.2s passes on nothing** — best is 1.54s. Headline wins: `/hire-va-4/` 61→94 (LCP 6.93s→2.43s), a single blog post 79→99 (3.27MB→0.59MB). Staging numbers, which is what this gate actually asks for, not yet taken |
| Production infrastructure exists | — | 🔴 **Owned outside this workstream** (2026-09-15). Still a hard gate; not tracked here |
| Rollback plan documented and rehearsed | — | 🔴 **Owned outside this workstream** (2026-09-15). Still a hard gate; not tracked here |
| Transactional email deliverability verified | [performance-baseline.md](performance-baseline.md) | 🔴 Not started — every `MAIL_*` value is empty |
| Webhook signing secrets set in every environment | [known-issues.md](known-issues.md) #7 | 🔴 **New gate, 2026-09-15.** Both webhook endpoints now fail closed: with no secret they return 503 and process nothing. `STRIPE_WEBHOOK_SECRET` and `CALENDLY_WEBHOOK_SIGNING_KEY` are empty everywhere, so deploying as-is takes Stripe Connect payout events and Calendly booking events dark. Set both before this reaches staging |
| Canonical tags verified live | [known-issues.md](known-issues.md) **#17** (behaviour) and **#18** (data) | 🟢 **Behaviour proven 2026-09-15 — not a blocker.** Setting `DISALLOW_INDEXING=false` locally makes `/about-us/` emit `index, follow` plus `<link rel="canonical" href="…/about-us/">`; restoring it removes both. The absence on dev and staging is correct, and production (where the constant is absent) will emit canonicals. **#18 remains open** as a data check: production canonicalises both `/hire-va/` and `/hire-va-isolated-form/` at the homepage and the import carried that across faithfully — fixed, but the same blindness covers all 180 imported canonicals, so re-sweep destinations after any further `content:import-seo` run |
| Error monitoring reporting | — | 🔴 Sentry has no DSN. `SENTRY_LARAVEL_DSN` is now present and blank in `.env` / `.env.example` (2026-09-15) — the key exists, the value is still the gate |
| Funnel redirect URLs repointed off production | [Funnel URLs configured outside WordPress](#funnel-urls-configured-outside-wordpress) | 🔴 **New gate, 2026-09-15.** Four Calendly events and the Stripe deposit element carry redirect targets set outside WordPress. They still point at `remoteleverage.com`, and `STRIPE_DEFAULT_THANKYOU_URL` is unset so the v2 deposit page emits an empty `success-url` |

## Recommended sequence

~~1. Install Yoast; settle `/guides/` vs `/blog/` canonical.~~ ✅ Done 2026-09-15.
~~2. Send decision requests 1 and 2.~~ ✅ Answered — see [cutover-decisions.md](cutover-decisions.md).
~~3. Clear the no-sign-off work: sub-nav, booking-footer sweep, Lexgo, tracker reconciliation.~~ ✅ All four done 2026-09-15.
~~4. Settle the ADR-0005 review gate.~~ ✅ Recorded as decision §11.
~~5. Agree decision 3 and build the role/industry template.~~ ✅ Moot — scope closed at 48 URLs; the ~45 role/industry pages are discarded.

**What is actually left, in order:**

1. Set the webhook signing secrets and `STRIPE_DEFAULT_THANKYOU_URL`; repoint the four Calendly
   redirects. Cheap, and two of them silently break paying customers.
2. Re-point `company_logo` and the 171 OpenGraph image URLs off `remoteleverage.com`.
3. Repeat the Yoast configuration and `content:import-seo` on staging and production.
4. Take the performance numbers **on staging** — the local run is not what the gate asks for.
5. Build production infrastructure and the DNS/rollback runbook. **Owned outside this workstream,
   still a hard gate.**
6. Resolve Sentry's DSN and the `MAIL_*` values; close the remaining gates; flip.

## Gaps this plan does not cover

- ~~**Drafts, private and password-protected content were never audited on production.**~~ **Accepted risk, 2026-09-15** ([cutover-decisions.md §16](cutover-decisions.md)). The REST API only exposes published content without auth. Deliberately not chased — a missed draft is invisible until someone asks for it, and that is understood, not forgotten.
- **Google Site Kit is installed but inactive**, so no GTM snippet is emitted and no tag inside the container fires. Activating it and connecting the container is admin work, but it is a prerequisite for any GTM-delivered tracking at cutover.
- ~~**`e-landing-page` CPT (2 entries)** has no equivalent post type in v2 and no recorded decision.~~ **Dropped 2026-09-15** ([cutover-decisions.md §14](cutover-decisions.md)) — both entries are inside the discarded set and already redirect. No post type is built.
- **Production's media library is deliberately not being ported** (decided 2026-09-15). Local holds 512 attachments; production's was never counted and will not be. Page art is tracked as source in `resources/images/pages/` and generated into `public/images/` at build time, so it does not depend on a library migration. This is a closed decision, not an open gap.
- **`/comparison/` is a never-filled-in internal template on production** — literal `[X]` placeholders, "Text here Text here", a stray "NEW SECTION" label. It was reproduced verbatim under the strict-fidelity rule. **Settled 2026-09-15:** it ships that way but `noindex`, and stays database-resident ([cutover-decisions.md §10, §10b](cutover-decisions.md)).

## SEO gates added 2026-09-14

- [x] **`robots.txt` exists** — `web/robots.txt`, static file in the Bedrock web root. Disallows `/wp/wp-admin/`, the authenticated portals and `/api/`; points at `https://remoteleverage.com/sitemap_index.xml` (**changed from `/wp-sitemap.xml` on 2026-09-15**, see the sitemap item below). (Locally it reports 404 — a Herd/nginx artifact affecting `robots.txt` and `favicon.ico` only. Production serves it 200. See known-issues.md #3.)
- [ ] **Unknown URLs must 404, not serve the homepage** — fixed in code by `MissingPathNotFoundMiddleware`; re-verify on staging after deploy, since the bug was invisible locally until then. Check any nonsense path returns 404 and every Laravel route (`/book-consultation`, `/referrer-portal`, `/referrer-register`, `/referral-dashboard`, `/partners`, `/social-media-kit`) still returns 200.
- [x] **Stop forcing indexability on non-production** — ✅ done 2026-09-14. The theme's indexability override was **removed entirely**; WordPress/Bedrock defaults now apply, so `DISALLOW_INDEXING` (set in `config/environments/development.php` and `staging.php`) does its job. Verified locally: `blog_public=0` and `<meta name="robots" content="noindex, nofollow" />`. Production is unaffected — the DB value is `1`. See known-issues.md #4. **Re-confirm on staging after deploy** that the page source contains that noindex meta, and that production does *not*.
- [x] **Confirm the sitemap** — ✅ **decided 2026-09-15: Yoast's `/sitemap_index.xml`.** Activating Yoast settles it — with `enable_xml_sitemap` on, Yoast serves `/sitemap_index.xml` and 301s core's `/wp-sitemap.xml` onto it, verified locally and exactly what production has served for years. Letting Yoast win keeps the URL Search Console already has, and core's sitemap would otherwise advertise the eight pages this migration just marked `noindex`, since it cannot read `_yoast_wpseo_meta-robots-noindex`. `web/robots.txt` updated to match. (Core's sitemap 404s locally anyway: `blog_public` is `0`, which disables it. Yoast's serves regardless.)
- [ ] **Re-point `company_logo` and the OpenGraph image URLs** — Yoast's Organization logo and 171 imported `_yoast_wpseo_opengraph-image` values still resolve against `remoteleverage.com`, because the media library is deliberately not ported. Harmless while production is up; they must be re-pointed at local uploads before the DNS flip, or the new site's social cards depend on the old one.


## Funnel URLs configured outside WordPress

Audited 2026-09-15. These are the redirect targets the 301 map **cannot** fix, because they
live in Calendly's and Stripe's own settings rather than in this codebase. Nothing 404s today
— `config/redirects.php` catches each old page — but every one of these bounces a paying or
booking customer through an extra hop to a generic page, and they will break outright if
`remoteleverage.com` ever stops answering.

### Stripe — the one that is actually broken

Production's deposit element hardcodes an absolute success URL:

```
success-url="https://remoteleverage.com/referral-program-thank-you-page-deposit/"
```

v2 does the right thing and reads it from config — `PaymentGatewayBlock` falls back to
`services.stripe.default_thankyou_url` — but **`STRIPE_DEFAULT_THANKYOU_URL` is not set in
`.env`**, so the rendered page emits `success-url=""`. A customer who pays today lands
nowhere. Set it to the v2 `/referral-program-thank-you-page-deposit/` before the deposit page
goes live. This sits alongside the Stripe-credentials blocker already recorded against that
page in [PAGE-MIGRATION-STATUS.md](../../../../../PAGE-MIGRATION-STATUS.md) §P2.

### Calendly — four events to re-check

The post-booking redirect for each of these is set in the Calendly dashboard and is not
readable from the page, so each needs opening and confirming by hand:

| Calendly event | Booked from | Old confirmation page |
| :--- | :--- | :--- |
| `d/cyrw-79t-s5r/remote-leverage-hr-cor-compliance-consultation` | `/service-cor/` | `/cor-thank-you/` |
| `d/cxqp-9vk-mvc/remote-leverage-onboarding-applicant-criteria` | `/service-hiring/` | `/cor-thank-you/` |
| `d/crh5-d9t-pt9/remote-leverage-2nd-interview-w-top-candidates` | `/vainterview2/` | — |
| `d/cpyj-nvv-fbm/15-minute-virtual-assistant-hiring-consultation` | sitewide | `/virtual-assistant-consultation-scheduled/` |

The last one is embedded on most pages, so it is the highest-traffic of the four.

**Why this is not just a redirect-map entry.** `/cor-thank-you/`, `/deposit-received/` and
`/virtual-assistant-consultation-scheduled/` all 301 to the generic `/vathankyou/`. Each had
its own copy on production — post-payment next steps, COR-specific booking confirmation — so
until these external settings are repointed, a customer completing a deposit or a COR booking
reads a generic thank-you instead of the one written for them.
