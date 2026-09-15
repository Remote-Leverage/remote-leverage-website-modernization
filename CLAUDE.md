# Remote Leverage v2 — project instructions

Roots Bedrock + Sage 10 + Acorn, Tailwind v4, ACF Composer blocks.
Theme root: `web/app/themes/remote-leverage`. Run all theme commands from there.

## Reuse before you build

The theme ships 56 ACF blocks. **Before writing markup in a pattern, find out what already
exists.** Skipping this is the most common and most expensive mistake in this repo: it forks
the design system, and a fix to the block never reaches the hand-written copy.

1. Read [`docs/block-inventory.md`](web/app/themes/remote-leverage/docs/block-inventory.md) —
   generated, maps every block to the production section it renders, its fields and usages.
2. Confirm against source:
   ```bash
   grep -rn "Production" resources/views/blocks/*.blade.php        # what renders which section
   grep -oE "acf/[a-z0-9-]+" patterns/comparison-full.php | sort -u  # how a real page composes
   ```
3. Production having a *variant* of an existing block is not grounds for a second block —
   add an option and default it to current behaviour (`acf/feature-cards` has
   `inset`/`flush`; `acf/talent-grid` has `grid`/`row`).
4. Only then create a new block. Inline markup in a pattern is the last resort and must carry
   `// @bespoke: <blocks checked, why none fit>`. `tests/Unit/PatternBlockReuseTest.php`
   fails the build without it.

Re-check **per section, not per project** — a block that was wrong for one page is often right
for the next. Regenerate the index after adding blocks or patterns:

```bash
wp acorn blocks:inventory
```

Full workflow for migrating a production page:
[`.agents/skills/page-migration/SKILL.md`](web/app/themes/remote-leverage/.agents/skills/page-migration/SKILL.md).

## Page content lives in patterns, not the database

Author a page as a `patterns/<slug>.php` file in git, then point the page at it with a single
pattern reference:

```
<!-- wp:pattern {"slug":"remote-leverage/<slug>"} /-->
```

Never paste expanded block markup into `post_content` — it drifts from the pattern and is lost
on a database refresh. Shared partials that are *not* patterns go in `resources/patterns/`
(WordPress scans `patterns/` recursively and rejects headerless files there).

**The one exception** is a page created through the MCP `app/clone-page` ability, which writes
expanded markup on purpose so marketing can ship a campaign page without a deploy. Those pages
are database-resident and *will* be lost on a refresh — that is the accepted trade, not a bug.
When one earns a permanent place, promote it back into a pattern. See
[`docs/ai-mcp-and-sync.md`](web/app/themes/remote-leverage/docs/ai-mcp-and-sync.md).

## Rendering blocks from a pattern

Use the `BlockDefaults::render*` helpers. Repeater data must be ACF-encoded — passing a raw
array as an override is silently ignored and the block falls back to its presets, which is how
a page can ship the wrong content while looking wired up.

```php
BlockDefaults::renderFeatureCards('3', [], $cards, '413/152', 'flush');
```

## Verifying a migrated page

Content parity is not visual parity. Compare rendered screenshots against production:

- Playwright + `channel: 'chrome'` at 1440px.
- Force `img.loading = 'eager'` and scroll the full page before capturing — lazy images and
  lazy CSS backgrounds otherwise read as missing or broken.
- **Do not scroll back to the top before shooting.** Returning to the top re-arms Elementor's
  `.elementor-invisible` entrance animation, so images that had loaded read as blank space
  again. Capture from where the scroll ended, or strip `.elementor-invisible` first. This is
  not theoretical: it silently dropped two photos from a `/services/` capture, and a related
  lazy-load miss produced two wrong conclusions on 2026-09-15 — a section reported "broken on
  production" that merely had a lazy background, and images reported absent that were just
  below the fold.
- **Sanity-check the geometry, not just the picture.** The `/services/` miss was caught because
  card heights (591px and 810px) could not be explained by ~325px of content — not because the
  screenshot looked wrong. A blank area and a correctly-rendered white card are the same pixels.
