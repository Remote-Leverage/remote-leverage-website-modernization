# Design system

Tokens, blocks, patterns and templates — everything that decides what a page looks like.

For the *process* of migrating a production page into this system (extract → dissect → build → verify), see [page-migration-and-design-system-workflow.md](page-migration-and-design-system-workflow.md). This document is the reference: what exists and what the rules are.

---

## Non-negotiable rules

1. **The canonical container is 1380px.** Blade/Tailwind: `w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8`. Gutenberg: every root `wp:group` declares `"layout":{"type":"constrained","contentSize":"1380px"}`. `theme.json` sets both `contentSize` and `wideSize` to `1380px`. Never `max-w-4xl`…`max-w-7xl`, `1140px` or `1200px` on a top-level section.
2. **Bold (700) is the heaviest weight.** No `font-extrabold`, `font-black`, `800` or `900`.
3. **Bespoke sections are ACF blocks.** Raw HTML inside `core/group` / `core/columns` triggers Gutenberg's "unexpected or invalid content" recovery modal, because core containers use `<InnerBlocks.Content />` and expect only block comments as children. An ACF block saves as a single server-rendered comment and can never fail client-side validation. If you must use core blocks, every visual element is a block comment and any custom markup is wrapped in `<!-- wp:html -->`.
4. **Strict 1:1 fidelity when reproducing a production page.** Fetch the production page's compiled CSS (`post-<id>.css`) and match colours, gradients, borders and padding exactly. If no token matches a production value, *extend the tokens* — do not adjust the design.
   **The container is the exception to this.** Measuring production tells you its type scale, spacing and colour; it does not set the container. Production's Elementor pages measure 1260–1320px, but migrated pages still use the canonical 1380px from rule 1. `/signedup/` and `/referral-program/` were first built at production's measured 1260px and corrected on 2026-09-15.
5. **Page content is authored as a `*-full.php` pattern in git**, then applied to the page. Not edited in the database.

## Tokens

Tailwind v4 — everything is declared with `@theme` in `resources/css/app.css`. There is no `tailwind.config.js`.

### Brand colours

| Token | Value |
| :--- | :--- |
| `--color-brand-purple` | `#8A2BE2` |
| `--color-brand-purple-deep` | `#6200A4` |
| `--color-brand-navy` | `#342567` |
| `--color-brand-midnight` | `#18112C` |
| `--color-brand-dark-violet` | `#25104A` |
| `--color-brand-hero` | `#13132F` |
| `--color-brand-magenta` / `--color-brand-magenta-hover` | `#F90066` / `#D90057` |
| `--color-brand-orange` / `--color-brand-orange-warm` | `#FB7501` / `#F97316` |

Surfaces (`--color-bg-light` `#F4F6FC`, `--color-bg-map` `#E4ECFC`, `--color-bg-benefits` `#FFF5FD`, `--color-roles-surface` `#250D4A`), semantic table colours (`--color-table-leverage` `#00D982`, `--color-table-competitor` `#D94900`), check/cross (`#94DB49` / `#DB4437`), and a set of glassmorphism alpha tokens round it out.

### Blocks extended for the ecommerce page (2026-09-15)

Seven existing blocks gained options rather than being forked, each defaulting to its previous
behaviour so no other page moved. Reach for these before building anything new:

| Block | Option added |
| :--- | :--- |
| `acf/feature-cards` | `columns: 2`; the card image is now optional (text-only cards) |
| `acf/image-card-grid` | per-card `cta_text` / `cta_url`, and `emphasis` for a black-outlined featured card |
| `acf/partner-hero` | `tone: light`, plus a `badges` repeater for the checked pill grid |
| `acf/roles-pricing-grid` | `variant: split-chip` and `columns: 2`, with per-card chip fields |
| `acf/talent-carousel` | `layout: full` — drops the left copy column |
| `acf/cta-banner` | `tone: light`, overridable `gradient_start` / `gradient_end`, `align: split` |
| `acf/guarantee-card` | `background: flat-midnight`, and a toggle for the three reassurance items |
| `acf/media-copy` | optional `video_url` — the media slot becomes a player, the image its poster |

