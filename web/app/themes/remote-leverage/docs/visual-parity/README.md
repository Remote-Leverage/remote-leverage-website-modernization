# Visual parity captures

Full-page screenshots of every migrated v2 page next to its production homologue, at desktop
and mobile widths. Use it to answer "does this page still match production?" without
re-deriving the capture rules every time.

## Run it

```bash
# from the theme directory
npx playwright install chrome          # once
node docs/visual-parity/capture.mjs    # ~322 captures, roughly 1-2 hours
open docs/visual-parity/index.html     # side-by-side viewer
```

The run resumes: anything already in `shots/` is skipped, so a failed or interrupted run just
needs re-invoking. `--force` re-shoots everything.

```bash
node docs/visual-parity/capture.mjs --filter=case-study   # only matching slugs
node docs/visual-parity/capture.mjs --side=local          # one side
node docs/visual-parity/capture.mjs --viewport=mobile     # one viewport
node docs/visual-parity/capture.mjs --concurrency=6       # default 3
```

Production is the slow half and occasionally drops a connection; the script retries twice with
backoff. If you want to go faster, raise concurrency on `--side=local` (your machine) rather
than on production.

## What's in git

| Path | Tracked | Why |
|---|---|---|
| `capture.mjs` | yes | the scraper |
| `manifest.json` | yes | page inventory and local ↔ production mapping |
| `index.html` | yes | side-by-side viewer |
| `shots/` | **no** | ~230MB of PNG, regenerated in one command |

The pixels are deliberately not committed. They are large, they change on every deploy, and
they are cheap to rebuild — the same reasoning that keeps the VSL out of git. A fresh clone
gets the harness; run it to get the images.

## Viewports

- **Desktop 1440px** — what `docs/design-system.md` and the measurements throughout the theme
  are recorded at.
- **Mobile 390px** — iPhone 12–15, comfortably inside Elementor's 767px mobile breakpoint, so
  production serves its mobile stack.

Mobile matters more than it looks on this site: **production is not self-consistent across
breakpoints.** `/services/` ships separate desktop and mobile Elementor stacks
(`elementor-hidden-mobile` / `elementor-hidden-desktop`) and the mobile one carries *different
prices*. Where the two disagree, reproduce desktop and say so — see CLAUDE.md.

## Reading the output

`index.html` pairs production (left) with v2 (right), toggles desktop/mobile, and can filter to
pages whose two sides differ by more than 10% in height. That height gap is the cheap version of
the geometry check CLAUDE.md insists on: **a blank area and a correctly-rendered white card are
the same pixels**, so a screenshot that looks right is not evidence. The `/services/` miss was
caught because card heights of 591px and 810px could not be explained by ~325px of content, not
because anything looked wrong. `shots/results.json` carries the per-capture height, image count
and broken-image count for the same reason.

## Three differences that are NOT drift

Verified site-wide on 2026-09-15. Don't file these as parity bugs:

1. **Typeface.** Production uses Gilroy; v2 standardised on Inter Display / Inter Variable
   theme-wide. Every heading will differ in shape and metrics.
2. **Footer.** Production serves a slim footer (email, address, three links) on *every* page
   including the homepage. v2 ships a mega footer with Talents / Products / Careers / Resources
   columns. A deliberate v2 decision, not a case-study or per-page regression.
3. **Page background.** Production's body is `#FFFFFF`; v2's is `#F4F6FC` (`--color-bg-light`),
   applied site-wide.

Container width is a fourth, narrower case: production's Elementor pages measure 1240–1320px,
and migrated pages deliberately use the canonical **1380px**. Per CLAUDE.md rule 1, container
width is the one value never measured off production.

## Inventory

81 surfaces: 54 pages, 23 case studies, 3 partner pages, 1 archive. 80 have a production
homologue at the same path — verified, and verified not to be reached through a redirect.

One local-only surface: `/partners/lano/` is a v2 partner-hub route with no production
equivalent; production covers that partnership at `/remote-leverage-x-lano/`, which is captured
separately as its own page.

## Known issue this surfaced

`/case-study/` renders only **10** of the **23** published case studies. `archive-case_study.blade.php`
loops the main query, which respects WordPress's default `posts_per_page = 10`, and the template
has neither pagination nor a `pre_get_posts` override. Thirteen case studies are live but
unreachable from the index. Production shows all 21 of its own. The archive capture in `shots/`
reflects the bug, not the content — the posts exist.
