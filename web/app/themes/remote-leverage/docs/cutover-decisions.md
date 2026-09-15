# Cutover decisions

Every open question that was blocking the production cutover, and what was decided. Recorded
**2026-09-15**, when the whole backlog was cleared in one pass.

This file exists because these decisions kept getting re-litigated from stale notes. Where a
decision contradicts something written elsewhere in `docs/`, **this file wins** and the other
document is wrong until corrected.

Each row names the decision, why it went that way, and what it changed. "Supersedes" points at
the document that said otherwise.

---

## Platform and infrastructure

### 1. Production deploy pipeline is owned elsewhere
The production ECS service, ECR repository, secret store, `deploy-production.yml`, DNS cutover
runbook and rollback plan are **handled outside this workstream**. They remain hard cutover
gates; they are simply not tracked here any more.
*Supersedes* `deployment.md` §"Production — what does not exist yet" as a work list.

### 2. Production's media library is not being ported
No reconciliation against production's attachment library is planned. Page art is tracked as
source in `resources/images/pages/` and generated into `public/images/` at build time by the
`themeImages()` Vite plugin, so nothing about the cutover depends on a library migration.
*Supersedes* the "media library has never been reconciled" gap in `production-cutover.md`.

### 3. Webhook signing secrets are a new, hard gate
Both webhook endpoints now **fail closed** — 503 with no secret configured, 403 on a bad
signature, and the event is acted on in neither case. `STRIPE_WEBHOOK_SECRET` and
`CALENDLY_WEBHOOK_SIGNING_KEY` are unset everywhere, so deploying without them takes Stripe
Connect payout events and Calendly booking events **dark**. Set both before staging.
See `known-issues.md` #7.

### 4. Queue worker (WR-106) is deferred, and reclassified
Not a latency blocker. Measured `CaptureLeadAction::execute()` p95 is **6.75 ms**; the
integrations already run `afterResponse()`. WR-106 is a **throughput and reliability** item —
PHP-FPM worker occupancy after the response, no retry, no dead-letter. Deferred deliberately.
*Supersedes* the "synchronous listeners are a latency risk" observation in `known-issues.md`.

## Payments

### 5. The deposit is collected on the existing Stripe account
`STRIPE_KEY`/`STRIPE_SECRET` — the same pair serving Connect payouts to referrers. No second
credential set. Still needs that account's webhook signing secret (decision 3).

### 5b. Staging stays in Stripe test mode; production uses the live pair
`deployment.md` said "Staging uses Stripe test keys" while decision 5 put the deposit on the
live Connect pair — a contradiction with teeth now that the webhook **fails closed**: a
test-mode event signed with a live-account secret is refused outright rather than logged.

**Resolved:** staging keeps Stripe **test** keys and its own **test-mode** webhook signing
secret; production uses the live Connect pair and its live secret. That means **two distinct
`STRIPE_WEBHOOK_SECRET` values**, one per environment — Stripe issues a separate signing secret
per webhook endpoint, so this is how it is meant to work, not a workaround.

Consequence: staging can exercise the deposit flow end to end without moving real money, and a
secret copied from the wrong environment fails loudly (403) instead of silently accepting
unverified events.

### 6. Checkout telemetry is rebuilt, not dropped
The legacy `rl_payment_gateway` widget's PostHog / Customer.io / internal-log calls were removed
during the port. Sales and ops depend on that attribution, so the funnel is re-instrumented
through the existing Tracking domain.
*Supersedes* the "telemetry was dropped" note in `stripe-payments.md`.

## SEO

### 7. `/blog/` is the canonical post archive
Not `/guides/`. It is what v2 already serves (page ID 799), all 119 posts resolve under it, and
production's `/guides/` renders the blog archive anyway. `/guides/` joins the redirect map.

### 8. `referral-program` and `ecommerce-virtual-assistant` do not inherit production's `noindex`
Both were rebuilt in v2, so production's suppression is stale rather than intentional. The other
five noindex pages (`signedup`, `vaonboardingform`, `payment`, `vastore5`,
`referral-program-thank-you-page-deposit`) inherit it correctly.

