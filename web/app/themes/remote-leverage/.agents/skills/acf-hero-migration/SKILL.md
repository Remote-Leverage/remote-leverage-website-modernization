---
name: acf-hero-migration
description: >-
  Validate a migrated ACF Composer block/page against its legacy Elementor snapshot, and convert
  an existing ACF block between plain ACF fields and Gutenberg InnerBlocks editing. Use when
  asked to check whether a migrated block/page matches production or the legacy snapshot, when
  asked to make a block's content editable in place as native blocks, or when adding a
  field-driven toggle between two authoring modes on an ACF Composer block.
---

# ACF Block ↔ Legacy Parity & InnerBlocks Conversion

This skill covers two related jobs that tend to happen back to back: (1) checking whether a
migrated block/page is a faithful copy of the legacy Elementor design, and (2) converting part of
an ACF Composer block's content from plain ACF fields to Gutenberg InnerBlocks (or adding a toggle
between the two). Both are easy to get subtly wrong in ways that look fine until someone opens the
actual WordPress editor or the live front end — this skill exists because several of those wrong
turns happened in the session that produced it.

---

## 0. Confirm before applying (read this first)

**Before any of the following, present the plan and wait for explicit confirmation — do not just
apply and report afterward:**

- Removing or restructuring ACF fields on a block that may already have published content relying
  on them.
- Breaking a documented repo convention (e.g. `docs/ab-testing.md` calls `acf/experiment` "the
  theme's only InnerBlocks block" — adding a second one is a real convention change, not a
  drive-by refactor).
- Using any ACF/Gutenberg API surface that can't be exercised locally. This repo/session
  typically has no `vendor/`, `node_modules/`, or `wp` CLI available — `php -l` only catches
  syntax errors, never behavior. Say so explicitly rather than implying it was tested.
- Making an architecture-level call (e.g. "should this be a toggle field, a hard cutover, or two
  separate blocks?") that has real trade-offs. Propose options, don't silently pick one.

Getting this step wrong is exactly how the InnerBlocks conversion in this repo went out with
JS-object-literal syntax that ACF silently doesn't parse (see §3) — it was never runnable here,
and nobody was asked "this can't be verified locally, are you OK shipping it that way?" before it
landed on a real page.

---

## 1. Validating a migration against the legacy snapshot

Per the root `CLAUDE.md`: production Elementor is gone from the web since the 2026-09-19 cutover.
The only reference is the local, gitignored `legacy-snapshots/pages/<slug>.html` (per-checkout,
~946MB — see `legacy-snapshots/README.md`). If it's missing, say so; don't compare against the
live site and call it "production."

**Don't stop at text/visual comparison — diff the actual DOM.** Text content matching is not
enough to catch an element that was *added* during migration and never existed in the original:

1. Open the snapshot as a `file://` URL in the browser tool. Expect it to render unstyled/imageless
   (sibling asset loading is sandboxed) — that's fine, you're reading structure and text, not
   pixels.
2. For any element you suspect might be new (a graphic, a badge, an icon cluster), inspect it
   directly: `document.images`, or `element.outerHTML` on the specific node, not just
   `get_page_text()`. A missing element shows up as absent DOM, not as different text.
3. Cross-check against git history. A component's *original* fallback pointing at generic stock
   imagery (e.g. `images.unsplash.com/photo-...` as a default) is a strong signal it was invented
   during migration, not sourced from the legacy design — even if a later commit "cleaned it up"
   by swapping in real theme assets. `git log --oneline -- <file>` plus reading the first commit's
   version of the file (`git show <first-commit>:<path>`) surfaces this quickly.
4. Confirm the element isn't reused elsewhere in the theme as an established pattern (grep for the
   same asset/class name in sibling blocks) before concluding it's a one-off addition.

Report findings as: what's in the legacy version, what's in the current version, and which
specific commit introduced the difference — not just "these look different."

---

