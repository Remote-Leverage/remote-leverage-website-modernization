# Known issues

Live bugs, dead configuration, and documentation that contradicts the code. Found while writing this documentation set on 2026-09-14, by reading the code rather than the docs.

Each entry names the problem and a proposed fix so it can be scheduled. Entries fixed since are
marked inline.

> **Scope closure (2026-09-14).** Migration scope is now the 44-URL transfer list and nothing
> else — see [PAGE-MIGRATION-STATUS.md](../../../../../PAGE-MIGRATION-STATUS.md). Some issues
> below concern code that may itself be out of scope and slated for deletion; fixing those would
> be wasted work. Affected entries say so.

---

## Bugs

### ~~1. `/partner-dashboard` throws — three inconsistent definitions of one URL~~ — ✅ **FIXED 2026-09-14**

Three places disagreed about where `/partner-dashboard` goes, and none pointed anywhere real. No route was named `partner.portal` (the registered names are `referrer.portal` / `referrer.register`), and no path `partner-portal` existed (the real one is `referrer-portal`). The two CTA buttons on the partner directory raised `RouteNotFoundException` on every click.

**What was fixed:**

| Location | Before | After |
| :--- | :--- | :--- |
| `routes/web.php` | `redirect()->route('partner.portal')` | `redirect()->route('referrer.portal', [], 301)` |
| `config/redirects.php` | `'partner-dashboard' => 'partner-portal'` | `'partner-dashboard' => 'referrer-portal'` |
| `archive-rl_partner.blade.php:77` | `home_url('/partner-dashboard#register')` | `route('referrer.register')` |
| `archive-rl_partner.blade.php:84` | `home_url('/partner-dashboard')` | `route('referrer.portal')` |

The redirect was also made **permanent (301)** rather than the framework-default 302, since `/partner-dashboard` is a legacy URL that should pass its signal on.

**Verified:** `/partner-dashboard/` now returns 301 and lands on `/referrer-portal` (200) on `remoteleverage-v2.test`.

**Regression guards added** to `tests/Feature/RoutesTest.php`:

- Every `route('...')` name referenced in `routes/` and `resources/views/` must be registered. Mutation-tested: reintroducing `partner.portal` fails the suite with the file name.
- Every `config/redirects.php` target must be a registered route, a declared WordPress page, or explicitly quarantined with a reason. Mutation-tested with a bogus target.
- The partner-directory CTAs specifically resolve to `referrer.portal` / `referrer.register` and no longer contain a hardcoded `partner-dashboard`.

**Residual smell (not a bug):** `/partner-dashboard` is still defined twice — as a Laravel route and as a `config/redirects.php` entry. Both now agree and the route wins. Collapsing to one definition would be tidier but risks changing which layer handles the path, so it was left alone deliberately.

### ~~2. Every unknown URL serves the homepage with HTTP 200~~ — ✅ **FIXED 2026-09-14**

Any path that matched no page returned the **full homepage** with `200 OK` instead of a 404 — not a soft 404 but unlimited duplicate copies of the front page, which would have let crawlers index any typo or stale backlink and made every broken internal link look like it worked.

**Root cause (confirmed in core, not guessed).** `WP_Rewrite::$use_verbose_page_rules` is `true` here, because core sets it from `/^[^%]*%(?:postname|category|tag|author)%/` against the permalink structure — and this site uses `/blog/%postname%/`, which matches. In verbose mode `WP::parse_request()` does not trust the catch-all page rule:

```php
// wp-includes/class-wp.php:242-250
if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
    // This is a verbose page match, let's check to be sure about it.
    $page = get_page_by_path( $matches[ $varmatch[1] ] );
    if ( ! $page ) {
        continue;          // <-- rule skipped, and no later rule matches
    }
```

With the rule skipped and nothing else matching, WordPress is left with **no query vars at all**. An empty query is the default home query, which returns posts — so `handle_404()` never fires and the front page renders with a 200.

