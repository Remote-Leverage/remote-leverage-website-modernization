# Comp-matching tools

Pixel tooling for making a page match a design comp when the comp is a PNG and the page is
live. Everything here reads PNGs directly (pure `zlib`, no image libraries are installed) and
renders the live page with Playwright from `docs/visual-parity/node_modules`.

Reference comp: `docs/design/hire-va-4.png` — 375 x 13204, mobile, **no site footer**, so never
compare total page height against it.

## Render the current build

```bash
node docs/design/tools/shoot.mjs --out=/tmp/mine.png --width=375
```

Prints the page height and the y of every h1/h2, which is how you locate your section in the
live render. Animation is frozen and images are decoded before the shutter, so two runs of the
same page are byte-identical.

## Find your section

```bash
node docs/design/tools/findmagenta.mjs docs/design/hire-va-4.png   # CTA pills — good anchors
node docs/design/tools/profile.mjs <png> <y0> <y1> <x0> <x1>       # ink runs = text lines, cards
```

## Measure

```bash
node docs/design/tools/gutter.mjs <png> <y>...        # where a card starts/ends on each row
node docs/design/tools/ink.mjs <png> y0:y1:x0:x1 ...  # ink bounding box of a text run
node docs/design/tools/pts.mjs <png> x,y x,y ...      # exact pixel colours
node docs/design/tools/mkcrop.mjs <png> <tag> y0:y1:scale ...   # crop to look at
node docs/design/tools/zoom.mjs <png> <out> y0 y1 x0 x1 <zoom>  # magnify glyphs
node docs/design/tools/pngdiff.mjs <a.png> <b.png>    # differing pixels + bands
```

## How to compare type

Compare the **ink box of one glyph**, not a whole line: line breaks differ, so a full line's
width tells you nothing. A capital `E` gives cap height and stroke width cleanly. Two renders
of the same string at the same size have the same ink pixel count to within a few units.

A string that is *taller but narrower* is a bigger font with tighter tracking. A string the
same height but narrower is the same font with tighter tracking.

## Known traps

- The comp has **no footer**. Height differences of ~450px at the bottom are that, not a bug.
- The comp's gutter is a flat **20px** on every card, every section. The theme ships `px-4`
  (16px). Match the comp inside your own section.
- `hover:` styles never fire on a phone. A card that is `bg-transparent hover:bg-white` renders
  bare on mobile — that is a real defect, not a comp difference.
- Blade's `@class([...])` emits its own `class` attribute. Putting it on an element that
  already has `class="..."` produces two attributes and the browser keeps the first. Merge the
  conditional into the existing string instead.