## 2. Deciding what becomes InnerBlocks vs. what stays an ACF field

A single ACF block only gets **one** `<InnerBlocks />` region (this is a real technical limit, not
a convention). Before converting anything, decide per element:

- **Good InnerBlocks candidates**: linear flow content — headline, subtitle, a single CTA button.
  These read naturally as a stack of `core/heading` / `core/paragraph` / `core/buttons`.
- **Keep as ACF fields**: anything with fixed/absolute positioning relative to other elements
  (an image overlaid with a badge composite, icons combined with text in bespoke markup a native
  block can't reproduce), and anything that sits in a *second* location the single InnerBlocks
  region can't reach (e.g. a label below a two-column grid when the grid's left column is already
  the InnerBlocks area).

If preserving an exact hand-built composite (icon + image + text in one unit, or a
pixel-specific arrow-in-a-circle button) is a project requirement, don't try to reconstruct it as
native blocks — it will either drift from the design or need real CSS engineering. Keep it as a
field, or reuse an existing block-style variation if one already matches close enough (see §6).

---

## 3. The InnerBlocks JSX syntax is not JavaScript

This is the mistake that shipped first: ACF's block-level `jsx` support (`supports.jsx => true`,
already used by `acf/experiment` in this theme) does **not** mean you write real JSX/JS
object-literal syntax into the Blade view. Writing this looks plausible and fails silently —
ACF just prints the tag as literal text on the page, both in the editor and the front end,
with no error:

```blade
{{-- WRONG — this is JS object-literal syntax, ACF does not parse it --}}
<InnerBlocks
    template={[
        ['core/heading', {level: 1, content: 'Headline'}]
    ]}
    templateLock="all"
/>
```

