# Content Migration — Continuation Plan

Companion to [`content-migration-checklist.md`](./content-migration-checklist.md).
Written 2026-09-10 after verifying actual local DB state against the checklist.

---

## Where we actually are

Verified against the local `remoteleverage-v2.test` database, not the docs:

| Content | Production | Migrated locally | Remaining |
| :--- | ---: | ---: | ---: |
| `page` | 233 | 14 | 219 |
| `case_study` (were pages on prod) | 23 | 23 | 0 |
| `post` | 119 | 0 (`Hello world!` only) | 119 |
| `rl_partner` | 2 | 1 (Oyster) | 1 (Lexgo) |
| Categories | 7 | 0 | 7 |
| Tags | 8 | 0 | 8 |

**~37 of 233 pages (16%) are done.** Everything migrated so far is §1 "core
marketing" plus the case-study set. Sections §2–§8 are untouched.

Local pages: `home`, `about-us`, `reviews`, `vapricing`, `privacy-policy`,
`terms-of-use`, `comparison`, `compare-athena`,
`wing-assistant-vs-remote-leverage`, `comparison-wing-assistant-ads`,
`affiliate-program`, `referral`, `vathankyou`, `hire-va-4-preview`.

### Doc drift to fix first
`PAGE-MIGRATION-STATUS.md` (repo root) is stale — it still lists the 4
comparison pages and `/referral/` as "🔧 Needs migration" when the checklist
and the DB both show them migrated. Two overlapping trackers is a liability;
recommend collapsing `PAGE-MIGRATION-STATUS.md` into the checklist and keeping
one source of truth.

---

## The real bottleneck is decisions, not build capacity

Of the ~219 remaining pages:

- **~87 pages (§4 + §5)** cannot be started at all until someone decides
  keep / kill / redirect. These are noindex funnel pages and paid-traffic
  landing-page experiments. Building them is wasted work if they're being
  retired; killing them is revenue loss if ad spend still points at them.
- **~45 pages (§2)** are blocked on one architectural decision (single
  data-driven template vs. individually-built pages) plus a canonical-URL
  decision across the `-2` / `-legacy` duplicate sets.
- **119 posts (§8)** are blocked on the Elementor → Gutenberg conversion pass.

That means **~250 of ~338 remaining items sit behind four decisions.** The
highest-leverage next move is not building another page — it's unblocking those
four, in parallel with the small pool of work that needs no sign-off.

---

## Plan

### Track A — Unblocked now (no sign-off needed)

Small, self-contained, can start immediately.

1. **Case-study sub-nav tab bar.** Flagged as a known gap in the checklist and
   confirmed absent from the theme: production shows a
   "CASE STUDIES / TALENT PROFILES / REVIEWS" tab bar above the case-study hero,
   shared site-wide with `/reviews/`. Build it once as a shared partial. Note
   the TALENT PROFILES destination doesn't exist in v2 yet — it needs a target
   before the tab can link anywhere real.
2. **`booking-footer` headline regression sweep.** The `headline` ACF field was
   dead code until the affiliate-program review fixed it
   (`BookingFooterBlock.php:90`). Any page that set a headline override before
   that fix was silently rendering the default. Re-check every page using the
   `booking-footer` / `hire-va-4-booking-footer` patterns.
3. **Lexgo partner content.** Partner templates and admin UI are complete
   (per `partner-hub-gap-analysis.md`, WR-115 closed) — only Oyster's content
   exists. Lexgo is pure data entry against the now-restored ~35-field metabox.
4. **Install Yoast SEO (§9).** Zero-risk and it *unblocks* later work: matching
   the live site's plugin means `_yoast_wpseo_*` postmeta carries over directly
   instead of needing field-mapping, and Premium's redirect manager is the
   mechanism every §4/§5 kill decision depends on. Doing this late forces a
   second pass over everything already migrated. Currently only 4 plugins are
   installed locally; Yoast is not among them.

### Track B — Force the four decisions (do in parallel with A)

