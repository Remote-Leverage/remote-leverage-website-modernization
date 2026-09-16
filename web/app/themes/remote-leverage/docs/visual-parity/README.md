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
| `shots/` | **no** | ~700MB of PNG, regenerated in one command |

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
pages whose two sides differ by more than 10% in height. Treat that filter as a way to find
pages worth opening, never as a verdict — see the run notes below for how badly height alone
misranks this site. It is the cheap version of the geometry check CLAUDE.md insists on: **a blank area and a correctly-rendered white card are
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

## What the first full run showed (2026-09-15)

322 captures, 81 surfaces, both viewports.

**Height alone is a misleading measure, and the first pass of this section got it wrong.**
Ranking pages by how far the two sides differ in height put `/services/`, `/payment/`,
`/recruiterchecklists/`, `/saleschecklists/`, `/vaonboardingform/`, `/about-us/` and
`/samples/` near the top — and none of them is a v2 regression. Measuring the *content* in
each capture alongside its height is what separated them:

| page | content vs production | height vs production | what is actually happening |
|---|---|---|---|
| `services` | +198% | +129% | production hides 56% of its content on mobile |
| `payment` | +517% | +84% | production renders 214 characters in total |
| `recruiterchecklists` | +487% | +121% | production renders 239 characters |
| `saleschecklists` | +292% | +120% | production renders 405 characters |
| `vaonboardingform` | +523% | +94% | production renders 214 characters |
| `about-us` | +84% | +98% | v2 carries more copy; height grows in proportion |
| `samples` | — | — | production renders 0 characters at either width |

`/services/` is the cautionary one: production ships separate desktop and mobile Elementor
stacks and its mobile stack drops more than half the page — the dual-stack problem CLAUDE.md
documents, whose mobile copy also carries different prices. Matching production's mobile height
there would mean *deleting content*.

**Use the content-vs-height test, not height alone.** A page is a layout bug only when its
content is roughly level with production's and its height is not. On that test exactly three
pages qualified, and only two of them turned out to be ours to fix:

| page | content | height | outcome |
|---|---|---|---|
| `vapricing` | +12% | +179% | **Fixed.** Talent grid: 8 portrait cards two-across cost 1,246px of a 1,746px hero. `acf/talent-grid` gained a `swipe` layout — a snapping row below `sm`, the unchanged four-across grid from `sm` up. Hero 1,746 → 957px. |
| `home` | +10% | +91% | **Fixed.** Every card deck collapsed to one column on mobile while keeping a full-width image. `acf/feature-cards` and `acf/department-cards` now start at two across, with type stepped down below `sm`. Page 23,397 → 21,312px; desktop unchanged. |
| `store` | +19% | +85% | **Not a bug — do not "fix".** See below. |

### `/store/`: production sets body copy at 10px

The section where production looks three times denser renders its paragraphs and list items at
`font-size: 10px` / `line-height: 15px` on a 390px screen. v2 renders the same 3,892 characters
at 16px / 27.2px. Production has no accordion, no tabs, no carousel and nothing hidden there —
it is simply 10px text.

Closing that gap would mean shrinking body copy to 10px on mobile, well under the ~16px both
platform guidelines recommend. **v2 is correct here and production is not.** The height
difference is the intended outcome, and `/store/` should be read as passing.

This is the same shape of trap as `/services/`, and the reason the content measure matters: the
metric says "v2 is 85% taller", and the right response is to leave it alone.

### What the two fixes changed site-wide

`acf/feature-cards` is used by 15 patterns, so its default is genuinely a site-wide change:

- Card decks are two across below `sm` instead of one (a one-column deck stays one column).
- Card title steps 20px → 17px and body 14px → 13px below `sm` only; at `sm` and above nothing
  moves. Without that step a title wrapped to four ragged lines in a ~163px column.
- `acf/department-cards` got the same treatment plus a shorter `min-height` below `sm`, since a
  420px photo on a 163px-wide card is disproportionate.

Measured effect on the homepage: the six-card deck 2,140 → 1,160px, the four-card deck
1,908 → 803px, department cards 1,836 → 912px. Desktop height is byte-identical.

The 15-card testimonial grid on the homepage (6,532px) was deliberately left alone — its content
is 87% larger than production's for 97% more height, so it is proportional, not a layout bug.

## Known issue this surfaced (fixed)

`/case-study/` rendered only **10** of the **23** published case studies. The archive template
loops the main query, which respects WordPress's default `posts_per_page = 10`, and it had
neither pagination nor a `pre_get_posts` override — so 13 case studies were live but unreachable
from the index, with no pager to hint anything had been cut.

Fixed in `CaseStudyPostType::showEveryCaseStudyOnArchive()`: the main front-end query for the
`case_study` archive is set to `posts_per_page = -1`, matching production, which also lists all
of its own on one page with no pager. Admin list tables and secondary `WP_Query` calls keep
their own paging. `/case-study/` now renders 23 of 23.

Captures in `shots/` taken before that fix show the truncated archive; re-run with
`--filter=case-study--archive --force` to refresh them.
