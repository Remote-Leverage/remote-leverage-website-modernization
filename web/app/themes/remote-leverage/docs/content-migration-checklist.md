# Content Migration Checklist — remoteleverage.com → v2

Source-of-truth audit of everything live on remoteleverage.com, built from the Yoast
XML sitemaps **plus** the WP REST API (to catch noindex'd/unlisted content the
sitemap won't show). Feeds Phase 8 ("Staged Content Cutover") in `plan.md`.

Live counts at audit time (2026-09-10):
- 233 published `page`s (189 indexed in sitemap, **44 not in any sitemap** — see below)
- 119 `post`s across 8 categories
- 2 `rl_partner` entries (Oyster, Lexgo)
- 2 `e-landing-page` CPT entries (no matching CPT exists in v2 yet)
- 7 categories, 8 tags

> Not audited yet: drafts, private/password-protected posts, and media library —
> the REST API only exposes published content without auth. If we need those too,
> an app password against wp-admin would let us query `status=any`.

---

## 1. Core marketing pages (must migrate 1:1)

- [ ] `/` — Homepage (already built: `front-page.blade.php`)
- [ ] `/about-us/`
- [ ] `/reviews/` and `/vapricing/`
- [ ] `/privacy-policy/` and `/terms-of-use/`
- [ ] `/comparison/`, `/compare-athena/`, `/wing-assistant-vs-remote-leverage/`, `/comparison-wing-assistant-ads/` (ComparisonMatrix block exists — confirm content parity)
- [ ] `/affiliate-program/` and `/referral/` (referrer portal pages already exist — confirm these route to them)
- [ ] `/blog/` archive — **note:** Yoast reports the canonical `post` archive as `/guides/`, not `/blog/`. `/guides/` isn't in the sitemap at all (see §4). Decide which URL is canonical before cutover.
- [ ] `/case-study/` archive + all 22 individual `/case-study/{slug}/` pages (full list in appendix)

## 2. Role/industry landing pages ("Virtual Assistants" SEO templates)

Large template-driven set — good candidate for a single dynamic template + data
rather than one-off pages. ~45 URLs, e.g.:
`/virtual-assistants/`, `/sales-virtual-assistants/`, `/admin-virtual-assistants/`,
`/marketing-assistants/`, `/legal-virtual-assistants/`, `/healthcare-virtual-assistants/`,
`/bookkeeping-virtual-assistants/`, `/ecommerce-virtual-assistants/`,
`/real-estate-virtual-assistants/`, `/medical-virtual-assistants/`, `/paralegal/`,
`/executive-virtual-assistants/`, `/lead-generation-virtual-assistants/`,
`/customer-support-virtual-assistants/`, `/insurance-virtual-assistants/`,
`/graphic-design-virtual-assistants/`, `/cold-calling-virtual-assistants/`,
`/appointment-setting-virtual-assistants/`, `/paid-ads-managers/`, and Latin
America-flavored variants (`/sales-virtual-assistants-from-latin-america/`,
`/marketing-assistants-from-latin-america/`, `/virtual-assistants-from-latin-america/`, etc).

- [ ] Decide: one Blade template driven by ACF/CPT fields (role, industry, region) vs. individually-built pages
- [ ] Audit duplicates before rebuilding — several exist as `-legacy`, `-2`, `2` suffixed near-duplicates of the same role (e.g. `marketing-assistants` / `marketing-assistants-2` / `marketing-assistants-legacy`; `sales-virtual-assistants` / `-2`; `virtual-legal-assistants` / `-2`; `virtual-ecommerce-assistants` vs `ecommerce-virtual-assistants` vs `ecommerce-virtual-assistant`) — pick one canonical page per role and 301 the rest
- [ ] `/hire-*-virtual-assistants/` variants (`hire-sales-`, `hire-executive-`, `hire-admin-`, `hire-marketing-`) — confirm these are distinct from the role pages above or consolidate

## 3. Partner hub

- [x] `single-rl_partner.blade.php` + `archive-rl_partner.blade.php` already built
- [ ] Migrate content for both live partners: `/partners/oyster/`, `/partners/lexgo/`
- [ ] `remote-leverage-x-oyster` and `remote-leverage-x-lano` standalone pages — confirm whether these fold into the partner hub or stay separate

## 4. Pages excluded from the sitemap (noindex / orphaned — **not caught by a sitemap crawl**)

Confirmed via REST API diff against the sitemap. Needs a keep/kill/redirect decision per row before cutover — several look like live operational/funnel pages, not dead test pages.

**Operational/funnel pages (likely still in active use — verify with sales/ops before dropping):**
- [ ] `/payment/`, `/deposit-received/`, `/signedup/`
- [ ] `/contractoragreement/` (Independent Contractor Agreement)
- [ ] `/onboardingform/`, `/vaonboardingform/`, `/onboardingguide/`
- [ ] `/vainterview/`, `/vainterview2/`
- [ ] `/service-cor/`, `/service-hiring/`
- [ ] `/virtual-assistant-consultation-scheduled/`, `/virtual-assistant-hiring-consultation-booked-v2/`
- [ ] `/referral-program/`, `/referral-program-thank-you-page-deposit/`
- [ ] `/va-form/` ("Find me a VA")
- [ ] `/hmchecklists/`, `/recruiterchecklists/`, `/saleschecklists/` (internal checklists — confirm audience)

**Utility/nav pages not in sitemap:**
- [ ] `/guides/` — the actual canonical blog/post archive per Yoast (see §1 note)
- [ ] `/services/`, `/store/`, `/tools/`, `/sales-talents/`, `/samples-legacy/`
- [ ] `/anyshore/` ("AI-Powered Candidate Discovery Engine")
- [ ] `/guaranteed-fit/`, `/ecommerce-virtual-assistant/`, `/0-to-hire-a-virtual-assistant/`, `/zero-to-hire/`, `/hire-direct/`

**Confirmed dead — old homepage/test variants, do not migrate, just redirect to `/`:**
- [ ] `/home/`, `/b/`, `/simplified/`, `/hire-va/`, `/hire-va-2/`, `/hire-va-3/`, `/hire-va-5/`, `/hire-va-t/`, `/hire-va-isolated-form/`, `/hire-va-live-calling-feature/`, `/vastore5/`, `/vastore5-b/`, `/aaaa/`

## 5. In-sitemap A/B-test / experiment pages (review for redirect vs. rebuild)

These are indexed but read like landing-page experiments. Decide per-page whether
there's a "winning" variant worth rebuilding or whether all should 301 to a live equivalent:
`home-test`, `home-tasks-variation`, `home-tasks-variation-dark`, `home-eu`,
`new-home-26-v2`, `new-home-26-v2b`, `new-homepage-26-4`, `booking-template-test`,
`midway-warning-step-test`, `test-landing-page-26`, `test-landing-page-26-2`,
`test-variant-b`, `live-call-test-remote-leverage-home`, `form-lp`, `form-lp2`,
`vlet-lp`, `vsl-lp`, `prtnrshp-insp-lp`, `hire-va-4` (already has a full pattern
built — confirm it's the winner), `hire-va-6`, `hire-va-email`,
`hire-va-1st-month-free`, `hire-va-new-live-calling-feature`,
`hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant`
(+ `-b`, `-c`), `1monthonus`, `1monthonus-eu`, `1monthonus-flp`, `steal-back-your-time`,
`stealing-jobs`, `stealing-jobs-lp`, `0tostart`, `0tostart-elite-talent`,
`freethisweek`, `freetrial`, `dontpaytohire`, `tellustherole`, `withoutpayingfirst`,
`hire3forthecostof1`, `moneyback`, `love-it-or-get-a-refund`.

- [ ] Get sign-off from whoever owns paid-traffic campaigns before killing any of these — several are likely live ad landing pages with active spend pointed at them

## 6. Thank-you / confirmation pages

- [x] `/thank-you/`, `page-vathankyou.blade.php` already built
- [ ] `/thankyou-ppp/`, `/vathankyou/`, `/cor-thank-you/`, `/jobapplicationsubmitted/` — confirm these consolidate into the one thank-you template or need distinct flows

## 7. Tools / lead magnets (interactive, not static content)

- [ ] `/job-description-generator/` (+ `-2`), `/job-posting-template-generator/`
- [ ] `/text-optimizer/`, `/youtube-script-generator/`, `/marketing-email-generator/`
- [ ] `/social-media-kit/`, `/resume/`, `/signature-generator/` (page already scaffolded: `signature-generator.blade.php`)
- [ ] `/referral-dashboard/` (referrer portal already exists — confirm parity)

## 8. Blog content (119 posts)

- [ ] Business Growth — 91 posts
- [ ] Outsourcing — 95 posts *(categories overlap; a post can carry both)*
- [ ] Case Studies (category) — 13 posts — cross-check against the 22 `/case-study/` CPT-style pages in §1; likely two different content types covering similar ground
- [ ] Real Estate Posts — 9
- [ ] Salary Guides — 12
- [ ] Live Sessions — 2
- [ ] News — 2
- [ ] VA Guides (`virtual-assistant-posts`) — 0 posts currently tagged, but `plan.md` already flags "100+ VA guides" as explicitly **deferred**, not in scope for this pass
- [ ] Elementor → Gutenberg conversion pass (`wp acorn content:audit-elementor` / `ConvertElementorPostAction`) still needs to run per `plan.md` Phase 8 — this blocks any bulk post migration
- [ ] 8 tags (`blog-pages`, `cold-calling`, `partnership-program`, etc.) — mostly internal/organizational, confirm which need to survive as public taxonomy pages

## 9. SEO/meta parity

- [ ] Install **Yoast SEO** on v2 (live site runs Yoast SEO Premium v28.4) — matching plugin means `_yoast_wpseo_*` postmeta, schema graph, and canonical URLs carry over directly instead of needing field-mapping into a different plugin's schema
- [ ] Set up Yoast's redirect manager (Premium) for every URL in §4/§5 that gets killed rather than rebuilt
- [ ] Re-point the `post` archive canonical (`/guides/` vs `/blog/` — see §1) before launch so Yoast doesn't fight itself
- [ ] Carry over Organization/WebPage schema graph settings (site name, social profiles, logo) from the live site's Yoast configuration

---

## Appendix: full case-study list (22)

chick-fil-a, bench-accounting, emporia-consulting, jeremis-auto-repair,
mobile-mixologist, doran-industries, anchorage-care-coordination,
clean-cozy-home, smiley-injury-law, on-the-outskirt, haus-of-her, soccer-stars,
conservice, fast-real-estate, hawaiian-philanthropy, wright-legal-group,
the-acre-hub, double-close, saber-tech-group-unloads-admin,
gotham-lighting-supply, watson-psychiatry, construction-home-services-virtual-assistants,
private-practices-hire-faster
