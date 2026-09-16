# Page Migration Status — v2 Redesign

Tracking the **46-row "Migrate for now" list** (43 unique URLs — rows 1/21, 2/22 and
11/45 are duplicates), **plus `/hire-va-4/`** (added to scope 2026-09-14, see §3 P0),
plus the **4 partner-hub URLs** confirmed in scope 2026-09-14 (§3 P1), against what
actually exists in the v2 database and codebase. **48 URLs in scope.**

> ## Scope is closed
>
> **These 44 URLs are the entire migration. Nothing else on production is being
> migrated — not deferred, not "phase 2". Discarded.**
>
> Production has 235 published pages. 191 of them are out of scope and will not be
> rebuilt; they need a redirect-or-drop decision at cutover, not a migration plan.
> (The partner hub is the one addition to the transfer list: `/partners/` is v2-native
> and has no production counterpart, but was confirmed needed — see §3 P1.)
> This supersedes every "must migrate", "needs a decision", and unticked checkbox in
> [`docs/content-migration-checklist.md`](web/app/themes/remote-leverage/docs/content-migration-checklist.md),
> which is now a **read-only inventory of what exists on production** — not a work list.
>
> v2 already contains some content that is *not* on the list (built before scope was
> closed). It is inventoried in §4 and needs a keep/redirect/delete decision.

**Last verified: 2026-09-15** — against the live local DB (`wp post list`), the theme's Laravel
routes, and `config/redirects.php`. Not hand-maintained: every row below was checked against the
database on that date.

> **Checking a URL here needs more than one lookup.** A `wp post list` sweep reports several
> in-scope URLs as missing, for three different and legitimate reasons:
>
> | Reason | Examples |
> | :--- | :--- |
> | Acorn route, never a `page` row | `/social-media-kit/`, `/referral-dashboard/`, `/book-consultation/`, `/referrer-portal/`, `/live-call/connect` |
> | A redirect, deliberately | `/thank-you/` → `/vathankyou/` |
> | A draft, not published | 1 page as of 2026-09-15 |
>
> Check `routes/web.php` and `config/redirects.php` too, or just curl the URL.
>
> And a redirect key equal to a live page slug **301s that page away**. Three keys
> (`contractoragreement`, `services`, `store`) and later `vaonboardingguide` had to be removed
> for exactly that reason on 2026-09-15; `tests/Feature/RoutesTest.php` now pins them so they
> cannot come back.

Local DB totals, re-queried **2026-09-15**: **54 published pages** (+1 draft), **119 posts**,
**23 case studies**, **3 partners**, 513 attachments.

> **Do not read "54 pages" as a check on "54 of 54 URLs".** They are different 54s and will drift
> apart the moment anyone adds a draft or a route-backed URL. `/social-media-kit/` is in the URL
> count and is not a page row at all.

> **What "migrated" means here (tightened 2026-09-15).** A row only counts as migrated when
> the page is **visually 1:1 with production**, verified by screenshot diff — not when the
> same sections and copy are present. Five rows previously counted as done had complete
> content but invented layout; they were rebuilt against production's real design and
> re-verified. See §6 for the method.

> **Scope directive (2026-09-14):** everything on the transfer list gets migrated.
> Earlier audit notes marking some of these as "dead", "test variant" or "confirmed
> dead — just redirect" are **superseded** — they no longer gate any row here.
> The one exception is a genuine duplicate slug serving identical content, which is
> handled by a codebase redirect rather than a rebuilt page (see §2).

**Score: 54 of 54 built (100%). 1 page cannot go live yet.**

The denominator moved twice and the arithmetic is **48 + 5 + 1**:

| | Count | What |
| :--- | ---: | :--- |
| Original closed transfer list | 48 | The scope closed on 2026-09-14 |
| Non-indexed sweep | +5 | `/hmchecklists/`, `/recruiterchecklists/`, `/saleschecklists/`, `/sales-talents/`, `/onboardingguide/` (IDs 1000073–1000077) |
| Added by direction | +1 | `/hire-va/` (page 1000066), added to scope this session |
| **Total** | **54** | |

`/hire-va/` is the one the earlier "53" missed: §3b already treats it as in scope in prose —
`hire-va-2`, `hire-va-3` and `hire-va-t` were re-pointed to it "now that it is itself a live v2
page" — while leaving it out of the count. Fixed here.

Every priority section below is closed — P0, P1, P2, P3 and P4 — plus five pages pulled in from
the non-indexed sweep on 2026-09-15 (§3b), which took the scope from 48 URLs to 53.

The one outstanding item is not an unbuilt page:
`/virtual-assistant-hiring-manager-refundable-deposit/` is visually complete but **cannot take
a payment until Stripe credentials are moved** (see P2). A second blocker on that same page was
found on 2026-09-15 — `STRIPE_DEFAULT_THANKYOU_URL` was unset, so the deposit element rendered
`success-url=""` and a paying customer landed nowhere. Now set locally; it has to be set in
every environment. See the funnel gate in
[production-cutover.md](web/app/themes/remote-leverage/docs/production-cutover.md).

_Reconciled 2026-09-15 after P3 and P4 closed concurrently and the running total briefly
disagreed with the sections. The per-priority sections are authoritative; §1's table counts
table rows (34), not URLs, because several rows cover many URLs — `/blog/` carries 119 posts,
one row carries all 23 case studies, and P4's 14 campaign pages are listed in their own
section._

---

## 1. ✅ Migrated (34)