The decisive evidence was that `/?pagename=zzz-nope-123` correctly returned **404** while `/zzz-nope-123/` returned **200**: identical target, different path into `parse_request()`.

**Fix.** `App\Application\Http\Middleware\MissingPathNotFoundMiddleware`, hooked on `parse_request` at priority 999 in `app/setup.php`. When a non-empty path produced no `matched_rule` and no query vars, it sets `$wp->query_vars = ['error' => '404']` — the same mechanism core itself uses. `WP::send_headers()` reads that and sends a real 404 (class-wp.php:455), and `WP_Query` calls `set_404()` (class-wp-query.php:1148), so the 404 template renders.

**Why priority 999:** Acorn dispatches Laravel routes on `parse_request` at priority 10 and its `handleRequest()` ends in `exit()` (`Roots/Acorn/Application/Concerns/Bootable.php:300`), so a matched route never reaches this guard. As a second layer the middleware also skips any path registered as an Acorn route, excluding Acorn's catch-all `wordpress` route, which matches everything and would otherwise make the guard a no-op.

**Verified end-to-end on `remoteleverage-v2.test`:**

| Must 404 | | Must keep working | |
| :--- | :--- | :--- | :--- |
| `/zzz-definitely-not-a-page-abc123/` | 404 | `/`, `/about-us/`, `/hire-va-4/`, `/blog/`, `/reviews/`, `/vapricing/`, `/case-study/`, `/comparison/` | 200 |
| `/zzz/deep/nope` | 404 | `/book-consultation/`, `/referrer-portal/`, `/referrer-register/`, `/referral-dashboard/`, `/partners/`, `/tools/signature-generator` | 200 |
| `/hire-va-4-preview/` (deleted page) | 404 | `/case-study/{slug}/`, `/blog/{slug}/`, `/partners/oyster/` | 200 |
| `/vacalendar/`, `/samples/`, `/contractor-management/` (unmigrated) | 404 | `/feed`, `/blog/feed`, `/wp-json/wp/v2/pages`, `/wp-sitemap.xml`, `/?s=virtual` | 200 |
| | | `/hire-va-old/`, `/hire-virtual-assistant/`, `/thank-you/`, `/partner-dashboard/` | 301 |

The 404 response is the real template (49 KB, title *"Page not found"*, heading *"Looks like this page went remote."*) rather than the 219 KB homepage.

**Regression guards:** `tests/Unit/MissingPathNotFoundTest.php` (6 tests) covers the force case, the front page, rewrite-resolved requests, explicit query vars (`?p=`, `?pagename=`, `?s=`, feeds), and asserts every registered Laravel route is never forced to 404.

**Consequence worth remembering:** before this fix, URL-existence checks against the local site were meaningless — everything answered 200. Audits done before 2026-09-14 that relied on curling the local site should be redone; ones that queried the database (`wp post list`) are sound. Production still has a narrower version of the same trap: it returns 200 for any path under `/tools/*`.

### ~~3. `/robots.txt` returns 404~~ — ✅ **RESOLVED 2026-09-14** (file created; the 404 is a local Herd artifact)

**A `robots.txt` now exists** at `web/robots.txt`, served as a static file from the Bedrock web root. It disallows `/wp/wp-admin/` (core lives under `/wp/` in this layout, not the web root), allows `admin-ajax.php`, disallows the authenticated portals and `/api/`, and points at `https://remoteleverage.com/wp-sitemap.xml`.

**The 404 was never an application bug.** Traced with a request-lifecycle probe: PHP reported `http_response_code() === 200` at `send_headers`, `template_redirect`, `do_robots` and `shutdown` — the full request — while nginx still returned 404 to the client. After adding the static file, nginx serves **our exact file** (correct bytes, `content-type: text/plain`, its own `etag`) and *still* reports 404.

Confirmed local-only:

| URL (local) | Status | Bytes served |
| :--- | :--- | :--- |
| `/favicon.svg` | 200 | 3,972 |
| `/favicon.ico` | **404** | 3,972 |
| `/robots.txt` | **404** | 754 |

