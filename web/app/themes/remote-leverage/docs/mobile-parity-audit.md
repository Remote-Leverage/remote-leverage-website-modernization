# Mobile parity audit — v2 vs production

**Run 2026-09-15.** All 54 in-scope URLs, both sides, Playwright + `channel: 'chrome'` at
**390×844** (`isMobile`, `hasTouch`, iPhone UA). Every page was prepared before measuring:
`img.loading='eager'`, `img.decoding='sync'`, a full top-to-bottom scroll to trigger lazy
assets, `.elementor-invisible` stripped, then `await img.decode()` on every image.

This complements [PAGE-MIGRATION-STATUS.md](../../../../../PAGE-MIGRATION-STATUS.md) §5, whose
verification was **desktop 1440px only**. Mobile had never been checked.

---

## 1. The headline finding: nothing structural was broken

Across all 54 pages, at 390px:

| Check | Result |
| :--- | :--- |
| Horizontal overflow (`scrollWidth > clientWidth`) | **0 pages** |
| Broken images (`naturalWidth === 0`, tracking pixels excluded) | **0 pages** — 2,813 images checked |
| Elements wider than the viewport and *not* clipped by an ancestor | **0 pages** |
| Overlapping text (per-line rects) | **0 pages** |
| Text below 12px | fewer than production on **every** page |

The detectors were validated against injected controls (a 600px unclipped div, an `<img>` at a
dead URL, two overlapping absolutely-positioned spans) — all fired; a 1200px div inside an
`overflow-x:hidden` parent was correctly skipped. A clean sweep from an unvalidated detector is
indistinguishable from a broken one, so this matters.

What *was* wrong was a single systemic class of bug, below.

## 2. Fixed: content rendered underneath the fixed header

The site header is `position: fixed` (90px on the CTA-only landing header, 81px on the standard
one), so it reserves no layout space. Heroes that relied on incidental spacing to clear it did
not clear it once the content grew — which is what happens at phone widths.

**10 of 54 pages rendered their main headline underneath the header.** Four components:

| Component | Was | Now | Pages |
| :--- | :--- | :--- | :--- |
| `hire-va-hero` | `pt-6 sm:pt-8`; desktop only escaped because the grid's `my-auto` happened to leave ~96px of centring slack, which collapses to 0 on mobile | `pt-24 lg:pt-8` | `hire-va-4`, `hire-for-less` |
| `consult-landing-hero` (gradient variant) | `py-16` — headline under the header at **390, 700 *and* 1440** | `pt-28 pb-16` | `hire-va`, `hire-va-isolated-form`, `1monthonus` |
| `consult-landing-hero` (dark variant) | `py-14 sm:py-16 lg:py-20` — under at every width | `pt-28 pb-14 sm:pb-16 lg:pt-32 lg:pb-20` | the 3 LATAM variants, `hire-va-email`, `1monthonus-flp`, `hire-real-estate-…-flp` |
| `contractor-payments-hero` | `pt-14` below `lg` | `pt-28` | `contractor-payments` |
| `partner-hero` under the overlay header | headline at 64px against an 81px header | `.rl-header-over-hero .rl-partner-hero { padding-top: 7rem }` | `remote-leverage-x-lano`, `-oyster` |

**Verified: 0 of 54 pages have a heading under the header, at 390 / 700 / 1024 / 1440.**
Clearances now run 22–136px.

> **Note this was not a mobile-only bug.** Both `consult-landing-hero` variants collided at
> 1440px too (−5px and −10px). The desktop screenshot diffs never caught it because a headline
> tucked under a transparent header still *looks* like a headline in a full-page capture.

## 3. Fixed: `/ecommerce-virtual-assistant/` shipped an invisible header

`HeaderMode::DARK_HERO_BLOCKS` matched `acf/partner-hero` unconditionally, so the page got
`rl-header-over-hero` — which absolutely-positions the header, makes it transparent, inverts the
logo to white and turns every nav link white. But that block has a `tone` field, and this page
passes `tone => light` (production's pale `#F4F6FC` band). The result was a white logo and white
nav on a near-white hero: **the entire header was invisible, on desktop as well as mobile.**

`HeaderMode` now reads the block's own `tone` before deciding. The tone sits in the block
comment's ACF payload, which runs past the 2000-char opening window, so it is read from the full
content — `tests/Unit/HeaderModeTest.php` pins that case specifically, along with "a light hero
further down the page is still irrelevant".

## 4. Fixed: the mobile footer was 1,644px

The footer's `grid-cols-1` stacked all four cells vertically on phones, making it **taller than
most pages' own content** and 4× production's 396px mobile footer.

Now `grid-cols-2` below `md` (brand and contact cells span both columns; the five social buttons
do not fit a half column at 390px), with tightened phone padding. **1,644px → 1,222px on every
page**, no content removed.

The brand copy was deliberately **not** hidden on mobile: it is keyword-rich and mobile-first
indexing discounts `display:none` text.

Computed values confirm the change is phone-only:

| Width | Columns | Gap | Padding | Legal `margin-top` |
| :--- | :--- | :--- | :--- | :--- |
| 1440 | 4 | 32px | 80px | 64px | ← identical to before |
| 768 | 2 | 40px | 64px | 64px | ← identical to before |
| 390 | 2 *(was 1)* | 24/40px | 40px *(was 64)* | 40px *(was 64)* |

## 5. Fixed: hero stacking order on the landing pages

Production stacks the booking card **above** the reassurance checklist on phones for
`hire-va-4`, `hire-for-less`, `hire-va-6` and `hire-va-1st-month-free`. Ours put the checklist
first, pushing the form's submit button below the fold (form input at y=688 in an 844px
viewport).