| # | Production URL | v2 implementation |
|---|---|---|
| 23 | `/` | `front-page.blade.php` (page ID 7) |
| 5 | `/about-us/` | page ID 209 |
| 6 | `/blog/` | page ID 799 + all 119 posts imported |
| 8 | `/case-study/` | `case_study` CPT archive + all 23 individual studies ✔ ("Every case study") |
| 9 | `/comparison/` | page ID 323 |
| 10 | `/compare-athena/` | page ID 382 |
| 11, 45 | `/wing-assistant-vs-remote-leverage/` | page ID 407 |
| 28 | `/privacy-policy/` | page ID 3 — Legal Document template |
| 30 | `/reviews/` | page ID 292 |
| 33 | `/terms-of-use/` | page ID 130 — Legal Document template |
| 27 | `/referral-dashboard/` | Laravel route, `routes/web.php:64` (legacy tab behaviour preserved) |
| 42 | `/thank-you/` | 301 → `/vathankyou/` (page ID 126) — see §2 |
| — | `/partners/` | `archive-rl_partner.blade.php` + `PartnerPostType` — directory rebuilt CPT-backed 2026-09-15, renders all 3 partners |
| — | `/partners/oyster/` | page ID 122 → `resources/partners/partners.php` |
| — | `/partners/lexgo/` | page ID 1000011 → `resources/partners/partners.php` |
| — | `/partners/lano/` | page ID 1000012 → `resources/partners/partners.php` |
| — | `/spanish/` | page ID 1000038 → `patterns/spanish-full.php` |
| — | `/hire-for-less/` | page ID 1000031 → `patterns/hire-for-less-full.php` |
| — | `/social-media-kit/` | Laravel route, `routes/web.php` → `pages/social-media-kit.blade.php` |
| — | `/ecommerce-virtual-assistant/` | page ID 1000064 → `patterns/ecommerce-virtual-assistant.php` |
| — | `/hire-va-4/` | page ID 1000000 → `patterns/hire-va-4-full.php` (migrated 2026-09-14) |
| — | `/vacalendar/` | page ID 1000002 → `patterns/vacalendar-full.php` |
| — | `/samples/` | page ID 1000005 → `patterns/samples-content.php` |
| — | `/contractor-management/` | page ID 1000006 → `patterns/contractor-management.php` — rebuilt 1:1 2026-09-15 |
| — | `/contractor-payments/` | page ID 1000007 → `patterns/contractor-payments.php` — rebuilt 1:1 2026-09-15 |
| — | `/impact-report-2026/` | page ID 1000008 → `patterns/impact-report-2026.php` — rebuilt 1:1 2026-09-15 |
| — | `/remote-leverage-x-oyster/` | page ID 1000009 → `patterns/remote-leverage-x-oyster.php` — rebuilt 1:1 2026-09-15 |
| — | `/remote-leverage-x-lano/` | page ID 1000010 → `patterns/remote-leverage-x-lano.php` — rebuilt 1:1 2026-09-15 |
| — | `/payment/` | page ID 1000036 → `patterns/payment.php` — JotForm 242638425990061 (2026-09-15) |
| — | `/signedup/` | page ID 1000032 → `patterns/signedup.php` (2026-09-15) |
| — | `/vaonboardingform/` | page ID 1000033 → `patterns/vaonboardingform.php` — JotForm 242937701106049 (2026-09-15) |
| — | `/referral-program/` | page ID 1000034 → `patterns/referral-program.php` (2026-09-15) |
| — | `/referral-program-thank-you-page-deposit/` | page ID 1000035 → `patterns/referral-program-thank-you-deposit.php` (2026-09-15) |
| — | `/virtual-assistant-hiring-manager-refundable-deposit/` | page ID 1000039 → `patterns/virtual-assistant-hiring-manager-refundable-deposit.php` — **⚠ built but cannot take payment**, see §3 P2 |

## 2. Duplicate slug resolved by redirect

`/thank-you/` and `/vathankyou/` serve the same confirmation page on production.
Rather than maintaining two pages, `/thank-you/` now 301s to the canonical
`/vathankyou/` (page ID 126, `page-vathankyou.blade.php`).

The redirect lives **in the codebase, not the database**: `config/redirects.php`,
applied by `LegacyRedirectMiddleware` (wired at `app/setup.php:248`). Query strings
and UTM parameters are preserved across the redirect. Covered by
`tests/Unit/LegacyRedirectTest.php`, which also asserts no target in the map is itself
a redirect key (guards against 301 chains).

This is the pattern for any future duplicate slug — add a row to `config/redirects.php`
so it ships with the code and survives a database refresh.

---

## 3. Remaining by priority — P0, P1, P2, P3 and P4 all cleared

### P0 — Broken destinations v2 already ships — ✅ CLEARED 2026-09-15

All five chrome-linked pages are built, so the site no longer points at its own 404s:
`/vacalendar/`, `/samples/`, `/contractor-management/`, `/contractor-payments/`,
`/impact-report-2026/`. `/hire-va-4/`, the target of the `hire-va-old` and
`hire-virtual-assistant` 301s, was cleared 2026-09-14.

Three of those five (`contractor-management`, `contractor-payments`, `impact-report-2026`)
were first built with the right copy but invented layout, and were rebuilt against
production's actual design on 2026-09-15. See §6.

### P1 — Partnerships — ✅ CLEARED 2026-09-15

**Resolved 2026-09-14: the standalone landing pages and the partner hub are both kept.**
They serve different jobs — the `/remote-leverage-x-*/` pages are marketing pages *about*
a partnership, the hub is the partner directory — so neither folds into the other.

**Standalone landing pages — ✅ done 2026-09-15.** `/remote-leverage-x-oyster/` and
`/remote-leverage-x-lano/` are built from `resources/patterns/partner-landing.php`, a shared
template the two thin pattern files supply copy and brand colour to. Rebuilt 1:1 against
production the same day.

They deliberately **do not** use production's partner-blue palette: per direction on
2026-09-15 they use theme tokens instead (Oyster `brand-purple-deep → brand-dark-violet`,
Lano `brand-navy → brand-midnight`), the shared world map rather than production's globe
graphic, and a transparent white header over the hero.

**Partner hub entries (3) — ✅ done 2026-09-15.**

The earlier reading of this row ("content work only — three ~70-char stubs") was wrong on
both halves, and the real blocker was structural:

- The three entries were **not** stubs. Only `post_content` held a placeholder sentence, and
  `single-rl_partner.blade.php` never renders `post_content`. Each entry already carried ~22
  populated meta fields (codes, terms, both fee structures, resource links, manager contact).
- `/partners/` itself was **empty**, and no amount of CPT content would have fixed it. The
  directory grid read *only* from a Notion database via `NotionSyncService`, and both
  `NOTION_API_KEY` and `NOTION_PARTNERS_DATABASE_ID` were unset — so `fetchPartners()`
  returned `[]` and the page rendered "No matching partners found" while the three populated
  CPT entries sat unused.

Resolved by making the CPT the single source of truth for both surfaces and **deleting the
Notion integration** (`NotionSyncService`, `SyncNotionPartnersAction`, the `services.notion`
config block and both env keys). Two dead reads in the directory card were fixed alongside:
it read `$partner['website']` where the DTO emitted `website_url` (so "Visit Website" never
rendered), and `$partner['slug']`, which the DTO never emitted at all (so the hub link was
guessed by slugifying the name).

| Entry | Local state | Production |
|---|---|---|
| `/partners/oyster/` | ID 122 — seeded, renders, in directory | live, 200 |
| `/partners/lano/` | ID 1000012 — seeded, renders, in directory | *(no `rl_partner` entry on production)* |
| `/partners/lexgo/` | ID 1000011 — seeded, renders, in directory | live, 200 |

Partner content now lives in **`resources/partners/partners.php`** (in git), applied by
`wp acorn partners:seed` — idempotent, matched on `post_name`, never changes an existing post
ID. Previously these entries existed only in the database and would have been lost on a
refresh, which is what made the directory empty in the first place.

Note the asymmetry: production carries Oyster + Lexgo as `rl_partner` entries and
Oyster + Lano as landing pages. **Lano has no production hub entry and Lexgo has no
production landing page**, so each needed one side authored fresh rather than migrated.

