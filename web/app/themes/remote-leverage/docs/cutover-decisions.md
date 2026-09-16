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

### 24b. The mobile header matches production's logo, not its chrome
The mobile Consultation pill was dropped and the logo is now **200×23.38, centred, identical to
production to the pixel at 320/375/400px**. Two measured differences were left in place
deliberately:

| | Production | v2 | Why v2 keeps its own |
| :--- | ---: | ---: | :--- |
| Header height | 63.38px | 81px | Changing it moves the sticky-header offset sitewide |
| Hamburger / gutter | 33px / 10px | 40px / 16px | 40px is a far better tap target than 33px |

Consequence to know: **"Book a Consultation" → `/vacalendar` in the drawer is now the only mobile
path to booking.** It is present and verified, and the markup carries a comment saying so, but
the margin for error there is zero.

### 24c. Two upscaled images are left as they are
`sales-talent.png` (536px source in a `max-w-[627px]` box) and `SPU-Image-1.png` (560px in
`max-w-[662px]`) render upscaled and slightly soft. They match production's own sizing, so
capping the display size would diverge from production and replacing the sources is a content
task. Cosmetic, recorded rather than fixed.

### 24d. The font-family merge was measured and **backed out**
Decision 24 authorised merging `--font-sans` and `--font-display` (two downloads of the same
outline at different optical-size pins, ~48KB). It was measured and **not shipped** —
`resources/css/app.css` is unchanged.

The premise was inverted: the merge does not touch headings, which stay on `--font-display`. It
repoints `--font-sans`, so it changes **body copy and card headings**. Width change: −1.07% at
16px, −3.22% at 20px, −9.67% at ≥32px.

`/blog/` settled it. Its noise floor is **zero** (two captures of the unchanged page are
byte-identical) and the merge changed **14.04%** of the page: "How Much Does a Virtual Legal
Assistant Cost?" drops from 3 lines to 2 and pulls everything below it up 25px, moving half the
index. 48KB does not buy a silently reflowed blog archive.

Costed but not recommended blind: pairing the merge with `font-optical-sizing: none` cuts the
16px discrepancy from −1.07% to ~0.07%. It needs its own verification pass; the numbers are in
`performance-baseline.md` Part 4.

### 24e. Font preload shipped — the win is stability, not LCP
Two faces preloaded (`inter-display-latin.woff2`, `inter-latin-wght-normal.woff2`), chosen by
measuring which faces six pages actually request. The latin-ext pair and the italic are never
requested above the fold; preloading them would put ~125KB of dead weight on every visit.

**LCP did not move, and the doc says so.** On both measured pages the LCP element is text, which
`font-display: swap` already paints instantly in the fallback — preload changes which typeface
that paint uses, not when. What it fixes is the swap itself (the face now lands *before* first
paint instead of 0.6–0.9s after) and the reflow that followed: **`/case-study/` CLS 0.0502 →
0.0013**, of which 0.0488 was font-attributable.

### 6b. Customer.io CDP needs a new credential — `CUSTOMERIO_CDP_WRITE_KEY`
Decision 6's "match production — use CDP" carried a trap. **CDP takes a write key, not a site
id** — they are different Customer.io products, and production's write key is not this install's
`CUSTOMERIO_SITE_ID`. Pointing CDP at a site id 404s the asset URL and **silently queues every
event forever**, which fails in the worst possible way: no error, no events.

So the browser snippet is gated on a new `CUSTOMERIO_CDP_WRITE_KEY` rather than on `SITE_ID`.
`SITE_ID`/`API_KEY` remain server-side for `CustomerIOClient`'s Track API v1. **Blank today —
someone must set it or the browser sends nothing.**

### 24f. Blog image weight fixed in the media library, not the theme pipeline
The 2.6MB/3.1MB pages were **entirely** WordPress media library (`web/app/uploads/`), not
`resources/images/pages/` → `public/images/`. The `themeImages()` plugin, the
`BlockDefaults::pageImg()` filename contract and `public/` were untouched.