Produce one decision-request doc per audience, each with a **recommended
default** so the owner is approving a proposal rather than authoring one from
scratch. Without recommendations these will sit.

1. **Paid-traffic owner → §5 (43 experiment pages) + the funnel half of §4.**
   Needed: which URLs currently have live ad spend pointed at them. Suggested
   framing: anything with spend in the last 90 days gets rebuilt; everything
   else 301s to its nearest live equivalent. This is the single largest
   unblocking action available.
2. **Sales/ops owner → §4 operational pages (19 URLs).** `/payment/`,
   `/contractoragreement/`, `/onboardingform/`, `/vainterview/`,
   `/hmchecklists/` et al. read like live internal tooling, not dead pages.
   Needed: which are still in the hiring/onboarding flow, and who the audience
   is for the three `*checklists` pages (internal staff vs. clients — that
   changes whether they need auth).
3. **§2 architecture (~45 role/industry pages) — recommend a single
   data-driven template.** The set is explicitly template-driven with a
   role/industry/region axis, and the LatAm variants are the same page with a
   region swap. One Blade template fed by a CPT or ACF options set turns ~45
   page builds into one build plus ~45 content records, and makes future role
   additions free. Building them individually re-creates the exact duplication
   problem §2 already documents (`marketing-assistants` /
   `-2` / `-legacy`). Pair this with the canonical-URL pass: pick one page per
   role, 301 the rest.
4. **§8 blog — run `wp acorn content:audit-elementor` first.** The tooling
   already exists (`app/Domains/ContentAudit/`:
   `AuditElementorCommand`, `ConvertElementorCommand`,
   `ConvertElementorPostAction`, `ApplyElementorConversionAction`). Running the
   audit is cheap and read-only, and its output is what tells us whether the
   119 posts are a bulk scripted import or a per-post slog. Run it before
   committing to any blog-migration estimate. Also settle `/guides/` vs
   `/blog/` canonical before import so Yoast isn't reconfigured twice.

### Track C — The big build (starts as B lands)

- §2 role/industry template + content (~45 pages) — largest single block of
  work, fully specified once decision B3 lands.
- §8 blog import (119 posts, 7 categories, 8 tags) — scriptable once B4's audit
  reports clean.
- §5/§4 rebuilds — scoped to whatever survives B1/B2, expected to be a small
  fraction.
- §7 tools/lead magnets (~10 pages) — these are interactive apps, not content;
  they need their own scoping pass and shouldn't be estimated alongside static
  page migration. `signature-generator.blade.php` is already scaffolded.

---

## Recommended sequence

1. Install Yoast + settle `/guides/` vs `/blog/` canonical (A4, unblocks §9).
2. Send the two decision requests (B1, B2) — longest lead time, send first.
3. Run the Elementor audit (B4) while waiting.
4. Clear Track A items 1–3 (sub-nav, booking-footer sweep, Lexgo).
5. Reconcile `PAGE-MIGRATION-STATUS.md` into the checklist.
6. Build the §2 template once B3 is agreed — this is the long pole.

---

## Gaps this plan does not cover

- **Drafts, private/password-protected content, and the media library** were
  never audited — the REST API only exposes published content without auth.
  An app password against wp-admin would let us query `status=any`. Worth
  doing before cutover, since a missed draft is invisible until someone asks
  for it.
- **`e-landing-page` CPT (2 entries)** has no equivalent post type in v2 and no
  decision recorded anywhere.
- **`/remote-leverage-x-oyster/` and `/remote-leverage-x-lano/`** standalone
  pages — still unresolved whether they fold into the partner hub. Note "Lano"
  is a third partner name that appears nowhere else in the audit (only Oyster
  and Lexgo exist as `rl_partner`), so this may be a dead page or a missing
  partner record.
- **`plan.md` progress is badly out of date** — Phases 3–8 all read `0%
  Completed`, but Phase 5 (Gutenberg blocks) has 28 blocks built and Phase 6
  (Blade templates) is clearly well underway. Whoever reads `plan.md` for
  status is getting a wrong picture.