`robots.txt` and `favicon.ico` are exactly the pair Valet/Herd singles out in its nginx config (`location = /favicon.ico` / `location = /robots.txt` with `log_not_found off`). `favicon.svg`, which is not in that snippet, serves 200 normally. **Production returns 200 for `/robots.txt`.** Nothing to fix in the application; do not chase this status code locally.

**Worth knowing:** production's current robots.txt is bare (`User-Agent: *` / `Disallow:` — allow everything, no sitemap). The new file is a deliberate improvement, not a reproduction of it.

### ~~4. Every environment is forced to be indexable — staging included~~ — ✅ **FIXED 2026-09-14** (override removed entirely)

`app/setup.php` forced indexability unconditionally at `PHP_INT_MAX`, which **overrode Bedrock's `disallow-indexing` mu-plugin** (`DISALLOW_INDEXING` is set in both `config/environments/staging.php` and `development.php`). Staging and local therefore advertised themselves as fully indexable and never emitted `noindex`. Introduced in `ee66680` (2026-09-07) alongside theme/performance work; the `for Lighthouse audit` comment suggests it was an audit tweak rather than a decision about other environments.

**Fix: the whole block was deleted, restoring the WordPress/Bedrock default.** No environment gate, no policy class — nothing in the theme touches indexability any more. Both filters (`pre_option_blog_public` and `wp_robots`) are gone, along with the `use` import.

| Environment | `blog_public` | Robots meta |
| :--- | :--- | :--- |
| `development` / `staging` (`DISALLOW_INDEXING = true`) | `0` | `noindex, nofollow` |
| `production` (no `DISALLOW_INDEXING`) | `1` (verified in the DB) | indexable, with core's `max-image-preview: large` |

**Verified** on this machine (`WP_ENV=development`): `get_option('blog_public')` is now `0`, `apply_filters('wp_robots', [])` returns `{"noindex":true,"nofollow":true}`, and the rendered homepage contains `<meta name='robots' content='noindex, nofollow' />`. The raw DB value of `blog_public` is `'1'`, so production remains indexable once `DISALLOW_INDEXING` is absent.

**Two consequences of removing the block wholesale, both intended:**

- `max-image-preview: large` is unaffected on production — WordPress core adds it itself via `wp_robots_max_image_preview_large()` (`wp-includes/robots-template.php:188`), gated on `blog_public`. The theme's copy was redundant.
- `max-snippet: -1` and `max-video-preview: -1` are **gone**. These are snippet-length controls, not crawlability, and were part of the same removed block. If they are wanted back, they should be added on their own, gated on `blog_public`, not bundled with an indexability override.

**Note on `robots.txt`:** the static `web/robots.txt` does not contradict any of this. Modern `do_robots()` never emits `Disallow: /` — it always outputs only the admin disallow/allow and passes `$public` to the `robots_txt` filter (`wp-includes/functions.php:1725-1739`). WordPress relies on the `noindex` meta tag, not robots.txt, to keep non-public sites out of the index, which is the mechanism now doing the work. Serving `noindex` while allowing the crawl is also the correct way round: a `Disallow` would stop crawlers ever seeing the `noindex`.

### ~~5. Failed WebP conversion left a 0-byte file that was then served forever~~ — ✅ **FIXED 2026-09-15**

`BlockDefaults::preferWebp()` rewrites a local PNG/JPG URL to `.webp` and generates the file on
demand. When the conversion failed it left a **zero-byte** file behind, and the `is_file()` check
on the next request treated that as a valid cache — so the broken empty image was served from
then on. Five files were affected; `contractor-management/hero-main.webp` was one, so that page's
hero image had been invisible.

