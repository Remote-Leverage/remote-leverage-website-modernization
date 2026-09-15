# Remote Leverage v2 — project instructions

Roots Bedrock + Sage 10 + Acorn, Tailwind v4, ACF Composer blocks.
Theme root: `web/app/themes/remote-leverage`. Run all theme commands from there.

## Reuse before you build

The theme ships 40+ ACF blocks. **Before writing markup in a pattern, find out what already
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
- Read design tokens off production with `getComputedStyle` rather than estimating.
- Check a section is actually *visible* on production before reproducing it; some are
  `display:none` at every breakpoint.

## Build & checks

```bash
npm run build                 # required after editing Tailwind classes in patterns/blocks
./vendor/bin/pest             # theme test suite
../../../../vendor/bin/pint   # formatting (repo root vendor)
```

Tailwind scans `patterns/`, `resources/patterns/`, `app/`, and `resources/**/*.blade.php`
(see `@source` in `resources/css/app.css`). A class in a file outside those globs will not exist
at runtime.

## Assets

`public/images/` is hand-maintained and **currently gitignored** — see the open question in
`PAGE-MIGRATION-STATUS.md`. `preferWebp()` generates a sibling `.webp` on demand; a truncated or
stale one is served silently, so verify a rendered image matches its source when it looks wrong.

## Conventions

- Do not commit or push unless asked.
- Container width: match production per page (measure it) rather than assuming a global value.