- **Await decode before capturing.** `decoding="async"` lets Chrome screenshot before images
  rasterise, so a diff reports blank cards on a page that is fine. This produced a phantom "21%
  different" on `/blog/` immediately after the image work — all 180 image URLs returned 200.
  Force `decoding='sync'` and `await img.decode()`; that took the same diff to 0px. Expect this
  on exactly the pages you just made faster.
- Read design tokens off production with `getComputedStyle` rather than estimating.
- Check a section is actually *visible* on production before reproducing it; some are
  `display:none` at every breakpoint.
- Production is not self-consistent. Check for separate desktop and mobile Elementor stacks
  (`elementor-hidden-mobile` / `elementor-hidden-desktop`) before assuming one responsive
  layout — `/services/` ships two, and the mobile one carries **different prices** because
  someone updated the desktop copy and forgot the other. Reproducing the wrong one ships wrong
  numbers. Where they disagree, reproduce desktop and say so.

## Build & checks

Run all three from the theme directory — these are exactly what CI runs
(`.github/workflows/ci.yml`), so if they pass locally the pipeline passes:

```bash
npm run build          # required after editing Tailwind classes in patterns/blocks
vendor/bin/pest        # theme test suite
vendor/bin/pint --test # formatting gate; drop --test to fix
```

**Both Pint configs use the `laravel` preset** — unified on 2026-09-15. They previously
disagreed (root `per`, theme `laravel`), which meant running the root binary over the theme
reformatted it into a state CI rejects; that is how staging broke on 2026-09-15. The root
`pint.json` was switched to `laravel` rather than the theme to `per`, because the root governs
10 PHP files and the theme 562.

There is now one standard, and `vendor/bin/pint --test` from either directory agrees on every
file. Still run the theme's from the theme directory as a habit — that is what CI runs
(`.github/workflows/ci.yml`, `working-directory: web/app/themes/remote-leverage`) — and the
root config still excludes the theme so the two never double-format the same file.

Tailwind scans `patterns/`, `resources/patterns/`, `app/`, and `resources/**/*.blade.php`
(see `@source` in `resources/css/app.css`). A class in a file outside those globs will not exist
at runtime.

## Assets

Page art is **source in `resources/images/pages/<page>/`** (tracked in git) and **generated into
`public/images/<page>/`** (gitignored) by the `themeImages()` Vite plugin in
[`vite/theme-images.js`](web/app/themes/remote-leverage/vite/theme-images.js). Add a new image by
dropping it in `resources/images/pages/` and running `npm run build` — never by writing into
`public/`, which is wiped and regenerated by every deploy.

The plugin preserves directory names and filenames exactly, because `BlockDefaults::pageImg()` /
`themeImg()` / `homeImg()` build those URLs by hand. That is why page art is kept out of
laravel-vite-plugin's `assets` glob, which content-hashes what it touches.

- PNG is re-encoded losslessly, JPEG is copied byte-for-byte. The source raster is only the
  fallback, so there is nothing to gain from recompressing an already-lossy JPEG.
- Every raster also gets a sibling `.webp` — lossless from a PNG (flags and icons band badly
  otherwise), q82 from a JPEG. `BlockDefaults::preferWebp()` serves these.
- Results are cached by content hash in `node_modules/.cache/`; a warm rebuild is a few seconds, a
  cold one tens of seconds. There are 629 source rasters under `resources/images/pages/`, expanding
  to ~1,340 files in `public/images/`, so both timings scale as art is added.

The loose files directly in `resources/images/` are a separate thing: those go through Vite and
are referenced with `Vite::asset()`.

Homepage art resolves from EFS uploads first (`homeImg()`), falling back to the theme. `public/videos/`
is still hand-maintained and untracked — the 64MB VSL does not belong in git.

## Conventions

- Do not commit or push unless asked.
- **Container width is the one thing you do NOT measure off production.** The canonical container
  is `w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8`, and every root `wp:group` declares
  `"layout":{"type":"constrained","contentSize":"1380px"}`. Production's Elementor pages measure
  1260–1320px; migrated pages still use 1380px. `/signedup/` and `/referral-program/` were built
  at production's measured 1260px and had to be corrected on 2026-09-15. Measure production for
  type scale, spacing and colour — never for the container. See `docs/design-system.md` rule 1.