Three data gaps remain, recorded in [docs/domains/partner-hub.md](web/app/themes/remote-leverage/docs/domains/partner-hub.md#known-gaps)
— they need information from the partners, not code: Lexgo and Lano have no referral intake
form or tracking sheet of their own (the hand-authored entries had **Oyster's**, which would
have routed their referrals into Oyster's sheet — now unset), Oyster still has no referral
destination (`pending to define`), and none of the three has a logo, so cards fall back to a
monogram.

### P2 — Funnel / operational pages — ✅ BUILT 2026-09-15 (1 blocker)

All six are built as patterns and published. Five are complete; the deposit page is visually
complete but **cannot take a payment until Stripe credentials are moved**.

| Page | v2 | Built from |
|---|---|---|
| `/payment/` | ID 1000036 | JotForm `242638425990061` via `acf/jotform-embed` |
| `/vaonboardingform/` | ID 1000033 | JotForm `242937701106049`, same block on a dark violet band |
| `/signedup/` | ID 1000032 | `acf/next-steps-panel` + `acf/testimonials` (new `plain` / 2-column options) |
| `/referral-program/` | ID 1000034 | `acf/referral-program-hero` + 4× `acf/media-copy` + `acf/cta-banner` |
| `/referral-program-thank-you-page-deposit/` | ID 1000035 | `acf/payment-success-banner` + the same shared body |
| `/virtual-assistant-hiring-manager-refundable-deposit/` | ID 1000039 | `acf/payment-gateway` — **⚠ blocked, below** |

Heights measured against production at 1440px: 91%, 99%, 103%, 106%, 105% and 94% once the
v2 footer (≈535px taller than production's on every page) is subtracted.

`/referral-program/` is confirmed distinct from the already-built `/referral/` (ID 213) and
`/affiliate-program/` (ID 212). Both its CTAs point at `/referral-dashboard/?tab=sign-up` and
`?tab=login`, which `routes/web.php` already serves — no new routing was needed.

Production's `/referral-program-thank-you-page-deposit/` is `/referral-program/` behind a
"Payment Successful!" banner and nothing else, so both pages compose one shared body at
`resources/patterns/referral-program-body.php` rather than duplicating it.

**Per direction on 2026-09-15 these use theme tokens, not production's palette** — Inter
Display rather than League Spartan, and the theme's action pills rather than production's
green `#68B93D`.

**Both referral pages were then refreshed onto the brand (2026-09-15, requested).** These are
deliberate departures from production, not parity gaps:

- The hero is the theme's `brand-midnight → brand-navy → brand-purple-deep` gradient with
  ambient glows, an eyebrow pill, the $1,000/$500 figures as glass cards, and the magenta
  action pill — production is a flat purple band with a plain text list.
- "How it Works" moved out of the hero into its own section rendered by `acf/process-steps`,
  the theme's numbered timeline. That block's grid was hard-coded to three columns; it now
  reads `--rl-process-cols` from the step count (1–4) and still defaults to three.

`/payment/` and `/vaonboardingform/` likewise gained a site-typography heading, an intro line
and a card around the embed (requested 2026-09-15). Production has none of that chrome. The
forms' interiors are JotForm's and cannot be styled from the theme.

`/payment/`'s JotForm is configured for **Stripe Checkout** (`payment_type="stripeCheckout"`)
with a customer-entered amount, so it charges through the Stripe account connected to
**JotForm**, not through v2's keys. That is a second money path, separate from the deposit
page's, with its records in the JotForm account.

Two slug notes: `/payment/` collided with an unreferenced `payment.webp` attachment (ID 34)
that held the slug — the attachment was re-slugged to `payment-webp-image`, which does not
change its file URL. This is the basename collision in `docs/known-issues.md` §6.

#### ⚠ Blocker: the deposit page cannot take a payment yet

`/virtual-assistant-hiring-manager-refundable-deposit/` is a live $100 Stripe charge on
production, served by the bespoke `rl_payment_gateway` widget in `rl-elementor-blocks`. That
subsystem is now ported into the theme — `acf/payment-gateway`, a server-side
`POST /api/payments/intent` that resolves the amount from the block (never from the browser),
and `payment_intent.succeeded` handling with HMAC signature verification added to
`StripeWebhookController`.

**It is inert until someone moves the credentials.** They live in `rl-testing`'s WP options and
were not readable from this session. The env vars and their legacy option names are documented
in [`docs/stripe-payments.md`](web/app/themes/remote-leverage/docs/stripe-payments.md). Three
things needed a human decision before this page goes live. **All three are now resolved
(2026-09-15)** — what remains is moving the credentials, not deciding anything:

1. **Which Stripe account — decided.** The deposit is collected on the **existing**
   `STRIPE_KEY`/`STRIPE_SECRET` pair, the same one serving Connect payouts to referrers. No
   second credential set. See [cutover-decisions.md](web/app/themes/remote-leverage/docs/cutover-decisions.md) §5.
2. **~~The webhook fails open.~~ Fixed 2026-09-15 — it now fails closed.** With
   `STRIPE_WEBHOOK_SECRET` unset the controller returns **503** and processes nothing; a bad
   signature returns **403**. The same shared verifier was applied to the Calendly webhook,
   which had no signature verification at all. **This turns the missing secret into a hard
   deployment gate:** until `STRIPE_WEBHOOK_SECRET` and `CALENDLY_WEBHOOK_SIGNING_KEY` are set,
   those endpoints are dark — Stripe Connect payout events included, not just the deposit. See
   [known-issues.md](web/app/themes/remote-leverage/docs/known-issues.md) #7.
3. **Telemetry — rebuilt 2026-09-15.** The legacy widget fired PostHog, Customer.io and an
   internal `/wp-json/rl/v1/log` endpoint on every step of the checkout, and the port removed
   all of it. It is instrumented again through the Tracking domain
   (`RecordBehaviorEventAction`), covering gateway viewed → checkout started → payment
   submitted → succeeded / failed. The `/wp-json/rl/v1/log` endpoint was deliberately not
   reinstated — it was unauthenticated and everything it held is now event properties. Event
   names, provenance and what still needs live keys to verify are in
   [`docs/stripe-payments.md`](web/app/themes/remote-leverage/docs/stripe-payments.md)
   § Checkout funnel telemetry.

Nothing was verified against Stripe end to end, because no key was available.

### P3 — Marketing / content pages — ✅ COMPLETE 2026-09-15

| Page | State |
|---|---|
| `/spanish/` | ✅ page ID 1000038 → `patterns/spanish-full.php` |
| `/hire-for-less/` | ✅ page ID 1000031 → `patterns/hire-for-less-full.php` |
| `/social-media-kit/` | ✅ **a route**, not a page — see below |
| `/ecommerce-virtual-assistant/` | ✅ page ID 1000064 → `patterns/ecommerce-virtual-assistant.php` |

**`/spanish/`** is the homepage funnel in Spanish (minus the client-logo strip and the
trust-and-impact band). Nine `spanish-*.php` patterns, no new blocks — every section reuses
its English counterpart's block with copy transcribed verbatim from production rather than
re-translated. 102% of production height.

**`/hire-for-less/`** is `/hire-va-4/` with three differently-titled process steps — a
heading diff of the two live pages shares 34 of 37. It reuses the hire-va-4 patterns wholesale
and adds one pattern file. 108% of production height.

**`/social-media-kit/` is a Laravel route, not a WordPress page** (`routes/web.php`), because
it is a tool rather than editorial content. Full port of the `rl-social-kit` plugin — its CSS
and JS carried over verbatim, 29 downloadable assets tracked in the theme, public by default.
See [docs/social-media-kit.md](web/app/themes/remote-leverage/docs/social-media-kit.md). A
WordPress page with that slug would shadow the route; the one created during the build was
trashed.

**`/ecommerce-virtual-assistant/`** — 17 sections, **96% of production height** (16,780 vs
17,449px), 147 images with none broken, no horizontal overflow. Built from 7 block extensions
and 3 new blocks (`acf/talent-dossier-carousel`, `acf/stats-band`, `acf/featured-posts`); every
extension defaults to today's behaviour, and 14 pages using those blocks were swept for
regressions.

Direction as of 2026-09-15 is **copy production as-is, content bugs included** — this
supersedes an earlier call to fix the title and repoint the blog feed. Reproduced verbatim and
individually verified in the rendered page: the `<title>` "Hire Power Dialers from LATAM", the
"Hourly Rates for Medical and Healthcare VAs" heading, the "Remote Leverage Medical VA Average"
pricing card, medical software logos on all 8 talent dossiers, four telehealth Featured Content
posts, the malformed `41,920,00` / "Economic Impact Create", the "Montly" misspelling, and the
duplicated card descriptions in §2 and §3. **Do not "fix" these in passing.**

§9a's pricing icons **are** wired in (Adrián's call, 2026-09-15). Production references
`green.png` / `green-1.png` but both are broken on the live page (`naturalWidth 0`), so the
card tops render blank there. The files themselves are intact, so they render here through
`acf/image-card-grid`'s new `icon` field, which draws them at their natural ~88px/146px —
painting them through the existing full-bleed `image` slot at `h-[168px]` was what would have
stamped an oversized green coin on each card.

#### Block gaps this page surfaced — ✅ all three closed 2026-09-15

Logged rather than papered over, then fixed — each was a real limitation the next migration
would have hit:

- **`acf/roles-pricing-grid` could not render without a headline.** `with()` did
  `get_field('headline') ?: 'Virtual Assistant Roles'`, so an empty value was impossible and
  the block injected a heading production lacks; the pattern had to suppress it with an empty
  `sr-only` span. A blank headline now genuinely means no heading, and only an *unset* field
  falls back to the default. The workaround is gone.
- **`acf/image-card-grid` had no alignment option** and no way to show a small icon rather than
  a full-bleed image. It gained `align` (`left` | `center`) and per-card `icon` / `icon_width`.
- **`acf/talent-carousel`'s `layout=full` hid the headline** along with the copy column,
  contradicting the view's own comment that the intent was a full-width carousel *under its own
  heading*. It now keeps the heading and drops only the copy column.

**A silent bug came out of this.** `ImageCardGridBlock::with()` maps card sub-fields explicitly,
so the per-card `cta_text` / `cta_url` / `emphasis` added earlier in the day **never reached the
view** — §9a's CTAs and the black outline on the featured card simply did not render, with no
error. Any field added to `fields()` but not to the `with()` mapping is invisible in exactly
this way; the mapping now carries a comment saying so.

#### `/hire-va-4/` corrected alongside (§1 row, was drifted)

Building `/hire-for-less/` surfaced four defects in the already-migrated `/hire-va-4/`, all
now fixed in shared blocks so both pages benefit:

1. The hiring-process section rendered "The Bridge Between Compliance and Execution" with Lano
   partnership copy where production shows "Our Hiring Process"; all three step bodies were
   also wrong.
2. The booking footer used the block's generic default headline instead of production's
   "Smarter support starts here. Flexible, skilled, and ready to go."
3. **Testimonials rendered all 14 (five grid rows) where production shows 6 (two rows)** —
   1,461px of excess height on its own. `BlockDefaults::hireVa4FeaturedTestimonials()` selects
   production's six; the full wall still belongs on `/reviews/`.
4. Two headers stacked: `acf/hire-va-hero` rendered its own logo + CTA bar while the layout
   also rendered the global site header.

Both pages now sit at 108–110% of production height, in line with the other migrated rows.

**Landing-page chrome.** Production serves the hire-va pages with no site nav — a conversion
page deliberately offers no way out. `App\Support\PageChrome` walks the pattern registry to
detect whether a page renders `acf/hire-va-hero` and, if so, `layouts/app.blade.php` swaps
`sections.header` for `sections.header-cta` (90px, transparent over the hero, `#F90066` pill).
Automatic rather than a page-template assignment, so it survives a database refresh and any
future page using that hero inherits it.

**Correction to an earlier finding:** "Talk to Sales Representative" is *not* missing from v2.
It lives inside the hidden `rl-jlc` live-call modal, which v2 replaces with the
`/live-call/connect` route.

### P4 — Campaign & landing pages (14) — ✅ CLEARED 2026-09-15

All 14 built, each as a single `wp:pattern` reference over a pattern file in git. Verified by
screenshot diff against production at 1440px, measured independently of the building agent.

The 14 pages are **five families of near-duplicates**, so they are built the
`resources/patterns/partner-landing.php` way — one shared template per family, thin per-page
config. Adding a sixth variant of any family is a config file, not a rebuild.

| Production URL | ID | Pattern | Shared template | vs prod height |
|---|---|---|---|---|
| `/1monthonus-flp/` | 1000044 | `patterns/1monthonus-flp.php` | `consultation-landing.php` | 99.0% |
| `/hire-va-email/` | 1000045 | `patterns/hire-va-email.php` | `consultation-landing.php` | 98.9% |
| `/hire-virtual-assistants-…-variant/` | 1000046 | `patterns/…-variant.php` | `consultation-landing.php` | 99.1% |
| `/hire-virtual-assistants-…-variant-b/` | 1000047 | `patterns/…-variant-b.php` | `consultation-landing.php` | 98.8% |
| `/hire-virtual-assistants-…-variant-c/` | 1000048 | `patterns/…-variant-c.php` | `consultation-landing.php` | 98.5% |
| `/hire-real-estate-virtual-assistants-flp/` | 1000049 | `patterns/hire-real-estate-virtual-assistants-flp.php` | `consultation-landing.php` | 98.9% |
| `/hire-va-1st-month-free/` | 1000040 | `patterns/hire-va-1st-month-free.php` | `hire-va-campaign.php` | 108.9% |
| `/hire-va-6/` | 1000041 | `patterns/hire-va-6.php` | `hire-va-campaign.php` | 105.7% |
| `/1monthonus/` | 1000052 | `patterns/1monthonus.php` | `va-roles-landing.php` | 99.4% |
| `/hire-va-isolated-form/` | 1000053 | `patterns/hire-va-isolated-form.php` | `va-roles-landing.php` | 99.2% |
| `/stealing-jobs/` | 1000056 | `patterns/stealing-jobs.php` | `steal-campaign.php` | 102.4% |
| `/stealing-jobs-lp/` | 1000058 | `patterns/stealing-jobs-lp.php` | `steal-campaign.php` | 103.3% |
| `/steal-back-your-time/` | 1000060 | `patterns/steal-back-your-time.php` | `steal-campaign.php` | 102.4% |
| `/vastore5/` | 1000050 | `patterns/vastore5.php` | — (one-off) | **redesigned, parity N/A** |

Ratios re-measured 2026-09-15 after the review round below; the residual on the two
`hire-va-campaign` pages is the hero, see "Known gaps".

One new block: `acf/consult-landing-hero`. Everything else reuses existing blocks.

**`/stealing-jobs/` and `/stealing-jobs-lp/` are NOT duplicates — do not redirect one to the
other.** They are word-for-word identical but production *inverts the whole palette* on `-lp`:
body `#0D0D0D` → `#F4F6FC`, hero `#0D0D0D` → `#3D1A5D`, roles `#0D0D0D` → `#13132F`. Confirmed
against both live pages. This family is near-black, **not** the brand `#250D4A`.

#### Review round — 2026-09-15

Six items came back from client review after the initial build. All six are done.

| # | Item | Resolution |
|---|---|---|
| 1 | Booking form's avatar card + 3-step progress rail unwanted | Removed **site-wide**: `MultistepBookingWizard::$hideProfileHeader`/`$hideProgressBar` now default `true` (property and `mount()`). A caller can still pass `false` |
| 2 | "We've helped more than 2,000…" should be 2 columns | Production only switches above **1500px**; the original build measured at 1440 and reproduced the stacked state. Both states now reproduced — verified matching production at 1440/1600/1920 |
| 3 | `GuaranteeCardBlock` fatal | Already fixed by the P3 session while adding its `background`/`show_reassurance_items` options; the fatal was a transient mid-edit state |
| 4 | `/hire-va-isolated-form/` hero wrong | Rebuilt. **The two `va-roles-landing` pages do not share a hero treatment** — `hire-va-isolated-form` has the white 472px booking card, `1monthonus` has a photographed gradient band and no card. Now driven by per-page keys |
| 5 | Hero + header wrong on the three steal pages | Header: all P4 families now take the CTA-only header (see below). Hero: the gradient was already correct to within 1–2/255 — the real faults were container width, copy-column alignment, a missing CTA glow, a fade overlay painting over the gradient, and a missing logo-rail scrim |
| 6 | `/vastore5/` design | Redesigned on the theme's own `@theme` tokens; parity with production deliberately abandoned. Content and behaviour unchanged — 325 production lines with 0 removed, every `$…` token identical, 35/35 interaction smoke tests passing |

Two mechanisms were added centrally rather than per page, both documented in
[architecture.md § Pages that describe their own chrome](web/app/themes/remote-leverage/docs/architecture.md):

- **CTA-only header.** Production serves every P4 page with no site nav — just a logo and one
  pink pill — but families A, C and D were rendering the full 24-link menu. The theme already had
  `App\Support\PageChrome` + `sections/header-cta` for the hire-va pages, so it was extended
  rather than duplicated: `acf/consult-landing-hero` joined its block list, and an
  `rl:cta-only-header` marker covers the two families whose heroes are hand-written.
- **Per-page `noindex`.** `App\Support\PageRobots` on the `wp_robots` filter, driven by an
  `rl:noindex` marker. See decision 1 below.

Two deliberate divergences from production, **both approved 2026-09-15**:

- **Trust band goes two-column at 1280px, not production's 1500px.** Production stacks this band
  on any window below 1500, which includes the common 1440px laptop — that is what was reported as
  "this should be two columns on desktop", so matching production exactly would have re-shipped the
  complaint. Production's 20% gap only works above 1500, so 1280–1499 uses a flat 64px gap and a
  24px side gutter (the row is `max-w-1320`, so at a 1280 viewport it would otherwise sit flush
  against both screen edges). At 1500+ the band is pixel-identical to production — verified: cards
  at x=96, copy column at x=860, unchanged from the parity build.
- **The `va-roles-landing` hero booking card renders from 1280px up.** Production collapses it to
  **zero width below ~1500px**, so on a 1440 laptop the page's entire conversion mechanism is
  invisible. That reads as an Elementor layout bug rather than a design decision — it is also what
  made an earlier audit mistake the card for an off-canvas popup. Our card is 472px against
  production's 412px where production renders it at all; keeping 472px was an explicit call.

#### Decisions this raised

1. **`/vastore5/` is internal sales collateral, not a landing page, and it publishes payment
   details.** — **decided 2026-09-15: stays `publish`, made genuinely `noindex, nofollow`.**
   It is the only P4 page production serves `noindex, nofollow`, it is absent from the sitemap,
   and nothing links to it. It carries a rep's call scripts plus a deposit panel exposing Zelle
   `Abbas@RemoteLeverage.com`, Venmo `@RemoteLeverage`, and a live Stripe link
   (`buy.stripe.com/9AQbKAcnC6NP2ukaFh`).

   The page **looked** correctly excluded locally, but only because Bedrock's
   `bedrock-disallow-indexing` mu-plugin noindexes every non-production environment — that
   protection disappears in production, and the theme had no page-level robots handling at all.
   Now real: the pattern emits an `rl:noindex` marker that `App\Support\PageRobots` resolves on
   the `wp_robots` filter. See [architecture.md § Pages that describe their own chrome](web/app/themes/remote-leverage/docs/architecture.md).

   **Still open, and worth being blunt about: this keeps the page out of search results, it is
   not access control.** Those payment details remain readable by anyone holding the URL — on
   production today, and in v2. If that is not intended, the fix is auth or unpublishing, not
   robots meta.
2. **The roles section on `/stealing-jobs/` and `/stealing-jobs-lp/` repeats "Customer Support"
   as its fifth card** (same title, same chips), so production's roles grid shows one role twice
   and never names the catch-all. — **fixed in v2 2026-09-15, still broken on production.** Both
   now carry the "And More" card (data entry, bookkeeping, project tracking, HR support) that
   `/steal-back-your-time/` already has: same template, same section, so this is production's own
   content for that slot rather than anything invented. **v2 and production deliberately differ
   here until production is corrected.**
3. **Canonicals pointing at the homepage** — **fixed in v2 2026-09-15, still wrong on production.**
   Production serves both `/hire-va-isolated-form/` and `/hire-va/` with
   `<link rel="canonical" href="https://remoteleverage.com">`, telling search engines both pages
   are duplicates of the homepage. `content:import-seo` had already carried that onto v2 pages
   1000053 and 1000066 as `_yoast_wpseo_canonical`, rewritten onto the v2 origin. Both deleted,
   and the whole page set swept for others — none remain. `/vastore5/` has no canonical on either
   side, which is correct for a noindex page.

   **Related and still open:** v2 currently emits **no canonical on any page at all** — verified
   on `/`, `/about-us/`, `/comparison/` and `/blog/` — even though `wordpress-seo` is active. So
   the bad values were latent rather than live. Whatever is suppressing Yoast's front-end output
   needs finding before launch, at which point any remaining imported canonical starts applying.

#### Theme-wide fixes made alongside P4 (2026-09-15)

- **Font weights brought inside the rule.** The page-migration skill caps weight at bold (700),
  never `font-extrabold` or `font-black`. 18 violations were live across 12 files — including the
  homepage hero CTA and its +2500 / +50 / economic-impact figures, `404.blade.php`,
  `results-preview`, `trust-stats`, `cta-banner`, `talent-carousel`, the booking wizard, the
  referrer portal and the site header. All now `font-bold`.
- **`acf/media-copy`'s dark tone no longer renders invisible text.** The block computed a
  tone-aware colour and then hard-coded `text-black` on the body wrapper, so choosing
  `tone: dark` in wp-admin produced black copy on a dark band with no warning. No page had
  selected it, so nothing visible changed — it stops the next person falling in.
- **Booking form, every instance:** first/last name are always paired on one row (the glass skin
  always did; the other skins only did when a `compactFields` flag was set), and the avatar card
  and three-step progress rail are hidden by default site-wide.
- **Booking form, page-bottom blocks only** (`acf/booking`, `acf/booking-footer`): the revenue
  question leads, as a vertical radio list. Hero forms keep their progressive email-first reveal.
  Submit label changed from "Next: Pick a Date" to "Book a Consultation"; the glass skin's
  hard-coded "Book a Call" was deliberately left alone.

#### Known gaps, all block-level and pre-existing (not introduced by P4)

- `acf/hire-va-hero` floors at `min-h-dvh`, so the hire-va family's hero runs 512px/337px taller
  than production's 688px/863px. Same defect on the signed-off `/hire-va-4/`. Fix per CLAUDE.md
  is a `height_mode` option defaulted to current behaviour, not a second block.
- ~~`acf/testimonials` has no "SHOW MORE" control~~ — ✅ **FIXED 2026-09-15.** The block gained
  `show_more` / `visible_count` / `tone`, defaulted **off** — defaulting it on would have
  collapsed `/reviews/` from 77 cards to 6. `/hire-va-6/`, `/hire-va-1st-month-free/` and
  `/hire-va-4/` now feed the full 15 reviews collapsed to 6, as production does, and the steal
  family's hand-rolled workaround was deleted.

  **A caution for future audits:** the original evidence for this ("production emits it, 6
  matches per page") was wrong — 5 of those 6 were theme boilerplate present on every production
  page, including ones with no control at all. `/reviews/` has none. Count *visible* controls,
  not string matches.
- ~~`acf/accordion-faq` hard-renders two columns and unconditionally emits `FAQPage`~~ —
  ✅ **FIXED 2026-09-15.** Gained `columns` (1|2, default 2) and `schema` (default on), both
  defaulting to previous behaviour. `/vastore5/`'s inline accordion folded back in at
  `columns: 1, schema: off`. The block's answer slot also had **no list styling**, and the theme
  has no global `ul{list-style}` rule — so `/comparison/` and `/hire-va-6/` were rendering FAQ
  bullets as unmarked, unindented lines. Both now `disc / 20px`.
- ~~`acf/roles-grid` renders the Administrative card dark where production has lavender~~ —
  **the premise was false.** Production measures `rgb(99,65,162)` with white text on all three
  consuming pages, identical to what the theme shipped. An `admin_tint` option was added but
  **has no consumer and must not be used** — see design-system.md. Two *real* defects were found
  in that block instead and fixed: the view hard-coded the CTA label and `href="#booking"` and
  never read the `$ctaText`/`$ctaUrl` it computed, and `$eyebrow` was dead the same way.
  **`$cards` is still dead** — the repeater is computed, eight cards are hard-coded.
- ~~The va-roles hero is hand-written markup because `acf/consult-landing-hero` is hard-wired to
  the consultation skin~~ — ✅ **FIXED 2026-09-15.** The block gained a `variant` field; the hero
  now renders through it with every per-page knob preserved (verified: rendered-HTML diff shows
  only the wrapper element and a container value that was already being clamped to 1380px).
- ~~Three templates override `.rl-logo-marquee-item img` at higher specificity~~ —
  ✅ **FIXED 2026-09-15.** `.rl-logo-marquee-lg` and `-natural` modifiers added alongside the
  existing `-dark`; `steal-campaign.php` and `va-roles-landing.php` now opt in instead of
  overriding. Verified byte-identical computed styles on all affected pages.
- ~~`has-text-align-center` has **no CSS anywhere in the theme**~~ — ✅ **FIXED 2026-09-15.**
  The class appeared 29 times across 7 pattern files and resolved to nothing in every built
  stylesheet, because the theme dequeues `wp-block-library`, which is what normally ships it.
  The homepage looked right anyway (a parent centres it); the four comparison pages computed
  `text-align: start`. `.has-text-align-center/-left/-right` are now declared in `app.css`
  alongside the other core-block overrides — verified: `/comparison/` headings now compute
  `center` where they computed `start`.

---

## 3b. Added to scope 2026-09-15 — the non-indexed sweep

43 live production pages are absent from `page-sitemap.xml`, so the audit that built the
transfer list never saw them. 8 were already in scope; of the remaining 35, **31 already had
explicit 301 keys** in `config/redirects.php` and 4 were being rebuilt — they were handled,
just not recorded here. Two facts settled the rest:

- **None of the 35 is linked from any migrated page.** All 17 were fetched and every href,
  form action and raw URL extracted. Zero matches. The "migrated page works, its confirmation
  step 404s" risk does not exist on-site; those entry points are external (email, Calendly, ads).
- The remaining ~20 are confirmed template clones, empty shells or already-dead 301s.

Five were pulled into scope and built:

| Page | v2 | Why it was not left to a 301 |
|---|---|---|
| `/hmchecklists/` | ID 1000073 | 20 forms POST to a **live Zapier webhook**; a 301 to `/` silently kills a working internal tool |
| `/recruiterchecklists/` | ID 1000074 | 7 forms → live n8n webhook |
| `/saleschecklists/` | ID 1000075 | 10 forms → live n8n webhook |
| `/sales-talents/` | ID 1000076 | The only SDR / appointment-setter landing page; genuinely distinct, not a template clone. 95% of production height |
| `/onboardingguide/` | ID 1000077 | 28 Vimeo client-training videos. 99% of production height |

`hire-va-2`, `hire-va-3` and `hire-va-t` were re-pointed from `/` to **`/hire-va/`**, the page
they are price variants of, now that it is itself a live v2 page.

#### Three webhooks nobody had catalogued

`/hmchecklists/` fires three further endpoints **from JavaScript rather than a form `action`**,
so they are invisible to any action-attribute scan — including the one that briefed this work:

- a second Zapier hook (`26138149/u03014q`) the Upsell Inquiry block posts to *alongside* the
  checklist, with its fields re-keyed
- `n8n…/webhook/hm/jobs`, which populates the hiring-manager dropdown
- `n8n…/webhook/send-todo-to-slack`

All three are reproduced. Migrating on the form-action list alone would have taken them dark
silently. `tests/Unit/OpsChecklistsTest.php` asserts every endpoint and every field name, and
asserts no form posts anywhere else — mutation-tested with a one-character typo and a renamed
field, both of which fail it.

#### `wptexturize` was rewriting production copy

Rendering these pages through `the_content` curled every apostrophe and turned
`Send Work Offer - Direct Staff` into an en dash — strings staff paste into CRM fields. That is
WordPress, not the pattern. Worked around inside the affected pattern files by entity-encoding
the characters texturize looks for; it never decodes entities or touches text inside a tag.
Worth knowing for any page whose copy has to survive verbatim.

---

## 4. Out of scope but already in v2 — decisions needed

Verified 2026-09-14 against the local DB and production's REST API (`wp-json/wp/v2`:
235 published pages, 119 posts, 2 `rl_partner`).

v2 was built before scope was closed, so it contains content that is not on the list.
None of it is a migration item any more. Each row needs a **keep / redirect / delete**
call from you.

### 4a. Built pages not on the list (4)

| # | Local URL | ID | On prod? | What it is | Note |
|---|---|---|---|---|---|
| 1 | `/vapricing/` | 288 | yes | Fully built — roles pricing grid, 8 role cards, 64 sideloaded images | Substantial build. Linked from nav? **check before deleting** |
| 2 | `/affiliate-program/` | 212 | yes | Fully built — `AffiliateHeroBlock` + process steps + booking footer | CTAs point at `/referrer-register` |
| 3 | ~~`/referral/`~~ | 213 | **trashed 2026-09-15** | **Built from the wrong source** — rendered `hire-va-4-full`, but production `/referral/` is a *homepage* variant | **Decided: delete.** See correction below |
| 4 | `/comparison-wing-assistant-ads/` | 408 | yes | Fully built — shorter variant of the in-scope Wing page | Confirmed a distinct page, not a duplicate |

#### Correction: `/referral/` was migrated from the wrong source (2026-09-14)

An earlier migration note claimed production `/referral/` was "section-for-section identical
to the VA hiring landing page", and the local page was built from `hire-va-4-full` on that
basis. **That claim is wrong.**

Production `/referral/` (ID 27582) is titled **"Home – Referral"** and is a referral-traffic
variant of the **homepage**. Verified by fetching all three production pages and comparing
their `h1`–`h3` headings:

| Compared against | Shared headings |
|---|---|
| Production **homepage** | **27 of 27 — 100%** |
| Production `/hire-va-4/` | 4 of 27 — 15% |

Its real sections are the homepage's — "Great Talent Changes Everything", the talent-profile
cards, "World's Best Talent, Hired Directly for You", "Beyond the 'Virtual Assistant.'" —
none of which appear on `/hire-va-4/`.

**No rework is scheduled:** `/referral/` is not on the transfer list, so it is out of scope.
This matters for the decision in the table above — the page is not a legitimate duplicate to
redirect away, it is 20,952 characters of *incorrect* content sitting at a URL that is live on
production. Deleting or redirecting it is cleaner than leaving it.

**Resolved 2026-09-15 — deleted.** Page 213 was trashed (`wp post delete 213`, recoverable, not
`--force`). Nothing referenced it: no `href` in the theme, patterns or config, no `nav_menu_item`
targeting object id 213, no `/referral/` string in any `wp_posts.post_content`, `wp_postmeta` or
`wp_options` row. The only mention anywhere was the explanatory comment in
`config/redirects.php`. `/referral/` now returns a real 404 locally. **Still needed:** a 301 in
`config/redirects.php`, since the URL is live on production — `'referral' => ''`, in the
"Old homepage variants and homepage clones -> /" bucket, because production's `/referral/` *is*
a homepage variant (27 of 27 headings). Until that key is added, a legacy `/referral/` visitor
404s at cutover.

`/hire-va-4-preview/` (ID 104), the other copy of this content, was **deleted (trashed)
2026-09-14** at your instruction. No code referenced it — only documentation.

### 4b. Application routes with no production counterpart (5)

These are **not migrated pages** — they are v2 features replacing retired plugins
(`rl-referral-program`, `rl-join-live-call`, `rl-social-kit`, `rl-elementor-blocks`).
"Discard" does not obviously apply: deleting them removes working functionality rather
than dropping unmigrated content. Listed so the decision is explicit, not so it is assumed.

`/partners` and `/partner-dashboard` were previously listed here; both are **in scope** as of
2026-09-14 — `/partners` is the partner hub (§3 P1), and `/partner-dashboard` exists to serve
it, which unblocks the `known-issues.md` bug #1 fix.

| URL | Replaces | Safe to drop? |
|---|---|---|
| `/book-consultation` (+ `/book`) | `rl-elementor-blocks` booking widgets | **No** — booking funnel; blocks across the site link to `#booking-footer` and this route |
| `/referrer-portal` | `rl-referral-program` | URL yes — **but the view is required** by `/referral-dashboard` (row 27, in scope) |
| `/referrer-register` | `rl-referral-program` | URL yes — **same dependency**; also the target of `/affiliate-program/` CTAs |
| `/live-call/connect` | `rl-join-live-call` | **No** — Calendly instant-call routing |
| ~~`/tools/signature-generator`~~ | `rl-social-kit` | **Dropped 2026-09-15.** The route and `GenerateSignatureHtmlAction` are deleted; it 301s to `/social-media-kit/`, which is the in-scope deliverable and is built. They were the same thing. |

### 4c. Redirect entries pointing at out-of-scope targets (2 of 3 open)

In `config/redirects.php`. They work today, but each sends traffic to something not on
the list:

| Entry | Target | Status |
|---|---|---|
| `book-a-call` → `book-consultation` | 4b route | Fine if the booking funnel stays |
| `join-live-call` → `live-call/connect` | 4b route | Fine if live-call routing stays |
| `partner-dashboard` → `referrer-portal` | in-scope route | ✅ Resolved — hub is kept, target is correct |

### 4d. Content carried in scope by a parent row

Not a decision — recorded so the counts are not mistaken for gaps:

- **119 blog posts** — the list names `/blog/` (row 6), not individual posts. Treated as
  in scope: an archive with no posts is not a migrated page. Say so if you disagree.
- **23 case studies** — row 8 reads "Every case study". Explicitly in scope.
- **`/vathankyou/`** (126) — in scope as the canonical target of `/thank-you/` (row 42).
- **`/home/`** (7) — in scope; it is the front page serving `/` (row 23).
- **512 media attachments** — only matters if 4a pages are deleted, which would orphan
  their images. Worth a sweep after the decisions above, not before.

### 4e. Methodology note

**Local URL checks were unreliable until 2026-09-14.** Every unresolved path on
`remoteleverage-v2.test` returned the homepage with a 200, so "does this URL exist?" could
not be answered by curling the local site. Fixed — see
[`docs/known-issues.md`](web/app/themes/remote-leverage/docs/known-issues.md) bug #2 — and
unmigrated pages now correctly return 404. Every conclusion in this document was reached by
querying the database (`wp post list`) or production's REST API, so none of it was affected.

Production has a narrower version of the same trap: it **soft-404s under `/tools/*`**,
returning 200 for any path below it. Root-level paths 404 correctly. Any future production
check must calibrate against a known-bad URL before trusting a 200; this already produced one
false positive (`/tools/signature-generator`) during the 2026-09-14 audit.

## 5. How a page is verified (added 2026-09-15)

Content parity is not visual parity. A page counts as migrated only after a screenshot diff
against production, because heading-level checks pass happily on a page whose layout was
invented.

1. **Capture both sides** with Playwright + Chrome (`channel: 'chrome'`) at 1440px. Force
   `img.loading = 'eager'` and scroll the full page *before* capturing — lazy images and lazy
   CSS backgrounds otherwise read as missing, which produced two wrong conclusions in the
   2026-09-15 session (a section called "broken on production" that simply had a lazy
   background, and images reported absent that were merely below the fold).
2. **Read design tokens off production** with `getComputedStyle` — font sizes, letter-spacing,
   section background colours and gradients — rather than estimating them from a screenshot.
3. **Confirm a section is actually visible** before reproducing it. `contractor-management`
   ships two sections on production (`One source of truth for your entire workforce`,
   `Use everything, or only what you need`) that are `display:none` at every breakpoint; they
   are intentionally absent from v2.
4. **Compare page heights** as a cheap regression signal, then review the side-by-side panels.
   Current: contractor-payments 94%, contractor-management 92%, impact-report 109%,
   oyster 93%, lano 91% of production height. The residual is mostly the v2 footer differing
   from production's.

Known-benign differences that will always show up in a heading-level diff: hero stat values
render as non-headings in v2 blocks, step numerals render as spans, Calendly embed headings
are absent until the widget loads, and Elementor concatenates its A/B headline variants into
one DOM node.

**Steps 1–4 are desktop-only.** A 390px sweep of all 54 URLs was run on 2026-09-15 and is
written up in
[docs/mobile-parity-audit.md](web/app/themes/remote-leverage/docs/mobile-parity-audit.md).
Nothing structural was broken (no horizontal overflow, no broken images, no overlapping text on
any page), but **10 pages rendered their main headline underneath the fixed header** — two of
them at 1440px as well, which a full-page desktop capture cannot show, because a headline tucked
under a transparent header still looks like a headline. The mobile footer was also 1,644px,
taller than most pages' own content. Both are fixed.

Two cautions from that run before repeating it: raw `scrollHeight` ratios are dominated by our
header and footer (production's landing pages ship neither — `/recruiterchecklists/` reads 271%
while our body content is actually *shorter*), and running more than two Playwright workers
against the local server makes it serve gateway pages, which reads as dozens of broken images
that are fine.

## 6. Open questions

1. ~~**The partner hub and Lexgo**~~ — **resolved 2026-09-14**: both the
   `/remote-leverage-x-*/` landing pages and `/partners/` are kept. Lexgo rides the hub
   (production has no `/remote-leverage-x-lexgo/`). See §3 P1.
2. ~~**`/referral/` (213) holds the wrong content**~~ — **resolved 2026-09-15: deleted.**
   It rendered `hire-va-4-full`, but production `/referral/` is a homepage variant (§4a), and
   it was out of scope, so the call was delete rather than rework. Page 213 is trashed (not
   force-deleted) and nothing linked to it. `/hire-va-4-preview/` (104), the other copy, was
   deleted 2026-09-14. **Follow-up:** add `'referral' => ''` to `config/redirects.php`.
3. ~~**`/social-media-kit/` vs `/tools/signature-generator`**~~ — **resolved 2026-09-15.**
   They were the same deliverable, and it was settled by building one and retiring the other.
   `/social-media-kit/` is built and returns 200 as an **Acorn route** (`routes/web.php` →
   `pages/social-media-kit.blade.php`) — it is not a `page` row and never will be, so a
   `wp post list` check correctly fails to find it. `/tools/signature-generator` now returns
   **301**: that route and `GenerateSignatureHtmlAction` were deleted, and 36 legacy social-kit
   asset paths were added to `config/redirects.php`.
4. **`legal_last_updated`** — still empty on both legal pages, so the hero falls back to
   the post modified date (2026-09-10). Someone who knows the real revision dates should set it.
5. **Affiliate Program "How It Works"** — shows the same three cards duplicated from the
   section above instead of real steps. Exists on production too, so left alone pending a
   copy decision. Moot if `/affiliate-program/` is dropped under §4a.
6. ~~**`public/images/` is gitignored**~~ — **resolved 2026-09-15**: the 383 source images
   moved to `resources/images/pages/<page>/` (tracked, 27MB) and `public/images/` is now
   generated from them at build time by the `themeImages()` Vite plugin
   (`vite/theme-images.js`), which preserves every filename and adds a `.webp` sibling. A
   fresh clone plus `npm run build` reproduces the directory exactly. Two leftovers:
   `public/videos/` (64MB, unreferenced by any block or pattern) is still hand-maintained and
   untracked, and 22 Elementor/Framer scrape artefacts (CSS, woff2, mp4) that had been sitting
   in `public/images/home/` were parked in the gitignored `.scrape-cache/` rather than deleted.
7. **The Impact Report download form is not wired.** `acf/impact-report-hero` renders the
   gated name/email capture, but `form_action` is empty — production posts to a gated
   download this project has no endpoint for yet. Needs a Lead domain endpoint and the
   actual PDF.
8. ~~**Partner hub entries are stubs.**~~ — **resolved 2026-09-15.** They were never stubs:
   only `post_content` was a placeholder, and the hub template never renders it. The actual
   blocker was that `/partners/` read from an unconfigured Notion database rather than the
   CPT. Notion is deleted, the directory is CPT-backed, and partner content is seeded from
   `resources/partners/partners.php`. See §3 P1. What remains is information only the
   partners can supply — intake forms for Lexgo and Lano, a referral destination for Oyster,
   and logos for all three.
