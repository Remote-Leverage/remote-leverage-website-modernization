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

**Last verified: 2026-09-14** — against the live local DB (`wp post list`), the theme's
Laravel routes, and `config/redirects.php`. Not hand-maintained: every row below was
checked against the database on that date.

Local DB totals at verification: **15 pages, 119 posts, 23 case studies, 1 partner.**

> **Scope directive (2026-09-14):** everything on the transfer list gets migrated.
> Earlier audit notes marking some of these as "dead", "test variant" or "confirmed
> dead — just redirect" are **superseded** — they no longer gate any row here.
> The one exception is a genuine duplicate slug serving identical content, which is
> handled by a codebase redirect rather than a rebuilt page (see §2).

**Score: 14 of 48 migrated (29%). 34 remaining.**

---

## 1. ✅ Migrated (14)

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
| — | `/partners/` | `archive-rl_partner.blade.php` + `PartnerPostType` — template built; **content is the gap**, see P1 |
| — | `/hire-va-4/` | page ID 1000000 → `patterns/hire-va-4-full.php` (migrated 2026-09-14) |

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

## 3. ❌ Remaining (34), by priority

### P0 — Broken destinations v2 already ships (5)

Every one of these is already referenced from shipping v2 code, so the site currently
points at its own 404s. **Fix first.** In each case migrating the page fixes the
reference; no template change is needed, and nothing should be un-linked — the
references are all correct against production.

**Linked from the site chrome (5):**

| Page | Linked from |
|---|---|
| `/vacalendar/` | `resources/views/sections/header.blade.php:106`, `:120`, `:177` |
| `/samples/` | `resources/views/sections/header.blade.php:50`, `:170` |
| `/contractor-management/` | `resources/views/sections/footer.blade.php:59` |
| `/contractor-payments/` | `resources/views/sections/footer.blade.php:60` |
| `/impact-report-2026/` | `resources/views/sections/footer.blade.php:85` |

**Target of a live 301 redirect — ✅ resolved 2026-09-14:**

`/hire-va-4/` is **migrated** (page ID 1000000, rendering `patterns/hire-va-4-full.php`).
`hire-va-old` and `hire-virtual-assistant` both 301 to it and land on a 200; verified
end-to-end against `remoteleverage-v2.test`. See §1.

### P1 — Partnerships (5)

**Resolved 2026-09-14: the standalone landing pages and the partner hub are both kept.**
They serve different jobs — the `/remote-leverage-x-*/` pages are marketing pages *about*
a partnership, the hub is the partner directory — so neither folds into the other.

**Standalone landing pages (2)** — on the transfer list, must not be folded into `/partners/{slug}/`:

- `/remote-leverage-x-oyster/`
- `/remote-leverage-x-lano/`

**Partner hub entries (3)** — added to scope 2026-09-14. Templates
(`archive-rl_partner.blade.php`, `single-rl_partner.blade.php`, `PartnerPostType`) are
already built, so this is content work only:

| Entry | Local state | Production |
|---|---|---|
| `/partners/oyster/` | exists (ID 122) but **66 chars — a stub** | live, 200 |
| `/partners/lano/` | missing | *(no `rl_partner` entry on production)* |
| `/partners/lexgo/` | missing | live, 200 |

Note the asymmetry: production carries Oyster + Lexgo as `rl_partner` entries and
Oyster + Lano as landing pages. **Lano has no production hub entry and Lexgo has no
production landing page**, so each needs one side authored fresh rather than migrated.

### P2 — Funnel / operational pages (6)

Live money-path pages. Verify current behaviour with sales/ops as each is rebuilt.

- `/payment/`
- `/signedup/`
- `/vaonboardingform/`
- `/referral-program/` (distinct from the already-built `/referral/` ID 213 and `/affiliate-program/` ID 212)
- `/referral-program-thank-you-page-deposit/`
- `/virtual-assistant-hiring-manager-refundable-deposit/`

### P3 — Marketing / content pages (4)

- `/spanish/`
- `/social-media-kit/` (note: the `rl-social-kit` plugin's generator already exists as a route at `/tools/signature-generator`; the page itself is a separate deliverable)
- `/ecommerce-virtual-assistant/`
- `/hire-for-less/`

### P4 — Campaign & landing pages (14)

Ad-traffic landing pages and variants. Per the scope directive these are all in scope —
the previous audit's "test variant / dead" marks do not apply.

- `/1monthonus/`
- `/1monthonus-flp/`
- `/hire-real-estate-virtual-assistants-flp/`
- `/hire-va-1st-month-free/`
- `/hire-va-6/`
- `/hire-va-email/`
- `/hire-va-isolated-form/`
- `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant/`
- `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b/`
- `/hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-c/`
- `/stealing-jobs/`
- `/stealing-jobs-lp/`
- `/steal-back-your-time/`
- `/vastore5/`

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
| 3 | `/referral/` | 213 | yes | **Built from the wrong source** — renders `hire-va-4-full`, but production `/referral/` is a *homepage* variant | See correction below |
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
| `/tools/signature-generator` | `rl-social-kit` | Possibly — but `/social-media-kit/` **is** on the list (P3) and may need it |

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

## 5. Open questions

1. ~~**The partner hub and Lexgo**~~ — **resolved 2026-09-14**: both the
   `/remote-leverage-x-*/` landing pages and `/partners/` are kept. Lexgo rides the hub
   (production has no `/remote-leverage-x-lexgo/`). See §3 P1.
2. **`/referral/` (213) holds the wrong content** — it renders `hire-va-4-full`, but
   production `/referral/` is a homepage variant (§4a). It is out of scope, so the call is
   redirect-or-delete, not rework. `/hire-va-4-preview/` (104), the other copy, was deleted
   2026-09-14.
3. **`/social-media-kit/` vs `/tools/signature-generator`** — the in-scope page (P3) and
   the out-of-scope route (§4b) may be the same deliverable. Confirm before building either.
4. **`legal_last_updated`** — still empty on both legal pages, so the hero falls back to
   the post modified date (2026-09-10). Someone who knows the real revision dates should set it.
5. **Affiliate Program "How It Works"** — shows the same three cards duplicated from the
   section above instead of real steps. Exists on production too, so left alone pending a
   copy decision. Moot if `/affiliate-program/` is dropped under §4a.