Two extensions in the original plan turned out to be unnecessary: a trusted-by eyebrow and a
"Meet Our Talent" subheadline can both come from the pattern, because those blocks render no
heading of their own. Check that before widening a block.

### Blocks extended for the P4 campaign pages (2026-09-15)

Same principle — every option defaults to the block's previous behaviour, so no already-signed-off
page moved when these landed.

| Block | Option added | Why |
| :--- | :--- | :--- |
| `acf/accordion-faq` | `columns` (`1` \| `2`, default `2`), `schema` (default on) | Production has single-column FAQs, and `FAQPage` structured data does not belong on internal noindex collateral. Two patterns had hand-rolled their own accordion for exactly these two reasons |
| `acf/roles-grid` | `admin_tint` (`dark` default \| `lavender`) | Currently **no consumer** — see the warning below |
| `acf/consult-landing-hero` | `variant` (`consultation` default \| `va-roles`), plus `headline_gradient`, `headline_size`, `background_image_class`, `split_at`, `cta_text`, `cta_url` | Lets the va-roles hero drop its hand-written markup, so one hero implementation serves both families |
| `acf/testimonials` | `show_more` (default off), `visible_count` (default 6), `tone` (`light` default \| `dark`) | Production collapses the review wall behind a SHOW MORE pill. Default off on purpose: defaulting it on would have collapsed `/reviews/` from 77 cards to 6 |
| `multistep-booking-wizard` | `revenueFirst` (default off) | The page-bottom booking blocks lead with the revenue question as a radio list. Hero forms keep their progressive email-first reveal |
| `.rl-logo-marquee-*` (CSS) | `-lg` (50px mark on a 60px row) and `-natural` (width-sized, no filter) modifiers, alongside the existing `-dark` | Three templates were overriding the base `max-height: 28px` rule at higher specificity |

> **Do not use `admin_tint: lavender` without re-measuring.** It was added on a report that
> production renders the roles bento's Administrative card light lavender. That report was wrong:
> production measures `rgb(99,65,162)` with white text on `/hire-va-4/`, `/hire-va-6/` and
> `/hire-va-1st-month-free/` — identical to what the theme already shipped. The option exists but
> has no consumer, and opting a page in would *break* parity.

**Two dead-field traps found in `acf/roles-grid` while doing this**, worth checking for elsewhere:
its Blade view hard-coded the CTA label and `href="#booking"` and never read the `$ctaText` /
`$ctaUrl` the block computed in `with()`, and `$eyebrow` was dead the same way. Both are now wired.
**`$cards` is still dead** — `RolesGridBlock::cards()` and its repeater are computed, but the view
renders eight hard-coded cards. A field that exists in the editor and changes nothing on the page
is worse than no field at all.

Rule 2 (bold is the heaviest weight) was swept theme-wide the same day: 18 `font-extrabold` /
`font-black` occurrences across 12 files — including the homepage hero CTA and its stat figures,
`404.blade.php`, `trust-stats`, `cta-banner`, `talent-carousel`, the booking wizard, the referrer
portal and the site header — are now `font-bold`.

### Two stat surfaces, deliberately

`acf/trust-stats` and `acf/stats-band` carry the same three company figures. They are not
duplicates: `trust-stats` is a 470px stack of white cards that sits beside hero copy, while
`stats-band` is the full-width flat dark band production uses on
`/ecommerce-virtual-assistant/`. Nothing in the inventory rendered the latter shape, so a
fourth variant on `trust-stats` would have been the wrong seam.

Be aware the figures are transcribed from production verbatim, and production's are malformed
(`41,920,00`, `Economic Impact Create`). They therefore appear with different spellings in the
two blocks. That is intentional under the copy-as-is direction for the ecommerce page — fix
both together if the source copy is ever corrected.

### Roles bento tints

