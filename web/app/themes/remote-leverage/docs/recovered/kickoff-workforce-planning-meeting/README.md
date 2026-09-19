# Kickoff & Workforce Planning Meeting — recovered original

Production page **51128**, `/kickoff-workforce-planning-meeting/`, captured from the legacy
GoDaddy install on **2026-09-18**.

Ported to v2 as a route: `routes/web.php` → `funnel.kickoff-workforce-planning`, rendering
`resources/views/pages/kickoff-workforce-planning-meeting.blade.php`.

## What it is

A **revenue router**, not a content page. The visitor picks a monthly-revenue band and the
matching Calendly event embeds inline in an iframe. Six radio options collapse onto three
tiers:

| Radio option | Tier | Calendly |
| :--- | :--- | :--- |
| 0 to 5k Per Month | `small` | `/d/d2jq-z6m-5yt/kickoff-workforce-planning-meeting-t0` |
| 5k to 10k Per Month | `small` | same as above |
| 10k to 50k Per Month | `mid` | `/d/dvxh-c8m-p4k/kickoff-workforce-planning-meeting-t10` |
| 50k to 100k Per Month | `enterprise` | `/d/d2jp-krr-bng/kickoff-workforce-planning-meeting-t50` |
| 100k+ Per Month | `enterprise` | same as above |
| Blank | `small` | same as T0 |

Two details are easy to "tidy" into bugs. **"Blank" routing to `small` is deliberate** — it is
the option a client picks when they will not say, and production sends them to the T0 calendar.
And a **fourth** Calendly event exists, `…-t10-c` (`/d/d3rn-m95-6kr/`), which this page never
references; it is reachable only by direct link. Neither is an oversight to clean up without
asking whoever owns the booking flow.

## Why it was missed at cutover

The page was created **2026-09-15 10:35**, after the migration audit had frozen its scope from
`page-sitemap.xml`. It appears in neither that list nor the later non-indexed sweep of 43 pages,
so it received no v2 page and no 301 entry.

A sweep of all 357 legacy URLs on 2026-09-18 found exactly two still returning a hard 404 in
production: this page and `/new-homepage-26-3/` (an abandoned homepage variant whose siblings
are all in the redirect map).

The lesson is about the method rather than this page: scope was derived from a sitemap snapshot,
so anything published after that snapshot could not be seen by it.

## Files

| File | What it is |
| :--- | :--- |
| `widget.html` | The Elementor `html` widget verbatim — markup, styles and script. This is the entire page. |
| `elementor_data.json` | The page's full `_elementor_data`, one `html` widget in a single section. |

## Fidelity

The view is the widget unchanged, inside a verbatim block (its CSS has a media query Blade
would otherwise read as a directive) and wrapped in `layouts.app`.

- **Not noindexed.** Production carries no robots meta on this page, checked 2026-09-18, so the
  route does not call `PageRobots::forceNoindex()` — unlike `tools.live-transfer` beside it.
- **Header and footer are v2's.** Production used Elementor's `elementor_header_footer`
  template, so its chrome was the legacy theme's. The body is identical; the surrounding page
  is v2.
- **`<title>` is the site default**, which is also what production serves for this page and for
  `/live-transfer-contact-creation/`.