The real mechanism: `<InnerBlocks>` is a literal HTML tag, and `template` / `allowedBlocks` are
ordinary HTML attributes whose *value* is a JSON string, built in PHP with `wp_json_encode()` and
escaped like any other attribute (Blade's `{{ }}` does this automatically):

```php
// In the block's with():
'ctaTemplate' => wp_json_encode([
    ['core/heading', ['level' => 1, 'className' => '...', 'content' => 'Headline']],
    ['core/paragraph', ['className' => '...', 'content' => 'Subtitle text']],
    ['core/buttons', [], [
        ['core/button', ['text' => 'CTA', 'url' => '#anchor', 'className' => '...']],
    ]],
]),
'ctaAllowedBlocks' => wp_json_encode(['core/heading', 'core/paragraph', 'core/buttons', 'core/button']),
```

```blade
{{-- In the Blade view --}}
<InnerBlocks
    template="{{ $ctaTemplate }}"
    allowedBlocks="{{ $ctaAllowedBlocks }}"
    templateLock="all"
/>
```

`templateLock="all"` prevents editors from adding/removing/reordering pieces, which is normally
what you want when the template exists to preserve a specific layout rather than to give
open-ended freedom.

**Symptom if you get this wrong**: the raw tag/attribute text shows up literally on the page,
in both wp-admin and the front end. That specific symptom means "ACF never recognized the tag,"
not "something rendered incorrectly" — go straight to checking the attribute syntax.

---

## 4. Self-closing vs. open/close block comments

A block that uses InnerBlocks needs its serialized comment to be an **open/close pair**, not
self-closing — the inner content sits between the tags as real, separate block comments:

```php
// Self-closing (no InnerBlocks, or InnerBlocks unused in this instance):
'<!-- wp:acf/your-block '.$attrs.' /-->'

// Open/close (InnerBlocks in use — inner content is real WordPress block markup):
'<!-- wp:acf/your-block '.$attrs.' -->'.$innerBlocksMarkup.'<!-- /wp:acf/your-block -->'
```

If a `BlockDefaults::render*()` pattern helper hand-writes the self-closing form for a block whose
current mode actually uses InnerBlocks, the inner content is silently dropped — no error, the
block just renders without it. `docs/ab-testing.md` already calls this out for `acf/experiment`;
the same rule applies to any other InnerBlocks-capable block. If the block supports **both** modes
via a toggle field (see §5), the render helper must branch: self-close in field-driven mode, use
the open/close pair only when InnerBlocks mode is selected — don't always emit inert, unrendered
InnerBlocks content regardless of which mode is active.

---

## 5. Adding an ACF-fields ↔ InnerBlocks toggle

To let editors choose per-instance rather than committing the whole block to one mode:

1. Add a `select` field (e.g. `block_type`) with the two choices, placed first in `fields()`.
   Decide the default deliberately — ask the user rather than assuming which mode should be
   default (see §0).
2. Re-add the legacy ACF fields (headline/subtitle/button text & url, etc.), each with
   `->conditional('block_type', '==', 'acf')` chained immediately after its own `addX()` call —
   `conditional()` applies only to the single preceding field, it does not cascade (see
   `FeaturedPostsBlock.php` for the established pattern in this repo).
3. In `with()`, always compute both representations (the plain field values *and* the
   `wp_json_encode()`'d InnerBlocks template) — cheap, and lets the Blade view branch cleanly.
4. In the Blade view, `@if ($blockType === 'acf') ... plain markup ... @else <InnerBlocks ... />
   @endif`.
5. **Know the limit of this fix**: changing a `select` field's default, or the InnerBlocks
   `template` default, only affects *new* block insertions. Content already saved in
   `post_content` keeps whatever it was saved with — there is no retroactive migration. Say this
   explicitly rather than implying the toggle fixes already-published pages.

---

## 6. Reuse existing block styles before hand-rolling CSS

Per this repo's broader "reuse before you build" rule: check `resources/css/app.css` for existing
`core/button` style variations (`is-style-pill-purple` etc.) before writing new pseudo-element/mask
CSS to reproduce a custom button look. A close-enough existing token (slightly different hex, or
font-weight) is usually the better trade than forking the design system for one block — but name
the deviation explicitly when you make that call, don't silently accept a mismatch nobody signed
off on.

---

## 7. The `.wp-block-heading` / `.wp-block-button__link` cascade trap

Core Gutenberg blocks auto-add their own wrapper class (`wp-block-heading`, `wp-block-button__link`,
etc.) alongside any custom `className` you set. WordPress's global styles can define a color rule
on that same class at the same specificity as a plain Tailwind utility class — and when they tie,
**source order decides**, not which one "looks more specific." This is why a custom `text-white`
can render correctly in the block editor (the editor iframe doesn't load the same global-styles
cascade) but render as dark/invisible text on the live front end.

**Fix**: force the color utility with Tailwind's important modifier. In **Tailwind v4** the syntax
is a **trailing** bang — `text-white!` — not the v3 leading form (`!text-white`). Confirm the
Tailwind major version in `package.json` before assuming which syntax applies; using the wrong one
is itself a silent no-op (the class just doesn't exist in compiled CSS).

If the symptom is "looks right in wp-admin, wrong on the live page, only for text/color, only on
elements ACF or core blocks add their own wrapper class to" — this is very likely the cause, check
here before looking elsewhere.

---

## Quick diagnosis table

| Symptom | Likely cause | Where to look |
| --- | --- | --- |
| Literal `<InnerBlocks ...>` text shown on page (editor and front end) | JSX/JS syntax instead of `wp_json_encode()` HTML attributes | §3 |
| InnerBlocks content missing entirely, no error | Block comment is self-closing but should be open/close | §4 |
| Correct in wp-admin editor, wrong/invisible color on live front end | `.wp-block-*` global-styles class winning a specificity tie by source order | §7 |
| Changed a field default or template but an existing page didn't change | Defaults only seed new insertions, not saved `post_content` | §5.5 |
| Element present on the migrated page but visually odd/unexplained | May be migration-invented content with no legacy counterpart | §1 |