### 8b. The three pages built today stay `noindex`
`/contractoragreement/`, `/services/` and `/store/` inherited production's `noindex` and were
created *after* decision 8 was made, so they needed their own ruling. **They keep it.** They are
operational pages, not marketing: an e-sign form, a post-hire onboarding guide, and COR pricing
built as a sibling of `/vastore5/` — which is itself noindex. That is the same family as the five
that correctly inherit it, and the opposite of the two rebuilt marketing pages in decision 8.
Eight pages are now noindex: those three plus `payment`, `signedup`, `vaonboardingform`,
`vastore5`, `referral-program-thank-you-page-deposit`.

### 9b. Yoast's `/sitemap_index.xml` is the canonical sitemap, not core's
`web/robots.txt` points at it. Production already serves it and 301s core's `/wp-sitemap.xml`
onto it, so choosing core's would have changed the sitemap URL Search Console knows at exactly
the moment the site changed underneath it. Decisive technical reason: **core's sitemap cannot
read `_yoast_wpseo_meta-robots-noindex`**, so it would advertise all eight noindexed pages while
their markup says otherwise. Nothing is lost — the 301 keeps old references working.

### 9. Yoast Premium is not being bought
`config/redirects.php` already covers every discarded URL, ships with the code, survives a
database refresh, and is guarded by tests against 301 chains and dangling targets. Premium's
redirect manager is a database-resident version of what already exists in git.

### 10. `/comparison/` ships `noindex` until real copy exists
Production's is a never-filled-in internal template — literal `[X]`, "Text here Text here", a
stray "NEW SECTION" label — reproduced verbatim under the strict-fidelity rule. The URL resolves;
it stays out of the index until someone writes it.

## Content

### 10b. `/comparison/` stays database-resident, deliberately
Page 323's `post_content` is **58KB of expanded block markup, not a pattern reference** — which
`CLAUDE.md` forbids, because it drifts from the pattern and is lost on a database refresh. The
`rl:noindex` marker had to go into the database beside it for the same reason.

**Decision: leave it.** The page is still full of production's `[X]` placeholders and is a
rewrite candidate, so converting it to a pattern would be work spent preserving content that is
going to be replaced. **Known consequence: a database refresh loses this page and its noindex
marker, and it will need rebuilding.** Recorded so that is a choice rather than a surprise.

It is the only page in the repo in this state.

### 11. ADR-0005's review gate is satisfied by decision, not by queue
The 119 posts came in through `content:import-posts` rather than the audit/convert/review
pipeline, so none carries `_rl_conversion_status`. Zero posts carry `_elementor_data`, so the
letter of ADR-0005 is met. **Recorded decision: the import path made the review queue
unnecessary.** No retroactive sign-off pass.

### 12. `/referral/` (ID 213) is deleted
It renders `hire-va-4-full`, but production's `/referral/` is a *homepage* variant — 27 of 27
headings match the homepage, 4 of 27 match `/hire-va-4/`. Out of scope, so delete rather than
rework. `/hire-va-4-preview/` (ID 104), the other copy, was already trashed 2026-09-14.

### 13. `/vapricing/`, `/affiliate-program/` and `/comparison-wing-assistant-ads/` are kept
The rest of the §4a batch stays. Note `/vapricing/` is **VA role pricing**, not
Contractor-of-Record pricing — they are different products and different pages.

### 14. The `e-landing-page` CPT is dropped
Two entries, both inside the discarded set, both already redirecting. No post type is built.

### 15. The two "Live Sessions" items are pages, and just redirect
They were never missing posts. Production registers `category` on the `page` post type and both
items are pages; a term count is not post-type-scoped, which is what produced the phantom "2 on
production, 0 locally" discrepancy. Both are expired registration pages.
*Supersedes* the Live Sessions rows in `production-cutover.md` and `content-migration-checklist.md`.

### 16. Unpublished production content is an accepted risk
Drafts, private and password-protected pages were never audited — the REST API only exposes
published content without auth. **Deliberately not chased.** A missed draft is invisible until
someone asks for it; that is understood and accepted, not forgotten.

## Pages promoted from redirect to build

Three URLs were originally assigned 301s, then rebuilt once their real content was inspected.
Each one's redirect key had to be **removed** from `config/redirects.php` — a key matching a live
page slug would 301 the page away.