`generateWebp()` now unlinks on failure and `preferWebp()` treats a zero-byte file as absent and
retries. A related data problem was found at the same time: two files in `uploads/2026/09/`
(`Frame-115.webp`, `clickup.webp`) were valid but did not match their own source image, so the
page showed a different graphic than the one on disk. Both were deleted and regenerated. A
perceptual scan of all 319 webp/source pairs found no others.

**Follow-up 2026-09-15:** theme-file webp is no longer generated at runtime at all. The
`themeImages()` Vite plugin writes every `.webp` at build time from
`resources/images/pages/**`, so `preferWebp()` finds one already on disk and never has to
convert. The on-demand path still exists for images that live in `uploads/` (EFS), which is
where the remaining risk sits. Build-time conversion is lossless for PNG sources and q82 for
JPEG — a worked comparison is in the plugin's comments.

### ~~6. Image URLs are silently swapped for same-named media-library attachments~~ — ✅ **FIXED 2026-09-15**

`BlockDefaults::encodeRepeater()` runs every subfield value through `getAttachmentId()`, which
matches on basename. A theme-file image URL passed to a block therefore resolved to whatever
attachment shared that filename — a different image, if one existed. This is how a
`public/images/contractor-management/Frame-115.jpg` reference ended up rendering
`uploads/2026/09/Frame-115.webp`, and how the ecommerce page's 3KB `Frame-76.png` was
replaced by an unrelated 46KB upload of the same name.

`getAttachmentId()` now returns the URL untouched when the path contains `/themes/`. Theme
page art has no media-library counterpart, so there was never a legitimate match to make; the
mapping stays intact for genuine uploads, which is what it exists for. Silent, so it only
surfaced by eyeballing a rendered card against production.

### ~~7. Calendly webhook signatures are unverified~~ — ✅ **FIXED 2026-09-15** (both webhooks now fail closed)

`/api/webhooks/calendly` had **no signature verification at all**, so anyone who could reach the endpoint could flip a lead to `booked` or `canceled`. `/api/webhooks/stripe` verified signatures but fell through to processing when `STRIPE_WEBHOOK_SECRET` was empty — legacy parity with `rl-elementor-blocks`, and fail-open.

Both now refuse rather than accept. Verification moved into one shared verifier, `App\Application\Http\Support\WebhookSignature`, since Calendly and Stripe sign identically — HMAC-SHA256 over `"{timestamp}.{raw body}"`, presented as `t=…,v1=…`, compared with `hash_equals`, rejecting anything more than 300 seconds old.

| Condition | Before | After |
| :--- | :--- | :--- |
| No secret configured | Stripe: warn + process. Calendly: process | **503**, event not acted on |
| Bad or missing signature | Stripe: 403. Calendly: process | **403**, event not acted on |
| Replayed event outside 300s | Stripe: 403. Calendly: process | **403** |

Note the verifier *also* returns an error for an empty secret, so a future caller that forgets the configuration guard still fails closed rather than comparing against an empty key.

**Deployment consequence, and the reason this needs a human before it ships:** neither `STRIPE_WEBHOOK_SECRET` nor `CALENDLY_WEBHOOK_SIGNING_KEY` is currently set in any environment. Deploying this as-is takes both endpoints dark — Stripe Connect payout events and Calendly booking events will be refused, not processed. **Set both secrets in every environment before this reaches staging or production.** Stripe and Calendly both retry failed deliveries, so a short gap is recoverable; a long one loses events.