The worst offender was not the featured image but the **talent carousel**: five 771×1024 PNGs
(197–666KB each) served untouched into a **220×265px card** — 2.48MB of one post's 3.06MB, on
all 88 posts carrying it. WordPress had already generated `226x300` and `768x1020` subsizes of
every one; nothing used them.

| Page | Perf | LCP | Total weight |
| :--- | :--- | :--- | :--- |
| `/blog/` | 90 → **99** | 3.61s → **1.56s** | 2.74MB → **0.47MB** |
| a single post | 79 → **99** | 4.71s → **2.10s** | 3.27MB → **0.59MB** |
| `/case-study/` (control) | 100 → 100 | unmoved | unmoved |

A real bug fixed alongside: `filterContentImgTag()` rewrote only `src`, but a matching `srcset`
candidate always outranks `src` — so for any content image with a srcset, the WebP was **never
requested**. Reasoned about but not measured: neither test post has in-content images.

Still open: no registered size sits between 226px and 768px, so a 220px card at 2x asks for
577px and gets 768px. Closing that needs `add_image_size` plus `wp media regenerate` across 119
posts — a media migration, not a template change.

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

### 28. The canonical sweep happens **after** the DNS flip, not before
All 180 imported canonicals were written while nothing rendered them, so a wrong destination was
invisible — two (`/hire-va/` and `/hire-va-isolated-form/`, both canonicalised at the homepage on
production) were caught by accident rather than by looking.

**Decision: sweep after cutover, on real rendered output, rather than against stored postmeta
now.** The trade is understood and deliberate: any remaining bad canonical is live and indexable
for the length of the gap. Accepted because rendered output is the thing that actually matters
and post-flip data is real rather than simulated. **This is a scheduled task, not a closed one —
see the post-cutover checklist below.**

### 29. The blog image size gap is left open
No registered size sits between 226px and 768px, so a 220px card at 2x requests 577px and is
served a 768px file (30–61KB WebP each). Closing it needs `add_image_size` plus
`wp media regenerate` across 119 posts — a media migration, not a template change.

**Decision: leave it.** The win is already banked (a single post went 3.27MB → 0.59MB, 79 → 99
mobile). The remaining few tens of KB per card on retina screens does not justify rewriting the
uploads directory.

### 30. The `filterContentImgTag` srcset fix ships unmeasured
It rewrote only `src`, but a matching `srcset` candidate always outranks `src` — so for any
content image with a srcset the WebP was **never** requested. Fixed, but neither measured post
has in-content images, so the fix was reasoned about rather than observed.

**Decision: accept the reasoning.** The mechanism is not ambiguous. Recorded here because it is
the one unverified claim in the image work, and a post with in-content images would settle it in
minutes if anyone wants it later.

---

## Security and tracking

### 31. WordFence **is** ported — reversed 2026-09-16
Originally recorded as "not ported, the edge WAF replaces it". **That decision was reversed the
same week**, before any work followed from it. `wp-plugin/wordfence: ^9.0` is now a composer
dependency and installs to `web/app/plugins/wordfence` like every other plugin (gitignored;
it ships through the deploy, not the repo).

Production's status, unverifiable from outside at the time, is now confirmed from the 2026-08-27
backup: **WordFence 9.0.0 is installed and the WAF is active** (`wordfence-waf.php` at the web
root, the `auto_prepend_file` half). The earlier "could not confirm" note was an artefact of
Cloudflare returning 403 for every `readme.txt` probe, including plugins known to be installed.

The operational caveats from the original entry still hold and are implementation notes, not
objections:

- The container is immutable and rebuilt on every deploy, so anything WordFence writes to disk —
  `.user.ini` / `auto_prepend_file` for firewall optimisation, scan state — does not survive.
  Expect to re-run firewall optimisation after a deploy, or to bake it into the image.
- Scans against a read-only application tree will report the tree as unchanged, which is the
  intended property of an immutable deploy rather than a finding.
- Staging sits behind CloudFront and production behind Cloudflare. WordFence now runs *in addition
  to* that edge layer, not instead of it.

Settings are not carried over automatically: the plugin's configuration lives in the production
database, and moving it is a separate step from adding the dependency.

