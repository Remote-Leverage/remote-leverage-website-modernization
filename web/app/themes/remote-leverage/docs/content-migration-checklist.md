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
- [x] [`/about-us/`](https://remoteleverage-v2.test/about-us/) — migrated (local page ID 209). Built 2 new blocks: `AboutStatsBlock` (`about-stats`, 3-column headline+category stats) and `ResultsPreviewBlock` (`results-preview`, case-study preview cards linking into `/case-study/bench-accounting/` and `/case-study/chick-fil-a/`). Reused `ClientLogosMarqueeBlock` and `CtaBannerBlock` as-is; hero/narrative/mission-vision/values sections used native Gutenberg core blocks (simple enough not to warrant new ACF blocks). Known trade-off: production's client-quote video is a self-hosted MP4, but `TestimonialsBlock`'s modal is Vimeo-only, so this section renders as a static poster image + quote instead of a playable video — would need a TestimonialsBlock change to support non-Vimeo sources if native video playback is wanted later. Also fixed an image-pipeline bug along the way: a palette-mode source PNG broke the theme's on-the-fly WebP conversion (`imagewebp(): Palette image not supported`); worked around it by re-encoding to truecolor before sideloading rather than patching the shared `BlockDefaults` image filter. **Post-review fix (2026-09-10):** original `ResultsPreviewBlock` design used the Bench/Chick-fil-A images as full-bleed dark-overlay photo backgrounds — but those are logo wordmarks, not photos, so they rendered as giant illegible cropped text. Confirmed against production (that image is actually a small mobile-only logo badge next to a plain title) and rewrote the card to a plain white card with a small logo badge instead. Verified visually with a Playwright screenshot before/after.
- [x] [`/reviews/`](https://remoteleverage-v2.test/reviews/) (local page ID 292) and [`/vapricing/`](https://remoteleverage-v2.test/vapricing/) (local page ID 288) — migrated. Reused `TalentMarqueeBlock`, `TestimonialsBlock` (full 77-item grid via new `BlockDefaults::vaThankYouTestimonials()`/`renderReviewsTestimonials()`), `TrustStatsBlock`, `WhyHireBlock`, `ProcessStepsBlock`, `GuaranteeCardBlock`'s existing `replacement-guarantee` pattern, and `BookingBlock` via the `booking-footer` pattern — all with page-accurate copy overrides where production text differed from the site-wide defaults. Built one new block, `RolesPricingGridBlock` (`roles-pricing-grid`), for the 8-card "Virtual Assistant Roles" pricing grid (photo + price + task checklist + tool logos + CTA) since no existing block matched that bespoke card shape; sideloaded 64 new images (8 role photos + 48 tool logos + 8 VA profile photos/logos) plus 62 testimonial thumbnails that `vaThankYouTestimonials()` referenced but were never actually imported into the media library. Judgment call: the individual VA profile cards (Agustín Sosa, Fernando Almeida, etc.) are rendered via the existing `TalentMarqueeBlock`'s infinite-scroll marquee (full-width, matching the homepage hero's pattern) rather than the legacy site's static right-column grid with manually duplicated cards — same content, adapted to the design system's established presentation for this exact card type. `/reviews/` also transcludes the same shared "$6-$10/hr" funnel template production runs sitewide after the testimonials grid; reused as many existing blocks/patterns as matched exactly (`trust-and-impact`, `booking-footer`) and added small copy-override patterns (`reviews-why-hire`, `reviews-process-steps`, `reviews-guarantee-6mo`, `reviews-faq`) for the handful of sections where production copy diverged from the sitewide defaults (e.g. reviews' guarantee is 6-month, vapricing's own is 12-month).
- [x] [`/privacy-policy/`](https://remoteleverage-v2.test/privacy-policy/) and [`/terms-of-use/`](https://remoteleverage-v2.test/terms-of-use/) — migrated as core Gutenberg content (heading/paragraph/list); local page IDs 3 and 130. **Designed (2026-09-10):** both were rendering as unstyled dumped text — no page title at all (the shared `page.blade.php` skips `partials.page-header` whenever `has_blocks()` is true, so these had no `<h1>`), no navigation through a 13-section/27k-character document, and section headings picking up the global 42px marketing `h2` treatment. Built a shared **`template-legal.blade.php`** page template ("Legal Document", selectable in the editor) rather than an ACF block, so the legal copy stays ordinary editable Gutenberg rich text: dark `linear-gradient(65deg,#270028,#4E1450)` hero (matching the case-study family) with an eyebrow, the page title, and a last-updated chip; a sticky sidebar table of contents on desktop with active-section highlighting; a collapsible `<details>` equivalent on mobile; and a closing contact/back-to-top row. Anchors and the TOC are **derived at render time** by `App\Support\LegalDocument` (9 Pest unit tests) from the `h2`s in the content, so adding a section to either document adds it to the navigation for free — nothing is duplicated into fields. Active-section tracking is a small `rlLegalToc` Alpine component; it uses a rAF-throttled scroll listener rather than IntersectionObserver because legal clauses vary enough in length that a long one leaves no heading inside an observer band, which reads as the highlight dropping out. Typography lives in a scoped `.rl-legal-doc` block in `app.css`, not utility classes — the global `h2.wp-block-heading` rules are `!important`, so arbitrary variants silently lost to them. Content normalization: dropped the two redundant layout wrapper groups (the template now owns width and padding) and promoted the section headings from `h3` to `h2`, since they were `h3`s under no `h1`/`h2` at all. **Not set:** `legal_last_updated` postmeta is empty on both, so the hero falls back to the post modified date (currently the migration date, 2026-09-10) — someone who knows the real revision dates should set it.
- [x] [`/comparison/`](https://remoteleverage-v2.test/comparison/) (ID 323), [`/compare-athena/`](https://remoteleverage-v2.test/compare-athena/) (ID 382), [`/wing-assistant-vs-remote-leverage/`](https://remoteleverage-v2.test/wing-assistant-vs-remote-leverage/) (ID 407), [`/comparison-wing-assistant-ads/`](https://remoteleverage-v2.test/comparison-wing-assistant-ads/) (ID 408) — migrated. Each page's ~20 comparison-table/cost-breakdown sections reuse `DataTableBlock` (custom col headers + rows per competitor, up to 5 instances per page) as anticipated; the hero "at-a-glance" 2-card band reuses `ComparisonMatrixBlock`, extended with new optional card pill/title/2-line/image fields (backward compatible — existing DIY-vs-RL usage untouched) since its layout matched the hero card pattern on all 4 pages almost exactly. Also reused as-is: `ProcessStepsBlock` (numbered step sequences), `FeatureCardsBlock` (industry-icon grids and VA-profile showcase cards), `AccordionFaqBlock`, `GuaranteeCardBlock`, and the shared `hire-va-4-testimonials`/`hire-va-4-faq`/`hire-va-4-booking-footer` patterns where page copy matched the sitewide defaults exactly (verified line-by-line, e.g. all FAQ questions on `/comparison/` and `/compare-athena/` are identical to `BlockDefaults::hireVa4Faqs()`). Media-text explainer rows (e.g. "How Athena works", "Built for Control, Speed, and Scale") used core Gutenberg columns/image/heading/paragraph blocks rather than a new ACF block, since they're a simple alternating image+heading+paragraph shape with no bespoke interactivity. Sideloaded 37 new images (competitor logos, icon grids, process-step frames, VA headshots). `/comparison/` itself is a generic, never-filled-in internal template on production (literal `[X]` placeholders, "Text here Text here", "Xxxxxx xxxxxx" filler, even a stray literal "NEW SECTION" label) — reproduced verbatim per the strict-fidelity directive rather than inventing real competitor copy. **`/comparison-wing-assistant-ads/` is a distinct, shorter page**, not a true duplicate of `/wing-assistant-vs-remote-leverage/`: confirmed via full diff — same narrative and comparison data throughout, but every comparison table is condensed to fewer rows, and it fully omits the "How Wing Assistant works" 3-panel and the "10 Roles/Industries Wing Supports" icon grid; it also uses the real Wing Assistant + Remote Leverage logos in its hero cards instead of the generic stock icons the full page uses. Built both as separate pages with page-specific content rather than sharing one template.
- [x] [`/affiliate-program/`](https://remoteleverage-v2.test/affiliate-program/) and [`/referral/`](https://remoteleverage-v2.test/referral/) — local page IDs 212 and 213. `/affiliate-program/` is a genuine new marketing page (new `AffiliateHeroBlock` + `ProcessStepsBlock` + `BookingFooterBlock`); both its "Apply/Join" CTAs link to the existing `/referrer-register` route (`referrer.register`, `referrer-register.blade.php`) and its "Book a Strategy Call" CTAs scroll to `#booking-footer`. `/referral/` turned out NOT to be a referral-program explainer — production content is section-for-section identical to the already-migrated VA hiring landing page (ID 104, `hire-va-4-preview`), so it was republished with that same proven block content rather than new copy; referral attribution is handled separately by `ReferralAttributionMiddleware` regardless of page content.
- [x] `/blog/` archive is canonical (confirmed) — `/guides/` (not in sitemap, see §4) must 301 to `/blog/` via Yoast redirect manager at cutover
- [x] [`/case-study/`](https://remoteleverage-v2.test/case-study/) archive + all 23 individual `/case-study/{slug}/` pages (full list with links in appendix) — migrated. Production runs these as regular hierarchical Pages under parent ID 32797, all sharing one Elementor template with per-client data, so built one reusable code-first block, `CaseStudyBlock` (`case-study`), with fields for the hero (client name, logo, headline, subheadline, website, a `info_items` repeater for Headquarters/Industry/Company Size/Website, a `stats` repeater for the 3 hero stat tiles), a quote (text/photo/name/company), a `sections` repeater (heading + a `type` select of Rich Text / Stat Tiles / Metric Table, so the recurring "Outcomes" stat-tile repeat and "Results Summary" metric tables reuse the same field shape as narrative sections instead of needing separate blocks), and an optional client video (name/caption/Vimeo ID/poster) — one block instance per page, placed once. The archive itself is a page template (`resources/views/page-case-study.blade.php`, auto-applied via WordPress's `page-{slug}` hierarchy since the parent's slug is `case-study`) that queries its own published child pages live via `WP_Query` rather than hardcoding cards; each card pulls the child page's real title, permalink, and featured image (the client logo, reused from the block's own logo field) plus a `case_study_tags` postmeta (the 3 short descriptor pills from production's archive index, e.g. "SMB / Healthcare / Small Team") — no per-page content is duplicated into the template. Extracted all 23 pages' structured data (client name, industry/company info, stats, quote, narrative sections, video) from production's Elementor markup via a Python parser handling several structural variants (fused "Label: <h2>Title</h2>" headings, roundup pages with a subheadline instead of company-info rows, literal `<table>` HTML vs. fragmented metric/outcome pairs for "Results Summary"). Sideloaded 64 client logos/headshots/video posters via `wp media import`. Two pages (`construction-home-services-virtual-assistants`, `private-practices-hire-faster`) are actually multi-client "roundup" case studies rather than single-client stories — they still fit the same block/field shape (more `sections` rows, no video), just with one roundup-specific stat block on `private-practices-hire-faster` losing its stat-tile visual treatment (source used literal nested `<h3>` tags for those 4 values instead of the usual flat value/label pairs, so it renders as a plain rich-text paragraph there rather than tiles — content is intact, only that one section's visual treatment is reduced). `wp eval` block-audit and unicode/img-src checks: 0 errors across all 24 pages; all local URLs spot-checked return 200. **Post-review fix (2026-09-10):** the archive's client-logo marquee rendered as solid black squares — the shared `.rl-logo-marquee-item img` CSS applies `filter: brightness(0)` (turns transparent-background logos into black silhouettes, used correctly elsewhere on the site), but these sideloaded case-study logos are opaque white-background PNGs, so brightness(0) blacked out the whole square instead of just the mark. Fixed by adding `filter-none opacity-100` to override the shared filter for this specific marquee instance (didn't touch the shared CSS class, since it's correct for its other, transparent-PNG use cases). Verified visually with a Playwright screenshot before/after — full-page screenshots at this page's height (~20000px+) render too small to catch this kind of bug, so verification used viewport-scale and per-element screenshots instead. **Refactored to a custom post type (2026-09-10):** at request, moved off the "regular hierarchical Pages" structure (which only matched production's own implementation) onto a proper `case_study` CPT — `app/Infrastructure/WordPress/PostTypes/CaseStudyPostType.php`, registered in `DomainServiceProvider` alongside the existing `rl_partner` CPT, `has_archive`/rewrite slug `case-study`. Migrated all 23 entries in place (`post_type` → `case_study`, `post_parent` → 0; slugs, content, featured images, and `case_study_tags` meta all carried over unchanged since they live on the same post IDs), deleted the now-empty parent container page, and flushed rewrite rules. Replaced `page-case-study.blade.php` with a native `archive-case_study.blade.php` (same design, now driven by the real archive loop instead of a manual child-page `WP_Query`) and a lightweight `partials/content-single-case_study.blade.php` (skips the generic single-post title/byline/comments furniture, since `CaseStudyBlock` renders its own hero). Re-verified via screenshot: archive and single pages are pixel-identical to the pre-refactor version.

**Design correction (2026-09-10):** the original `CaseStudyBlock` visual design (light hero, single-column narrative) was a novel design, not a faithful reproduction — the earlier migration agent extracted the right *content* via the REST API but never actually compared the rendered result against production's real layout. Fetched production's live HTML + compiled CSS (`post-33917.css` for chick-fil-a) and rebuilt `resources/views/blocks/case-study.blade.php` to match exactly: dark `#270028` hero band with the client name as a large heading, logo/headline/info/stats in a 35%/65% split, and every narrative section (Background, Challenges, Why Remote Leverage, Results, Benefits, Outcomes, Video) using the same 35%/65% label-column/content-column layout production uses throughout. Also fixed a real data bug this surfaced: the `video_name`/`video_caption` fields had been populated backwards for all 19 case studies with video (e.g. `video_name` held "Traci talks about her experience..." instead of "Traci Danmeyer") — corrected via a script cross-referencing `quote_name` for the full name. Verified against the user-supplied production reference screenshots for chick-fil-a (hero, quote, all narrative sections, Outcomes grid, video) and spot-checked a no-video case study (Saber Tech Group) — both match. Not yet added: the "CASE STUDIES / TALENT PROFILES / REVIEWS" sub-nav tab bar production shows above the hero (site-wide element, shared with `/reviews/` and a talent-profiles page) — flagged as a follow-up, not yet built.

**Archive redesign (2026-09-10):** the archive had the same problem as the single template — invented light-background design instead of production's actual dark-gradient hero + tinted-logo card grid. Fetched production's archive HTML/CSS (`post-32797.css`) and rebuilt `archive-case_study.blade.php`: `linear-gradient(65deg, #270028 0%, #4E1450 100%)` hero containing the eyebrow/headline/description and the logo marquee (moved inside the dark band, was previously a separate light section below it), and cards with production's exact `#FB33FF` border, `#FECEFF` tag-pill background, and a solid `#4E1450` "Read More" button (was a plain text link). Sideloaded the 23 dedicated monochrome/wordmark logo assets production actually uses for this index (distinct from the full-color square logo used on each single page's hero — stored separately as `case_study_index_logo` postmeta) and tinted them to match via CSS filter rather than a new pattern per asset. One known deviation: production's own archive lists "The Acre Hub" twice (a content bug, confirmed by checking the raw page twice) — not replicated, since our archive is data-driven from the CPT query and every case study correctly appears exactly once.

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

## Appendix: full case-study list (23) — migrated, local page IDs linked

Parent archive: [`/case-study/`](https://remoteleverage-v2.test/case-study/) (local page ID 383)

| Slug | Local URL | Page ID |
| :--- | :--- | :--- |
| chick-fil-a | [/case-study/chick-fil-a/](https://remoteleverage-v2.test/case-study/chick-fil-a/) | 384 |
| bench-accounting | [/case-study/bench-accounting/](https://remoteleverage-v2.test/case-study/bench-accounting/) | 385 |
| emporia-consulting | [/case-study/emporia-consulting/](https://remoteleverage-v2.test/case-study/emporia-consulting/) | 386 |
| jeremis-auto-repair | [/case-study/jeremis-auto-repair/](https://remoteleverage-v2.test/case-study/jeremis-auto-repair/) | 387 |
| mobile-mixologist | [/case-study/mobile-mixologist/](https://remoteleverage-v2.test/case-study/mobile-mixologist/) | 388 |
| doran-industries | [/case-study/doran-industries/](https://remoteleverage-v2.test/case-study/doran-industries/) | 389 |
| anchorage-care-coordination | [/case-study/anchorage-care-coordination/](https://remoteleverage-v2.test/case-study/anchorage-care-coordination/) | 390 |
| clean-cozy-home | [/case-study/clean-cozy-home/](https://remoteleverage-v2.test/case-study/clean-cozy-home/) | 391 |
| smiley-injury-law | [/case-study/smiley-injury-law/](https://remoteleverage-v2.test/case-study/smiley-injury-law/) | 392 |
| on-the-outskirt | [/case-study/on-the-outskirt/](https://remoteleverage-v2.test/case-study/on-the-outskirt/) | 393 |
| haus-of-her | [/case-study/haus-of-her/](https://remoteleverage-v2.test/case-study/haus-of-her/) | 394 |
| soccer-stars | [/case-study/soccer-stars/](https://remoteleverage-v2.test/case-study/soccer-stars/) | 395 |
| conservice | [/case-study/conservice/](https://remoteleverage-v2.test/case-study/conservice/) | 396 |
| fast-real-estate | [/case-study/fast-real-estate/](https://remoteleverage-v2.test/case-study/fast-real-estate/) | 397 |
| hawaiian-philanthropy | [/case-study/hawaiian-philanthropy/](https://remoteleverage-v2.test/case-study/hawaiian-philanthropy/) | 398 |
| wright-legal-group | [/case-study/wright-legal-group/](https://remoteleverage-v2.test/case-study/wright-legal-group/) | 399 |
| the-acre-hub | [/case-study/the-acre-hub/](https://remoteleverage-v2.test/case-study/the-acre-hub/) | 400 |
| double-close | [/case-study/double-close/](https://remoteleverage-v2.test/case-study/double-close/) | 401 |
| saber-tech-group-unloads-admin | [/case-study/saber-tech-group-unloads-admin/](https://remoteleverage-v2.test/case-study/saber-tech-group-unloads-admin/) | 402 |
| gotham-lighting-supply | [/case-study/gotham-lighting-supply/](https://remoteleverage-v2.test/case-study/gotham-lighting-supply/) | 403 |
| watson-psychiatry | [/case-study/watson-psychiatry/](https://remoteleverage-v2.test/case-study/watson-psychiatry/) | 404 |
| construction-home-services-virtual-assistants | [/case-study/construction-home-services-virtual-assistants/](https://remoteleverage-v2.test/case-study/construction-home-services-virtual-assistants/) | 405 |
| private-practices-hire-faster | [/case-study/private-practices-hire-faster/](https://remoteleverage-v2.test/case-study/private-practices-hire-faster/) | 406 |
