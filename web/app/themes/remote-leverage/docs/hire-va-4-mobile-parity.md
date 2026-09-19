# /hire-va-4/ mobile parity — production vs v2

**Run 2026-09-18.** Playwright + `channel: 'chrome'`, iPhone UA, `isMobile` / `hasTouch`, at
**390×844** and **375×844**. Desktop re-checked at **1440×1000** for regressions.

**Result: 1:1 on mobile.** Identical page height, all 33 headings at identical coordinates, and
a zero-pixel image diff once the two capture artifacts below are controlled for.

---

## 1. What was actually wrong

The page was never disconnected from its pattern. `post_content` on both sides is the single
reference, exactly as intended:

```
<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-full"} /-->
```

Production `/hire-va-4/` is also **not** an Elementor page — it is already served by this Sage
theme, and both sides render the same 9 top-level sections in the same order with the same
content. Content parity was never the problem.

The difference was **nine per-instance mobile design overrides that existed only in
production's database.** Someone tuned this page's mobile rhythm through the block editor's
**Design → Custom CSS** box (`App\Support\BlockDesign`), which stores the CSS in the block's
`attrs.data` inside `post_content`. Because the page renders from a pattern, that editing
happened on production's copy and never reached git — so the pattern in this repo rendered the
blocks at their untuned defaults.

That is the drift `CLAUDE.md` § "Page content lives in patterns" warns about, and it was one
database refresh away from being lost on production too.

### The measured symptom

At 390px, v2 was **625px taller** than production. The drift was *entirely* at the section
seams — zero drift accumulated **inside** any section:

| Seam | v2 was taller by |
| :--- | ---: |
| Hero (internal tightening + roles opening) | 142px |
| Roles → Why hire | 48px |
| Why hire → Client Reviews | 24px |
| Reviews → Guarantee | 64px |
| Guarantee → Hiring Process | 24px |
| Process → Skip the Hiring Headache | 80px |
| Comparison → FAQ | 64px |
| FAQ → Booking footer | 80px |
| Booking footer tail | 23px |
| Hero top + form | 76px |
| **Total** | **625px** |

Each figure reconciles exactly against the production CSS recovered below.

---

## 2. The fix

The overrides are now in git, as
[`BlockDefaults::HIRE_VA_4_MOBILE`](../app/Support/BlockDefaults.php), attached to each block
instance by the nine `hire-va-4-*` patterns via `BlockDefaults::hireVa4Mobile()`.

`BlockDesign::render()` reads `attrs.data`, so a pattern supplying this is indistinguishable
from an editor having typed it into the Design panel — same code path, same output.

### Why not just retune the block views

Because these blocks are shared. `acf/booking-footer` renders on **12** patterns and
`acf/guarantee-card` on **7**. Changing their mobile padding to suit this one page would
silently reflow every other page using them — the design-system fork `CLAUDE.md` § "Reuse
before you build" exists to prevent. `BlockDesign` scopes each rule to a generated
`.rl-d-{hash}` class on that one instance, so nothing leaks.

### Verification that the CSS is production's, not an approximation

Each string is transcribed from what production serves, then compiled back through
`BlockDesign::compile()` and compared to production's emitted `<style>` blocks **byte for
byte**:

| Section | Override | Compiles identical |
| :--- | :--- | :--- |
| Hero | hide badge row, centre h1, 2-col logo grid, tighter form padding, CTA colours | yes |
| Roles | `padding: 32px / 40px` | yes |
| Why hire · Guarantee · Comparison | `padding: 40px / 40px` (one shared rule) | yes |
| Testimonials | `margin-bottom: -56px` | yes |
| Process steps | `margin-bottom: -56px`, container `margin-top: 24px` | yes |
| FAQ | `margin-top: -40px; margin-bottom: -56px` | yes |
| Booking footer | first child `padding: 40px / 40px` | yes |

The local page now emits **9 style blocks in the same order with the same rules**, across 7
distinct scope classes — and the three bands that share a payload collapse to a single class,
exactly as production's `rl-d-216a7b13` does.

---

## 3. Results

### Mobile 390px

| Check | Before | After |
| :--- | :--- | :--- |
| Page height (production 13,782px) | 14,407px (+625) | **13,782px (0)** |
| Max cumulative heading offset | 602px | **0px** |
| Headings differing in x / width / height / type | — | **0 of 33** |
| Full-page pixel diff | — | 0.106%, 1 band (see below) |

### Mobile 375px

Production 13,905px, v2 **13,905px**. All 33 headings identical in y, x, width, height and type.

### Desktop 1440px — no regression

Production 8,714px, v2 **8,714px**. Same two artifact bands, nothing else.

### The two diff bands are capture artifacts, not design differences

Both were proven to zero rather than assumed:

1. **Site header (mobile, y 0–89).** Production's *full-page* capture stitches a white strip
   where the sticky header sits. A viewport clip of the same region diffs at **0 px**, and the
   band disappears entirely once transitions are frozen. Nothing is different.
2. **Trust-logo marquee (y 776–799 mobile, y 914–941 desktop).** Both sides carry the same
   `animate-marquee-logos` element, same `marquee-left` animation, same 35s duration, same y,
   and the identical 18-logo sequence. Only the animation phase at shutter time differs.

   Proven at mobile: pinned to `transform: none` on both sides the band diffs at **0 px**, and
   the strip geometry matches exactly — 2,234px wide, 40px gap, identical per-logo offsets. The
   desktop band is the same element (2,795px, 64px gap there) and the same cause; it was not
   separately pinned, because desktop page height already matches production exactly and the
   band is the only one left.

A third desktop band (booking-footer phone field, y 7974–7987) diffed at **0 px** on
re-capture — a transient focus ring.

---

## 4. Checks

All three CI gates, from the theme directory:

```
npm run build          exit 0
vendor/bin/pest        1568 passed (7062 assertions)
vendor/bin/pint --test passed
```

`tests/Unit/HireVaHeroLcpTest.php` was updated in the same change. It asserted that
`patterns/hire-va-4-hero.php` contains the literal string `<!-- wp:acf/hire-va-hero `, read via
`file_get_contents`. The pattern now builds that comment through `BlockDefaults::patternBlock()`
so it can carry the design payload, so the block name is no longer literal source text.

The test now evaluates the pattern and asserts on its **output**, which is what WordPress
actually registers — `WP_Block_Patterns_Registry` stores the file's output and
`PageChrome::patternContent()` reads it back from there. The old form tested the authoring
style; the new one tests the contract. Confirmed at runtime: both production and v2 emit
`fetchpriority="high"` on the hero image, so the LCP treatment this test guards is intact.

---

## 5. Reproducing

```bash
# from the theme directory
node docs/visual-parity/capture.mjs --filter=hire-va-4 --viewport=mobile --force
open docs/visual-parity/index.html
```

When diffing this page, freeze animation before drawing conclusions — the logo marquee will
otherwise report a false ~0.1% every run:

```js
await page.addStyleTag({ content: `*, *::before, *::after {
    animation-play-state: paused !important;
    animation-delay: -1s !important;
    transition: none !important;
}` });
```

Freezing transitions also removes the sticky-header stitching artifact in full-page captures,
which is worth knowing before chasing it on any other page.