**Production is not consistent about this** — `/sales-talents/` stacks checklist-first — so this
is an option on the block, not a blanket change: `mobile_order` (`checklist-first` default,
`form-first` opt-in), set in `patterns/hire-va-4-hero.php` and
`resources/patterns/hire-va-campaign.php`.

The default path emits the original two-child markup untouched. Emitting the split structure
unconditionally moved `/spanish/`'s vertically-centred headline by 6px; branching on the option
removed that. **Desktop measured pixel-identical (headline, form and checklist positions plus
page height) across all 9 pages using this hero, before vs after.**

---

## 6. What looked like a defect and is not

Four things that will be re-discovered by anyone repeating this audit. They cost real time here.

### Page-height ratios are dominated by chrome, not content
`/recruiterchecklists/` reads **271% of production**. That is entirely our header and footer:
production's checklist and landing pages ship **no header and no footer at all**. Subtract
chrome and our body content is **564px against production's 844px** — we are *shorter*.
Compare `scrollHeight − header − footer`, never raw `scrollHeight`.

### Production hides content on mobile, including commercial content
`/services/` at 390px shows only "$2,000 One time payment". At 1440px it also shows the whole
bundle table — 3 VA/$12,000, 5 VA/$17,500, 7 VA/$23,000, 10 VA/$30,000, and the "save $2,700"
annual plan. Production hides all of it from phones.

**Decision (2026-09-15): v2 keeps that pricing visible on mobile.** Production's mobile omission
of commercial content is treated as a production bug, not a spec. This extends the existing
`/services/` call in `patterns/services.php` — reproduce the maintained desktop stack, not the
stale mobile one, whose prices differ ($300/month vs $400/4 weeks).

### "Missing" pricing is a thousands separator
A text diff flags `Earn $1000 …` as missing from `/referral-program/`. Our copy says
**`$1,000`**. Same for `/affiliate-program/`. **No pricing is missing on mobile anywhere.**
Compare numeric values, not formatted strings.

### Heading-level diffs are mostly markup, not content
`/store/`'s "3 Contractors:" / "5 Contractors:" are present — as `<strong>`, not headings.
`/referral-program-thank-you-page-deposit/`'s "Sign up for the**Referral Program**" reads as a
missing space only because `<br>` contributes no whitespace to `textContent`. Add CLAUDE.md's
existing known-benign list (stat values as spans, step numerals as spans, Calendly headings
absent pre-load, Elementor concatenating A/B variants).

---

## 7. Method traps worth keeping

**Concurrency against the local server fabricates defects.** At 4 concurrent Playwright workers,
35 of 54 pages returned gateway error pages (no viewport meta → the 980px fallback) and
`/sales-talents/` had all 180 image requests fail — producing a confident, entirely false list
of broken images and overflow. `curl` returned 200 throughout. **Assert per-page HTTP status and
the presence of viewport meta, and keep concurrency at 2.** A desktop-height sweep run
alongside other browser work in this session produced rows reading `local: 900` — exactly the
viewport height, i.e. a blank page — and was discarded for the same reason.

**`getBoundingClientRect()` on a wrapped inline element is the union of its line boxes.** This
generated 55 phantom text overlaps across 20 pages: a "Privacy Policy" that wraps onto two lines
reports a box spanning both, which appears to overlap a neighbour it never touches. Use
per-line `getClientRects()` pairs.

**A leaf-only element walk misses headings that contain `<br>`.** The first occlusion scan
reported 4 affected pages; scanning the first `h1,h2` regardless of child elements found **10**.
`<br>` is an element, so `h1 > br` is not a leaf.

---

## 8. Not changed, and why

- **Design-system tokens that differ from production's raw styling.** `/recruiterchecklists/`
  renders 48px solid `#342567` rows where production has 89px `#0066CC → #0052A3` gradients.
  These pages were deliberately migrated into the design system and the difference exists on
  desktop too — it is not a mobile regression. Decision 2026-09-15: the design system wins.
- **Sub-12px text.** Production has *more* of it than we do on every single page, so our type is
  already ahead of the parity bar. It remains an accessibility item (see
  [performance-baseline.md](performance-baseline.md)), not a parity defect.
- **Tap targets under 32px.** 20–66 per page were counted, but the count is dominated by inline
  text links inside paragraphs, which are exempt from WCAG 2.5.8. Not validated as defects; needs
  a proper pass that distinguishes inline links from controls before anyone acts on it.

## 9. Gates

`vendor/bin/pest` — 787 passed (3,701 assertions), including 6 new in
`tests/Unit/HeaderModeTest.php`. `vendor/bin/pint --test` — passes on every file changed here.
`npm run build` — clean.
