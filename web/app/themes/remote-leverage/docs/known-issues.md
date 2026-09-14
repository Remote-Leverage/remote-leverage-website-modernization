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

### 5. Calendly webhook signatures are unverified

`config/services.php` reads `CALENDLY_WEBHOOK_SIGNING_KEY`, but the key is absent from `.env` and from `.env.example`. With no key configured, `/api/webhooks/calendly` accepts unsigned payloads — anyone who can reach the endpoint can flip a lead to `booked` or `canceled`.

**Proposed fix:** set the key in every environment, and make `CalendlyWebhookController` reject requests when no signing key is configured rather than falling through to accept.

### 6. Sentry is installed but silent

`sentry/sentry-laravel` is a dependency, `config/sentry.php` is fully populated, and `@sentry/browser` is in `package.json` — but no `SENTRY_LARAVEL_DSN` or `SENTRY_DSN` is set in `.env`. Nothing is reported from any environment.

**Proposed fix:** set the DSN for staging now, and treat "errors reported" as a cutover gate for production.

## Dead configuration

Eight keys sit in `.env` and are read by nothing. All eight are documented in the archived README's environment reference as though they were live, which is how they survived.

| Key | Reality |
| :--- | :--- |
| `ZEROBOUNCE_API_KEY` | No email-validation integration exists |
| `REFERRAL_WEBHOOK_SECRET` | The code reads `REFERRAL_WEBHOOK_URL` |
| `BARBA_ENABLED` | No Barba.js anywhere |
| `LOCOMOTIVE_ENABLED` | No Locomotive Scroll anywhere |
| `PRISM_SERVER_ENABLED` | `PrismAiAuditor` does not read it |
| `STRIPE_TEST_KEY`, `STRIPE_TEST_SECRET` | Test mode comes from using test values in `STRIPE_KEY`/`STRIPE_SECRET` |
| `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` | The code reads `GOOGLE_CALENDAR_CLIENT_ID`/`_SECRET` |

Conversely, five keys the code *does* read appear in no `.env` and no `.env.example`:

`HUBSPOT_ACCESS_TOKEN`, `HUBSPOT_PORTAL_ID`, `CALENDLY_WEBHOOK_SIGNING_KEY`, `CALENDLY_LIVE_CALL_EVENT_TYPE`, `LIVE_CALL_MEET_URL`, `STRATEGY_CONSULTANT_EMAIL`.

HubSpot is the consequential one: with neither an environment token nor an admin-configured one, `HubSpotGateway` silently no-ops. A lead is captured, the audit log records the dispatch, and no CRM contact is ever created.

**Proposed fix:** delete the eight dead keys from `.env`, and regenerate `.env.example` from [configuration.md](configuration.md) so every key the code reads is present and empty, grouped by domain, each with a one-line note on what degrades when it is blank.

## Stale documentation

The user's instinct that the old README was "just a proposal" was right, and the drift was not limited to it.

### `plan.md` (repo root) — worst offender

Phases 3–8 all read `0% Completed`. In reality Phase 3 (design tokens), Phase 4 (Livewire), Phase 5 (38 blocks) and Phase 6 (Blade templates) are essentially complete, and Phase 7 (testing/CI) is done bar visual regression. Anyone reading `plan.md` for status gets a badly wrong picture.

Phase 7 also still lists *"Verify Customer.io lead identification upon Gravity Forms submission"* — Gravity Forms was retired by ADR-0008.

**Proposed fix:** either update the percentages and drop the Gravity Forms line, or retire `plan.md` into `docs/archive/` and let the README's status section be the single source. Recommendation: archive it. A hand-maintained percentage tracker drifts by default; the README's gate table does not, because it is derived from verifiable facts.

### ~~`PAGE-MIGRATION-STATUS.md` (repo root)~~ — **resolved 2026-09-14**

Was listing the four comparison pages and `/referral/` as "🔧 Needs migration" when both the checklist and the database showed them done.

**Resolved by inverting the relationship rather than archiving the file.** `PAGE-MIGRATION-STATUS.md` was rewritten against the live database and is now the **source of truth for the 44-URL migration scope**; `content-migration-checklist.md` was demoted to a read-only production inventory. The "two overlapping trackers" liability is gone because they no longer track the same thing — one is scope, the other is what exists on production.

### `docs/adr-status.md`

WR-105 says *"No `.github/workflows` directory anywhere in the repo. Not being worked for now."* Both `ci.yml` and `deploy-staging.yml` now exist and run. WR-105 is done, not on hold.

**Proposed fix:** mark WR-105 done with the workflow files as evidence, and re-verify the other "on hold" rows on the same pass.

### `docs/content-migration-checklist.md`

Refers to `App\Support\LegalDocument` with 9 Pest tests. The class is `App\Support\DocumentOutline` (renamed, tests in `tests/Unit/DocumentOutlineTest.php`).

**Proposed fix:** one-line correction. Low value now — the document was demoted to a read-only production inventory on 2026-09-14 and no longer drives work.

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

### Livewire components are registered under two names each

`LivewireServiceProvider` registers all seven components twice — `booking.multistep-booking-wizard` and `multistep-booking-wizard`, and so on. That is presumably backwards compatibility for existing templates, but it means there is no single canonical name, and a grep for usage misses half the call sites.

**Proposal:** pick the namespaced form as canonical, migrate the templates, and drop the bare aliases.

### The documentation tree is split across two directories

ADRs live in `doc/adr/` at the repository root; everything else lives in `web/app/themes/remote-leverage/docs/`. The split is historical and mildly confusing — `doc` and `docs` one character apart is a genuine footgun.

Merging them was deliberately **not** done in this pass: about ten source files carry `docs/…` references in comments that are relative to the theme, and moving the tree would silently break all of them for no functional gain.

**Proposal:** if this is worth tidying, move `doc/adr/` → `web/app/themes/remote-leverage/docs/adr/` (the smaller move, and ADRs have no inbound code references), rather than moving the larger tree.

### `web/app/themes/remote-leverage/plan.md` is a second stale plan

The theme carries its own `plan.md` alongside the root one. Same drift problem, doubled.

**Proposal:** archive both.

### A stray SQL file sits in the theme root

`remoteleveragev2-2026-09-14-a2d62b7.sql` — zero bytes, presumably an interrupted `wp db export`. It is correctly gitignored (`*.sql`, theme `.gitignore:7`), so this is housekeeping rather than a repository problem — just delete it.