`acf/roles-grid` ("The Roles That Buy Back Your Time") uses five tints read off production's
computed backgrounds on 2026-09-15. The grid is deliberately **asymmetric — only the
Administrative card is dark.** Customer Support looks like a second dark card but is a light
lavender on production; rendering it dark was the drift these tokens fixed.

| Token | Value | Cards |
| :--- | :--- | :--- |
| `--color-roles-feature` | `#6341A2` | Administrative (the one dark card, white text) |
| `--color-roles-lavender` | `#E1E1F7` | Lead Generation, Customer Support |
| `--color-roles-blue` | `#C6DBFF` | Sales (SDR), Graphic Design |
| `--color-roles-sky` | `#E1ECF7` | Social Media |
| `--color-roles-violet` | `#ECE1F7` | Marketing |

Custom Role is plain `bg-white`. `--color-roles-surface` (`#250D4A`) is a different thing — the
dark band behind the section on other pages — and is not one of the card tints.

`theme.json` (version 3) exposes a deliberately small editor palette — `brand-purple`, `brand-dark-violet`, `bg-light`, `bg-map`, `black`, `white` — so editors cannot drift off-brand. The full token set is available to Blade, not to the editor colour picker.

### Type scale

Named sizes taken from production's computed styles, so competitor and marketing pages share one scale instead of repeating magic numbers: `--text-hero` (64px), `--text-section` (48px), `--text-display` (46px), `--text-step` (36px), `--text-eyebrow` (27px), `--text-lead` (20px), `--text-cta` (17px), `--text-card` (15px), plus `--text-numeral` (201px) for the oversized step numerals. Each carries its own line-height and letter-spacing.

Fonts: Inter Variable (`@fontsource-variable/inter`), bundled — not fetched from Google.

### Stylesheets

| File | Scope |
| :--- | :--- |
| `resources/css/app.css` | Tokens + front end |
| `resources/css/editor.css` | Block editor parity |
| `resources/css/blog.css` | Editorial/article typography |

Scoped component classes (for example `.rl-legal-doc`) live in `app.css` rather than being expressed as utilities, because the global `h2.wp-block-heading` rules are `!important` and arbitrary Tailwind variants silently lose to them.

## Blocks

51 code-first ACF blocks in `app/Blocks/`, each with a Blade view in `resources/views/blocks/`. Registered through `Log1x\AcfComposer`; defaults come from `App\Support\BlockDefaults`, so a freshly inserted block is fully populated rather than empty.

**Before building a section, read [`block-inventory.md`](block-inventory.md)** — generated by
`wp acorn blocks:inventory`, it maps every block to the production section it renders, its
fields and where it is already used. Building a second copy of something that exists forks the
design system and means a fix to the block never reaches the duplicate.
`tests/Unit/PatternBlockReuseTest.php` fails the build on hand-written card markup in a pattern
that carries no `// @bespoke:` justification.

Where production has a *variant* of an existing block, add an option rather than a second
block, and default it to today's behaviour so existing usages are untouched —
`feature-cards` has `variant` (`inset` / `flush` / `horizontal`) and `ratio`,
`talent-grid` has `layout` (`grid` / `row`), `cta-banner` has `variant` (`card` / `band`)
plus an optional `background_image`, `testimonials` has `layout` (`cards` / `plain`) and
`columns` (`3` / `2`), and `media-copy` has `tone` (`light` / `white` / `dark`),
`text_align`, `heading_color` and `image_max_width`.