### 17. `/contractoragreement/` is built
Not a readable legal document: a single Elementor container wrapping a JotForm e-sign embed
(`242478488540063`). v2 already has `acf/jotform-embed` driving `/payment/` and
`/vaonboardingform/`, so it is the same block with a different form id. People are sent this link
to sign a contract; 301ing them to the homepage was the wrong answer.

### 18. `/services/` is rebuilt
**Not pricing collateral** — a ~43,000-character "Virtual Assistant Free Onboarding Guide" with
training videos, aimed at clients who have just hired. An earlier summary calling it bundle/COR
pricing was wrong.

### 19. `/store/` is built as a sibling of `/vastore5/`
Contractor-of-Record pricing: $4,200 per seat annual, with bundles at 3/5/7/10 contractors.
Built following `vastore5`'s styling decisions rather than reproducing production's Elementor
design, keeping the pricing and timer functionality. An earlier suggestion to point it at
`/vapricing/` was wrong — that page is VA role pricing, a different product.

## Redirect targets settled individually

### 20. `/hire-us-uk-now/` → `/hire-va-4/`
Production offers American and British professionals at $10–15/hr. The previous target,
`/hire-for-less/`, is Latin American VAs at $6–10/hr — a different talent pool at a different
rate, so it misstated the offer. `/hire-va-4/` is the canonical hire page.

### 21. `/anyshore/` → `https://anyshore.ai/` (external)
The page body is one iframe pointing at a Replit-hosted prototype. It redirects off-site to the
product's own domain. This is the **first external target in the map**, which previously only
supported site-relative paths.

### 22. `/tools/` → `/`
A deprecated old tools page, gated behind a login on production (302 to the public). Not
investigated further, by decision. v2's own signature generator now lives at `/social-media-kit/`; `/tools/signature-generator` was removed 2026-09-15 and 301s there.

## Front-end

### 23. The case-study sub-nav matches production exactly
Production renders the CASE STUDIES / TALENT PROFILES / REVIEWS bar **only on single `case_study`
posts** — it is not shared with `/reviews/`, despite what `production-cutover.md` said. v2 had
extended it to the archive and `/reviews/`; that extension was reverted for strict parity.
TALENT PROFILES points at `/samples/`.

### 24. The 843KB font and the 9px mobile overflow are fixed
`InterVariable.ttf` loaded on every page — largest asset on five of seven measured pages, 82% of
`/case-study/`'s weight — while the same outline already shipped as subsetted woff2. The mobile
header's hamburger overflowed the viewport by 9px at 400px site-wide.

### 25. The Calendly preflight is fixed
`findExistingInvitee()` cost a measured 4.0s p50 / 6.1s p95 **synchronously** inside booking
submit: 2 sequential GETs per pooled token across 4 tokens, never short-circuiting when there was
no prior booking.

## Gated downloads

### 26. The Impact Report captures, then downloads
Production's real post-submit behaviour lives in Gravity Forms form 33's confirmation settings,
which are not visible without production admin. Capture-then-download is a **deliberate
assumption**, not observed parity. If production emails a link instead, only the delivery half
changes.

### 27. A failed lead capture still delivers the file
If `CaptureLeadAction` throws, the error is logged and the PDF is served anyway. The visitor kept
their side of the bargain, and the booking wizard treats a failed partial capture the same way.

---

## Still open — not decisions, but things only other people can supply

- **Secrets:** `STRIPE_WEBHOOK_SECRET`, `CALENDLY_WEBHOOK_SIGNING_KEY` (both now blocking),
  `SENTRY_LARAVEL_DSN`, `MAIL_*`, `HUBSPOT_ACCESS_TOKEN` + `HUBSPOT_PORTAL_ID` (leads silently
  never reach the CRM without these), `POSTHOG_API_KEY`, `SLACK_WEBHOOK_URL`.
- **Admin:** activate Google Site Kit and connect the GTM container.
- **From the partners:** referral intake forms for Lexgo and Lano, a referral destination for
  Oyster, logos for all three. See `domains/partner-hub.md`.
- **From whoever knows:** the real `legal_last_updated` revision dates for the privacy policy and
  terms of use, which currently fall back to the migration date.