**Regression guards:** `tests/Unit/WebhookSignatureTest.php` (7 tests — tamper, wrong key, replay, malformed header, empty secret) and `tests/Feature/WebhookFailClosedTest.php` (4 tests asserting a forged `payment_intent.succeeded` is rejected and a forged Calendly booking leaves the lead's status untouched). The existing webhook feature tests were converted to send properly signed JSON bodies via a `signedWebhookRequest()` helper in `tests/Pest.php` — they previously posted form parameters, which leaves the raw body empty and would have "verified" a signature over nothing.

Unrelated bycatch, fixed in passing: the test suite's `log` stub implemented only `info`/`error`/`warning`/`debug`, so the first caller to use any other PSR-3 level failed with `undefined method` inside a facade rather than anywhere near the code under test. It now implements all of PSR-3.

### 8. Sentry is installed but silent

`sentry/sentry-laravel` is a dependency, `config/sentry.php` is fully populated, and `@sentry/browser` is in `package.json` — but no `SENTRY_LARAVEL_DSN` or `SENTRY_DSN` is set in `.env`. Nothing is reported from any environment.

**Proposed fix:** set the DSN for staging now, and treat "errors reported" as a cutover gate for production.

### 9. A block field can silently blank a repeater sub-field of the same derived key

ACF Composer derives a repeater sub-field's key as `field_<group>_<repeater>_<sub>`. A
**top-level** field whose name happens to match that concatenation collides with it, and ACF
resolves the clash by renaming the sub-field — which then reads back empty.

Concretely: `NextStepsPanelBlock` had a top-level `steps_label` field and a `steps` repeater
with a `label` sub-field. Both derived `field_next_steps_panel_block_steps_label`, so every
step rendered without its bold lead-in — `/signedup/` shipped "1. : A Hiring Manager will…"
instead of "1. Onboarding Meeting: A Hiring Manager will…".

There is no error, no warning and no failing test. It surfaced only in a screenshot diff.

**Rule:** for a repeater named `X`, do not add a top-level field named `X_<something>` that
matches one of `X`'s sub-field names. Fixed here by renaming the top-level field to
`intro_label`.

Check the generated keys for any block with a repeater:

```bash
wp eval 'foreach (acf_get_field_groups() as $g) {
  if (! str_contains($g["key"], "your_block")) continue;
  foreach (acf_get_fields($g) as $f) {
    printf("%s => %s\n", $f["name"], $f["key"]);
    foreach ((array) ($f["sub_fields"] ?? []) as $sf) printf("  sub %s => %s\n", $sf["name"], $sf["key"]);
  }
}'
```

### 10. One broken pattern file takes down the entire site and `wp` CLI

`app/setup.php` registers every file under `patterns/` at boot, so a fatal in any one of them —
a missing `BlockDefaults` helper, an undefined array key in a shared template — 500s every page
and every `wp` command, not just the page being edited.

This bit twice on 2026-09-15: once from a block referencing a helper that had not been written
yet, and once from `resources/patterns/steal-campaign.php` dereferencing `$steal['logos']`
unconditionally when only one of its three configs defined that key.

Not a bug to fix so much as a working habit: **`php -l` a pattern or block before saving it**,
and treat a shared partial's optional keys as optional (`$x['k'] ?? []`). It is especially
easy to miss when several people work the same checkout, because the breakage appears on
someone else's page.

### ~~11. Two images differing only by extension overwrote each other's WebP~~ — ✅ **FIXED 2026-09-15**

`vite/theme-images.js` derived the WebP name by swapping the extension, so `Frame-76-5.jpg`
and `Frame-76-5.png` — both real, distinct images used side by side on
`/ecommerce-virtual-assistant/` — both emitted `Frame-76-5.webp`. Whichever the build
processed second won, and `preferWebp()` then served that one file for both references, so one
card rendered the other card's picture with no error anywhere.

The build now detects a stem collision within a directory and emits the appended form
(`Frame-76-5.jpg.webp`, `Frame-76-5.png.webp`); `preferWebp()` prefers that form when it
exists and otherwise falls back to the swapped name, so every non-colliding file is unchanged.

### ~~12. A test suite that was red or green depending on run order~~ — ✅ **FIXED 2026-09-15**

`RoutesTest` and `GatedDownloadTest` both guarded route registration on `Route::has('api.health')`,
but `GatedDownloadTest` registers **only** `routes/api.php` behind it. When it ran first,
`RoutesTest`'s guard short-circuited, `routes/web.php` never loaded, and five web-route
assertions failed — while the file still passed in isolation. Identical code produced a red run
and a green run back to back.

Each guard now checks a route name its own file defines (`api.health` for the API file,
`funnel.book-consultation` for the web file). **The lesson generalises: a shared sentinel for
"has this fixture been set up" is only safe when every writer sets up the same thing.**

### 13. `=== false` against a block field will not do what you want

ACF true/false values arrive as `true`/`false` from `get_field()` but as the **string** `'0'`
or `'1'` when they come through a block's `data` attributes, which is how every pattern passes
them. `PartnerHeroBlock` guarded its talent row with `if ($show === false)`, so a pattern
passing `'show_talent' => '0'` still got the row — a 410px grid of 24 portraits the page was
explicitly asking not to render.

Compare loosely, or coerce (`filter_var($v, FILTER_VALIDATE_BOOLEAN)`), and keep `null`
distinct from `false` when an unset field is supposed to mean "on". Fixed for that block; the
pattern is worth checking wherever a block reads a boolean.

### ~~14. Two Pint configurations enforced two different standards~~ — ✅ **FIXED 2026-09-15**

The repo-root `pint.json` used the `per` preset while the theme's used `laravel`, so the same
file could pass one and fail the other. Pointing the root binary at theme paths reformatted
them into a state CI rejects (CI runs the theme's, from the theme directory) — that is how
staging broke on 2026-09-15, and the root config's theme exclusion was the mitigation.

The root is now `laravel` too. The direction mattered: the root governs **10** PHP files and
the theme **562**, so unifying the other way would have rewritten the entire theme. Five
non-theme files were reformatted once (`config/application.php`,
`config/environments/staging.php`, `web/wp-config.php`, `web/app/mu-plugins/bedrock-autoloader.php`,
`web/index.php`) and verified to still boot WordPress and serve pages. A pre-existing `per`-only
failure in `rl-sync-body-auth.php` resolved itself in the process.

Verified by running the **root** binary across all 562 theme files: it now passes, so the two
configs genuinely agree rather than merely avoiding each other. The exclusion stays so one
file has one owner.

## ~~Dead configuration~~ — ✅ **FIXED 2026-09-15**

Eight keys were listed here as read by nothing. **Seven were; the eighth was not.**
`STRIPE_TEST_KEY` / `STRIPE_TEST_SECRET` went live when the embedded card checkout was
ported from `rl-elementor-blocks` — `config/services.php` reads them as
`stripe.test_publishable_key` / `stripe.test_secret_key`, and
`StripePaymentIntentGateway` uses them whenever `STRIPE_TEST_MODE` is on.
[configuration.md](configuration.md) had this right and this file did not. **They were
kept.**

The seven genuinely dead keys were deleted from `.env`, each verified unreferenced across
`app/`, `config/`, `resources/`, `routes/` and the Bedrock `config/` first:

| Key | Reality |
| :--- | :--- |
| `ZEROBOUNCE_API_KEY` | No email-validation integration exists |
| `REFERRAL_WEBHOOK_SECRET` | The code reads `REFERRAL_WEBHOOK_URL` |
| `BARBA_ENABLED` | No Barba.js anywhere |
| `LOCOMOTIVE_ENABLED` | No Locomotive Scroll anywhere |
| `PRISM_SERVER_ENABLED` | `PrismAiAuditor` does not read it |
| `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` | The code reads `GOOGLE_CALENDAR_CLIENT_ID`/`_SECRET` |

Conversely, six keys the code *does* read appeared in no `.env` and no `.env.example`:
`HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_PORTAL_ID`, `CALENDLY_WEBHOOK_SIGNING_KEY`,
`CALENDLY_LIVE_CALL_EVENT_TYPE`, `LIVE_CALL_MEET_URL`, `STRATEGY_CONSULTANT_EMAIL`. All
six were added, along with `SENTRY_LARAVEL_DSN` (issue 8 below, and a cutover gate).

HubSpot was the consequential one: with neither an environment token nor an
admin-configured one, `HubSpotGateway` silently no-ops. A lead is captured, the audit log
records the dispatch, and no CRM contact is ever created.

`.env.example` was regenerated from [configuration.md](configuration.md): every key the
code reads, grouped by domain, with a one-line note on what degrades when it is blank.

**One trap worth knowing, found while doing this.** `env('KEY', 'default')` does *not*
fall back when the key is present but empty — Dotenv sets it to `''` and the default is
lost. So `LIVE_CALL_MEET_URL=` would have blanked
`LiveCallAvailabilityRouter::DEFAULT_MEET_URL`, and `STRATEGY_CONSULTANT_EMAIL=` would
have blanked the `team@remoteleverage.com` attendee. Every key with a working code default
is therefore **commented out** in both files rather than present and empty; only keys with
no default are present and blank. Verified: `wp eval 'var_dump(env("LIVE_CALL_MEET_URL",
"DEFAULT-KEPT"));'` returns `string(0) ""` when the key is present and empty.

The Notion integration was deleted on 2026-09-15; `NOTION_API_KEY` /
`NOTION_PARTNERS_DATABASE_ID` appear in neither file and should not return.

## Stale documentation

The user's instinct that the old README was "just a proposal" was right, and the drift was not limited to it.

### ~~`plan.md` (repo root) — worst offender~~ — ✅ **ARCHIVED 2026-09-15**

Phases 3–8 all read `0% Completed`. In reality Phase 3 (design tokens), Phase 4 (Livewire), Phase 5 (38 blocks) and Phase 6 (Blade templates) are essentially complete, and Phase 7 (testing/CI) is done bar visual regression. Anyone reading `plan.md` for status gets a badly wrong picture.

Phase 7 also still lists *"Verify Customer.io lead identification upon Gravity Forms submission"* — Gravity Forms was retired by ADR-0008.

**Done:** archived to [`archive/plan-root.md`](archive/plan-root.md) with a banner pointing at the live sources of truth. A hand-maintained percentage tracker drifts by default; the gate tables do not, because they are derived from verifiable facts. The Phase 8 launch targets it originated were carried into [`performance-baseline.md`](performance-baseline.md), which now holds them alongside real measurements.

### ~~`PAGE-MIGRATION-STATUS.md` (repo root)~~ — **resolved 2026-09-14**

Was listing the four comparison pages and `/referral/` as "🔧 Needs migration" when both the checklist and the database showed them done.

**Resolved by inverting the relationship rather than archiving the file.** `PAGE-MIGRATION-STATUS.md` was rewritten against the live database and is now the **source of truth for the 44-URL migration scope**; `content-migration-checklist.md` was demoted to a read-only production inventory. The "two overlapping trackers" liability is gone because they no longer track the same thing — one is scope, the other is what exists on production.

### ~~`docs/adr-status.md`~~ — ✅ **FIXED 2026-09-15**

WR-105 said *"No `.github/workflows` directory anywhere in the repo."* Both `ci.yml` and `deploy-staging.yml` exist and run; the row is now marked **Done** with the workflow files as evidence. The other "on hold" rows were left as they are — they were not re-verified on this pass, so treat them as unaudited rather than confirmed.

### `docs/content-migration-checklist.md`

~~Refers to `App\Support\LegalDocument` with 9 Pest tests.~~ ✅ **Corrected 2026-09-15** to `App\Support\DocumentOutline` (`tests/Unit/DocumentOutlineTest.php`).

**Separately, scope closure changed what this file is.** It was written as a "migrate everything" audit of 235 production pages; 191 of those are now discarded. A banner marks every unticked box void and flags §§2, 3, 4, 5, 7 as out of scope. Anyone reading it for work items will otherwise be badly misled.

### The archived READMEs

Both are now in `docs/archive/` with a banner. For the record, what they got wrong:

| Claim | Reality |
| :--- | :--- |
| "Sage 11 + Acorn 5" (root README) | Sage 10 + Acorn 6 |
| "Vite 6" | Vite 8 |
| "~16 unified blocks" | 38 |
| "21+ block patterns" | 54 |
| "53 tests, 251 assertions" | 422 tests, 1368 assertions |
| "5 bounded contexts" | 7 (Sync and Lead are absent from the proposal entirely) |
| `PartnerPortalDashboard`, `PartnerRegistrationForm` | `ReferrerPortalDashboard`, `ReferrerRegistrationForm` |
| `RegisterPartnerAction` | `RegisterReferrerAction` |
| Magenta `#F8248A` / `#E91E63` | `#F90066` / `#D90057` |
| Block table listing 18 blocks | 20 blocks omitted, including every `about-*`, `comparison-*` and `case-study` block |
| No mention of | Sync domain, AI/MCP abilities, `case_study` CPT, Calendly/Referral/Marketing/Environment Sync admin screens, the Calendly token pool |

## Design and process observations

These are not bugs, but they are things a newcomer should know and the team may want to revisit.

### Synchronous listeners are a latency risk at production volume

No listener implements `ShouldQueue` and no queue worker is deployed (WR-106, on hold). This is internally consistent and was a deliberate decision — but it means a single lead submission makes outbound HTTP calls to HubSpot, Slack, PostHog, Customer.io and a webhook **inside the user's request**, and the latency is additive. Staging traffic will not surface this; production traffic on the booking funnel might.

**Proposal:** before cutover, measure the p95 of `CaptureLeadAction` end to end with all integrations live. If it exceeds ~800ms, provisioning a queue worker becomes a launch blocker rather than a deferred nicety.

### ~~Livewire components are registered under two names each~~ — ✅ **FIXED 2026-09-15**

`LivewireServiceProvider` registered all seven components twice — `booking.multistep-booking-wizard` and `multistep-booking-wizard`, and so on — so there was no canonical name and a grep for usage found only half the call sites.

The namespaced form is now canonical and the only one registered. **No migration was needed:** every call site already used the namespaced form. A grep over Blade views, `patterns/`, `resources/patterns/`, PHP, JS and `wp_posts.post_content` found zero references to any bare alias, so all seven were removed outright.

**Regression guard:** `tests/Unit/LivewireComponentNamesTest.php` — every registered name must be namespaced and unique, and every `<livewire:…>` tag in the theme must resolve to a registered name (the failure message names the offending template).

**Verified:** `/hire-va-4/`, `/partners/`, `/tools/signature-generator` and `/referrer-register/` all still return 200 with a hydrated `wire:snapshot` island.

### The documentation tree is split across two directories

ADRs live in `doc/adr/` at the repository root; everything else lives in `web/app/themes/remote-leverage/docs/`. The split is historical and mildly confusing — `doc` and `docs` one character apart is a genuine footgun.

Merging them was deliberately **not** done in this pass: about ten source files carry `docs/…` references in comments that are relative to the theme, and moving the tree would silently break all of them for no functional gain.

**Proposal:** if this is worth tidying, move `doc/adr/` → `web/app/themes/remote-leverage/docs/adr/` (the smaller move, and ADRs have no inbound code references), rather than moving the larger tree.

### ~~`web/app/themes/remote-leverage/plan.md` is a second stale plan~~ — ✅ **ARCHIVED 2026-09-15**

The theme carried its own near-duplicate `plan.md` alongside the root one — same drift, doubled. Both are now in `docs/archive/` (`plan-root.md`, `plan-theme.md`).

### ~~A stray SQL file sits in the theme root~~ — ✅ **RESOLVED 2026-09-15**

`remoteleveragev2-2026-09-14-a2d62b7.sql` — zero bytes, presumably an interrupted `wp db export`. It is correctly gitignored (`*.sql`, theme `.gitignore:7`), so this was housekeeping rather than a repository problem.

Gone as of 2026-09-15: `find . -iname '*.sql'` across the whole checkout (outside `vendor/` and `node_modules/`) returns nothing.