| Group | Blocks |
| :--- | :--- |
| Hero & entry | `hire-va-hero`, `about-hero`, `comparison-hero`, `affiliate-hero`, `vacalendar-hero`, `contractor-payments-hero`, `contractor-management-hero`, `impact-report-hero`, `partner-hero`, `referral-program-hero` |
| Booking & funnel | `booking`, `booking-footer`, `cta-banner`, `payment-gateway`, `payment-success-banner`, `jotform-embed` |
| Talent | `talent-marquee`, `talent-carousel`, `talent-grid`, `roles-grid`, `roles-carousel`, `roles-pricing-grid`, `department-cards` |
| Social proof | `testimonials`, `trust-stats`, `client-logos-marquee`, `case-study`, `results-preview`, `about-stats` |
| Comparison | `comparison-matrix`, `cost-comparison`, `data-table`, `split-compare-cards`, `solution-choice` |
| Process & value | `process-steps`, `process-step-cards`, `progress-steps`, `why-hire`, `feature-cards`, `guarantee-card`, `assurance-pair`, `next-steps-panel` |
| Editorial | `accordion-faq`, `media-copy`, `image-card-grid`, `about-narrative`, `about-talent-banner`, `country-placements`, `sample-applicant-videos`, `sample-applicant-audio` |

Notes worth knowing:

- `accordion-faq` emits Schema.org JSON-LD FAQ structured data from a semantic `<details>` accordion.
- `testimonials` uses CSS scroll-snap with an Alpine video lightbox. **The modal is Vimeo-only** — a self-hosted MP4 renders as a static poster image instead. `layout="plain"` drops the quote/company/duration chrome for a bare 16:9 video wall (production's `/signedup/`); `columns="2"` halves the grid. The block's root is a bare `w-full` — **the caller supplies the container**, so echoing it straight into a pattern lets the grid run edge to edge.
- `process-steps` sizes its grid from the step count via `--rl-process-cols` (1–4), defaulting to 3. It was hard-coded to 3 columns until 2026-09-15.
- `jotform-embed` wraps a hosted JotForm by numeric form ID, with an optional heading, intro and card. The form's interior is JotForm's and **cannot be styled from the theme** — only the chrome around it. Used by `/payment/` and `/vaonboardingform/`.
- `payment-gateway` is the Stripe Elements checkout ported from `rl-elementor-blocks`. The charge amount is resolved **server-side from the block**, never from the browser. See [`stripe-payments.md`](stripe-payments.md); it is inert until credentials are configured.
- `booking` / `booking-footer` embed `MultistepBookingWizard` with a configurable skin; `booking-footer` is the dark glassmorphic `skin="glass"` variant.
- Heroes are deliberately one block per shape rather than one block with a `theme` option, so an editor picks the hero they want from the inserter gallery.
- `progress-steps` is the segmented progress bar used by the affiliate programme's "How It Works" and the partner pages' "Three Steps to a Fully Staffed Team".
- `case-study` carries the whole case-study page shape in one block: hero, info repeater, stat tiles, quote, a `sections` repeater with a Rich Text / Stat Tiles / Metric Table type select, and an optional Vimeo video.

`App\Support\BlockDefaults` centralises demo content, attachment mapping and text sanitisation. When a page needs copy that differs from the sitewide default, the override goes in a small pattern — not into the block's defaults.

## Patterns

53 patterns in `patterns/`, registered under five categories (`app/setup.php`):

| Category | Slug |
| :--- | :--- |
| Remote Leverage | `remote-leverage` |
| Heroes | `remote-leverage-heroes` |
| Sections & Trust | `remote-leverage-sections` |
| Booking & Funnels | `remote-leverage-funnels` |
| Editorial VA Guides | `remote-leverage-guides` |

Every pattern is **pre-hydrated** — block attributes carry real content, so inserting one renders immediately instead of showing empty placeholders.

The naming convention carries meaning:

- **`*-full.php`** — a complete page. `about-full`, `reviews-full`, `vapricing-full`, `affiliate-full`, `comparison-full`, `comparison-wing-full`, `comparison-wing-ads-full`, `comparison-athena-full`, `hire-va-4-full`, `full-homepage`. These are the unit of page authoring: build the page as a `-full` pattern in git, then apply it.
- **`<page>-<section>.php`** — a section carrying that page's copy override, used when production copy diverges from the sitewide default (for example `reviews-guarantee-6mo` is the 6-month guarantee, while `vapricing`'s own is 12-month).
- **Bare section names** (`hero`, `client-logos`, `process-steps`, `trust-and-impact`, `booking-footer`, `testimonials-video-modal`, `replacement-guarantee`, `worlds-best-talent`, `why-companies-choose`, `beyond-virtual-assistant`) — sitewide-default sections, reusable anywhere.
- **`guide-*.php`** — editorial furniture: table of contents, key takeaways, author bio, related articles.