**Production runs three security plugins, and only WordFence is doing enforcement** (audited
2026-09-16 from the backup, so only WordFence is ported):

| Plugin | State on production | Why it is not ported |
| :--- | :--- | :--- |
| `wordfence` 9.0.0 | Active, WAF wired via `auto_prepend_file` since 2025-05-29 (`wordfence-waf.php`, logs to `wp-content/wflogs/`) | **Ported** — this entry |
| `wp-fail2ban` 5.4.1 | Active, but **no `WP_FAIL2BAN_*` constant is set in `wp-config.php`**, so it runs on defaults | Defaults only write syslog lines; blocking needs matching `fail2ban` jails on the host. Production is managed hosting and v2 is ECS — neither gives us host jails, so it would be log noise |
| `malcare-security` 6.69 | Active | Its firewall wants `auto_prepend_file` too (`protect/prepend`), and WordFence already owns it — only one can. Whether it is even connected to the MalCare service is not observable from the backup |

So "production has three, v2 has one" is not the gap it first looks like: one enforces, one is
inert without host access, and one cannot hold the hook it needs. Neither omission is a silent
downgrade.

Unverified, and deliberately so: no `wf*` or `bv*` tables and no `wp-content/wflogs/` are present
in the backup. That is far more likely to be the backup tool excluding security-plugin data than
evidence the plugins never ran — it is not treated as a finding either way.

### 32. v2 inherits **both** production GTM containers
Production serves two containers, `GTM-53JDTQCZ` and `GTM-P4KZNJWL` (read off its HTML,
2026-09-15). Site Kit is connected to **both**, for straight parity: every tag already living in
them — LinkedIn Insight, Meta Pixel and the rest — keeps firing exactly as it does today, so the
cutover carries no ad-spend risk. Consolidating to one container is a separate, post-cutover
question.
*Refines* the "connect the GTM container" item, which assumed a single container.

---

## Post-cutover checklist

Things deliberately deferred to after the DNS flip, because they cannot be answered — or are not
worth answering — beforehand. **These are scheduled, not closed.**

| # | Do this right after the flip | Why it waited |
| :--- | :--- | :--- |
| 1 | **Confirm canonicals render** on a handful of pages | Suppressed on dev and staging by `DISALLOW_INDEXING`; proven correct locally by flipping the constant, but production is the first place it is true in normal operation (§17 of known-issues) |
| 2 | **Sweep all 180 canonical destinations** for any pointing somewhere other than themselves | Decision 28. Two known-bad were fixed; the rest were never observable |
| 3 | **Confirm both webhook endpoints return 200, not 503** | They fail closed; staging returns 503 today because the secrets are unset |
| 4 | **Take the real performance baseline** | Local numbers are a floor — no CDN, no page cache, self-signed cert. The Phase 8 gate asks for staging/production |
| 5 | **Verify transactional email actually sends** | Every `MAIL_*` value is currently empty |
| 6 | **Confirm Sentry is receiving events** | DSN unset; "errors reported" is a cutover gate |
| 7 | **Check production does *not* emit `noindex`** | The inverse of the dev/staging check — `DISALLOW_INDEXING` must be absent there |

---

## Still open — not decisions, but things only other people can supply

- **Secrets:** `STRIPE_WEBHOOK_SECRET`, `CALENDLY_WEBHOOK_SIGNING_KEY` (both now blocking),
  `SENTRY_LARAVEL_DSN`, `MAIL_*`, `HUBSPOT_ACCESS_TOKEN` + `HUBSPOT_PORTAL_ID` (leads silently
  never reach the CRM without these), `POSTHOG_API_KEY`, `SLACK_WEBHOOK_URL`.
- **Admin:** activate Google Site Kit and connect **both** GTM containers, `GTM-53JDTQCZ` and `GTM-P4KZNJWL` (decision 32).
- **From the partners:** referral intake forms for Lexgo and Lano, a referral destination for
  Oyster, logos for all three. See `domains/partner-hub.md`.
- **From whoever knows:** the real `legal_last_updated` revision dates for the privacy policy and
  terms of use, which currently fall back to the migration date.