## Templates

```
resources/views/
├── layouts/app.blade.php            Master shell
├── front-page.blade.php             Homepage
├── page.blade.php                   Default page (skips page-header when has_blocks())
├── template-landing.blade.php       Landing page template
├── template-legal.blade.php         "Legal Document" — TOC-driven legal pages
├── template-custom.blade.php        Custom template
├── page-vathankyou.blade.php        Thank-you page
├── single.blade.php  index.blade.php  archive.blade.php  search.blade.php  404.blade.php
├── archive-case_study.blade.php     Case study index
├── partials/content-single-case_study.blade.php
├── archive-rl_partner.blade.php     Partner directory
├── single-rl_partner.blade.php      Co-branded partner hub (9 tabs)
├── pages/                           Acorn-routed pages: book-consultation, referrer-portal,
│                                    referrer-register, signature-generator
├── blocks/                          One view per ACF block
├── livewire/                        One view per Livewire component
├── sections/                        header, footer, sidebar
├── partials/                        content, entry-meta, page-header, comments, …
├── components/                      alert
└── signatures/                      sig-1|2|3 × light|dark (static HTML)
```

`template-legal.blade.php` is worth calling out as the pattern to copy when content should stay ordinary editable Gutenberg rich text rather than becoming a block: it derives its anchors and sticky table of contents at render time from the `h2`s in the content (`App\Support\DocumentOutline`), so adding a section to a legal document adds it to the navigation for free — nothing is duplicated into fields.

## Support classes

| Class | Does |
| :--- | :--- |
| `BlockDefaults` | Centralised demo content, attachment mapping, sanitisation |
| `DocumentOutline` | Heading extraction → anchors + TOC (used by the legal template) |
| `MediaLibrary` | Attachment lookup helpers for blocks |
| `Pattern` | Pattern registration helpers |
| `ReadingTime` | Article reading-time estimate |

## Front-end behaviour

- Core block CSS (`wp-block-library`, `classic-theme-styles`, `global-styles`) is dequeued on the front end; `should_load_separate_core_block_assets` is forced off.
- Livewire's script is deferred and **lazily cloned** from a `<template id="rl-livewire-scripts">` when an island approaches the viewport (`resources/js/app.js`), so pages without Livewire components pay nothing for it.
- Images are converted to WebP on the fly. Palette-mode PNGs break that conversion (`imagewebp(): Palette image not supported`) — re-encode to truecolor before sideloading.

## Verification before you call a page done

```bash
# Every block parses; no orphan raw HTML inside container blocks
wp eval 'foreach (parse_blocks(get_post(ID)->post_content) as $b) { if ($b["blockName"] === null && trim($b["innerHTML"]) !== "") { echo "INVALID: ".substr(trim($b["innerHTML"]),0,80)."\n"; } }'
```

Any block with `blockName === null` carrying non-whitespace `innerHTML` inside a container is an error and must be eliminated before delivery. Take a screenshot and compare against production — full-page screenshots of very tall pages (20000px+) render too small to catch visual bugs, so use viewport-scale or per-element captures.

Pattern and block grammar is also covered in CI by `tests/Unit/GutenbergBlocksAndPatternsTest.php`.

### Removed 2026-09-15

`benefits-guarantee`, `live-call` and `product-hero` were deleted after an audit of both the
codebase and the database found no published content or code referencing them. `live-call`'s
functionality lives in the `/live-call/connect` route (Livewire + the Scheduling domain), not
in a block; `product-hero` was superseded by the per-page hero blocks above.

Note when auditing usage: a block reached through a `BlockDefaults::render*` helper has its
slug only inside `BlockDefaults`, so grepping patterns for `acf/<slug>` alone under-reports
and will call a well-used block unused.
