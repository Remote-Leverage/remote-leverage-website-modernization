# Performance baseline

First measurement of the cutover gate that [`production-cutover.md`](production-cutover.md) lists
as 🔴 **"Never measured"** — *Performance baseline met (mobile 96+, LCP < 1.2s, CLS 0.00)* — and of
the `CaptureLeadAction` p95 decision rule proposed in
[`known-issues.md`](known-issues.md) (“Synchronous listeners are a latency risk at production volume”,
since **reclassified** on the strength of what is measured below).

Measured 2026-09-15 against **local Herd** (`https://remoteleverage-v2.test`), after
`npm run build`. Nothing was changed to produce these numbers — this is a measurement pass only.

---

## ⚠️ Read this before quoting any number below

**These are local dev numbers. They are a floor and a relative signal, not the number that decides
the gate.** The gate in `plan.md` Phase 8 says *"Run Google PageSpeed Insights on Staging"*; this
was not staging. Specifically:

| Local condition | Effect on the numbers |
| :--- | :--- |
| No CDN, no edge cache | Every asset served from one local nginx. Production CloudFront would change the byte-delivery picture completely. |
| No full-page cache, no persistent object cache | TTFB below is a cold-ish WordPress render every time. |
| Self-signed certificate | `--ignore-certificate-errors` was passed to Chrome. |
| `DISALLOW_INDEXING` is set in `config/environments/development.php` | **Every SEO score below is depressed by exactly one audit, `is-crawlable`.** The real SEO score is ~15–20 points higher. Do not act on the SEO column. |
| Dev machine also runs the DB, PHP-FPM and Chrome | Run-to-run variance is high — see the per-run columns. |
| `public/` assets are the real production Vite build | ✅ This part *is* representative — `npm run build` was run first and the served hashes match `public/build/assets/`. |

**The one number that is environment-independent** is layout stability (CLS) and the *composition*
of the page (what is loaded, how big it is, what the LCP element is). Those carry to production.
Throughput timings do not.

---

# Part 1 — Lighthouse (mobile)

## Method

- `npx lighthouse@12.8.2`, default preset (**mobile**: Moto G Power emulation, simulated Slow 4G,
  4× CPU throttle), headless Chrome 
- **3 runs per page**, reported value is the **median** of the three. Per-run values are shown so
  variance is visible.
- Categories: performance, accessibility, best-practices, seo.
- One run (`/blog/outsourcing-customer-service/`, run 1) failed with
  `ERRORED_DOCUMENT_REQUEST` (HTTP 500). It was discarded and re-run. Eight subsequent
  `curl` probes of the same URL all returned 200, so it looks transient — but **a transient 500 on
  a content page is worth a look before cutover**, because nothing here explains it.

## Category scores (median of 3)

| Page | Perf | A11y | Best Practices | SEO¹ | Perf per-run |
| :--- | ---: | ---: | ---: | ---: | :--- |
| `/` | **99** | 100 | 96 | 66 | 97 / 99 / 99 |
| `/hire-va-4/` | **58** | 96 | 100 | 66 | 58 / 61 / 26 |
| `/about-us/` | **64** | 94 | 100 | 66 | 63 / 78 / 64 |
| `/blog/` | **93** | 93 | 100 | 63 | 94 / 93 / 90 |
| `/blog/outsourcing-customer-service/` | **60** | 96 | 79 | 66 | 58 / 60 / 80 |
| `/case-study/` | **100** | 98 | 79 | 54 | 100 / 100 / 100 |
| `/impact-report-2026/` | **63** | 89 | 100 | 66 | 62 / 63 / 63 |

¹ SEO is depressed on every page by `is-crawlable` failing, which is `DISALLOW_INDEXING` on local.
`/case-study/` loses a second point for `link-text` (nine identical "Read More" links).

## Core Web Vitals and the gap to target

Targets: **Performance ≥ 96**, **LCP < 1.2 s**, **CLS 0.00**.

| Page | Perf (target 96+) | LCP (target <1.2s) | CLS (target 0.00) | TBT | Speed Index | Total weight |
| :--- | :--- | :--- | :--- | ---: | ---: | ---: |
| `/` | 99 ✅ **+3** | 1.69 s 🔴 **+0.49 s** | 0.011 🔴 | 0 ms | 2.25 s | 1.18 MB |
| `/hire-va-4/` | 58 🔴 **−38** | 8.29 s 🔴 **+7.09 s** | 0.000 ✅ | 144 ms | 6.35 s | 1.33 MB |
| `/about-us/` | 64 🔴 **−32** | 6.10 s 🔴 **+4.90 s** | 0.008 🔴 | 0 ms | 5.73 s | 1.41 MB |
| `/blog/` | 93 🔴 **−3** | 3.19 s 🔴 **+1.99 s** | 0.000 ✅ | 0 ms | 1.86 s | 3.50 MB |
| `/blog/outsourcing-customer-service/` | 60 🔴 **−36** | 16.14 s 🔴 **+14.94 s** | 0.000 ✅ | 122 ms | 6.06 s | 4.07 MB |
| `/case-study/` | 100 ✅ **+4** | 1.36 s 🔴 **+0.16 s** | 0.051 🔴 | 0 ms | 1.49 s | 1.02 MB |
| `/impact-report-2026/` | 63 🔴 **−37** | 6.25 s 🔴 **+4.90 s** | 0.022 🔴 | 0 ms | 5.72 s | 1.10 MB |

**Nothing passes all three targets. Zero of seven pages are green.**

- **Performance ≥ 96:** 2 of 7 pass (`/`, `/case-study/`).
- **LCP < 1.2 s:** **0 of 7 pass.** The closest is `/case-study/` at 1.36 s.
- **CLS 0.00:** 3 of 7 pass (`/hire-va-4/`, `/blog/`, the single post). The worst is `/case-study/`
  at 0.051 — and note it is *unstable*: 0.052 / 0.051 / 0.002 across the three runs, meaning it is a
  race, not a fixed reservation bug.

### Simulated vs observed — read this before panicking about LCP

Lighthouse's default mobile preset reports **simulated** metrics (what the trace would look like on
Slow 4G), not what actually happened. The observed, unthrottled numbers from the same traces:

| Page | Observed FCP | Simulated FCP | Observed LCP | Simulated LCP |
| :--- | ---: | ---: | ---: | ---: |
| `/` | 1207 ms | 1553 ms | 1207 ms | 1553 ms |
| `/hire-va-4/` | 2081 ms | 6349 ms | 2081 ms | 8608 ms |
| `/about-us/` | 690 ms | 6026 ms | 690 ms | 6326 ms |
| `/blog/` | 807 ms | 1382 ms | 827 ms | 3032 ms |
| `/blog/outsourcing-customer-service/` | 1455 ms | 5736 ms | 1479 ms | 16135 ms |
| `/case-study/` | 624 ms | 1360 ms | 624 ms | 1360 ms |
| `/impact-report-2026/` | 1514 ms | 5718 ms | 1514 ms | 6993 ms |

The gap between the two columns is **entirely bandwidth**. On a fast local link the pages paint in
0.6–2.1 s; the simulated mobile column is what a real 1.6 Mbps phone would see. **Both are real
signals**: the scored column is the one PageSpeed Insights will report, and it says the pages are
too heavy for mobile. A CDN narrows the gap but does not close it, because the dominant cost is
bytes, not latency — see the next section.

## Server response time

Lighthouse `server-response-time` is measured under throttling. These are clean warm `curl` TTFBs
(10 requests per page, no throttling, no cache warming between pages):

| Page | TTFB p50 | TTFB p95 | Lighthouse `server-response-time` |
| :--- | ---: | ---: | ---: |
| `/` | 328 ms | 462 ms | 757 ms |
| `/hire-va-4/` | 265 ms | 270 ms | 574 ms |
| `/about-us/` | 242 ms | 321 ms | 483 ms |
| `/blog/` | 300 ms | 324 ms | 712 ms |
| `/blog/outsourcing-customer-service/` | 251 ms | 283 ms | 537 ms |
| `/case-study/` | 230 ms | 311 ms | 418 ms |
| `/impact-report-2026/` | 256 ms | 319 ms | 501 ms |

Lighthouse flagged `server-response-time` as a top opportunity on **every single page**
(290–802 ms of claimed savings). At 230–330 ms p50 with no page cache and no persistent object
cache, that is unremarkable for WordPress and is the item most likely to improve on its own once
production caching exists. **It is not the reason any page is failing.**

## The dominant cost: one 844 KB font, on every page

> ✅ **Fixed on 2026-09-15.** Everything in this section is the *pre-fix* measurement and is
> kept as the record of what was wrong. The font is now two `unicode-range`-subsetted woff2
> files cut from that same TTF; a page transfers **69 KB instead of 844 KB**. See
> [Part 3](#part-3--fixes-applied-2026-09-15) for the after numbers and the proof that the
> rendering did not move.

| Page | Requests | `InterVariable.ttf` | All fonts | Images | JS | CSS |
| :--- | ---: | ---: | ---: | ---: | ---: | ---: |
| `/` | 45 | **844 KB** | 891 KB | 221 KB | 21 KB | 39 KB |
| `/hire-va-4/` | 39 | **844 KB** | 891 KB | 178 KB | 218 KB | 43 KB |
| `/about-us/` | 37 | **844 KB** | 942 KB | 410 KB | 21 KB | 39 KB |
| `/blog/` | 21 | **844 KB** | 891 KB | 2 612 KB | 21 KB | 39 KB |
| `/blog/outsourcing-customer-service/` | 25 | **844 KB** | 891 KB | 3 060 KB | 148 KB | 39 KB |
| `/case-study/` | 21 | **844 KB** | 891 KB | 75 KB | 21 KB | 39 KB |
| `/impact-report-2026/` | 20 | **844 KB** | 891 KB | 154 KB | 21 KB | 39 KB |

`resources/css/app.css` declares `--font-display` as `"Inter Display"` first, backed by an
**unsubsetted, uncompressed `InterVariable.ttf`**:

```css
@font-face {
  font-family: "Inter Display";
  font-display: swap;
  font-weight: 100 900;
  src: url("../fonts/InterVariable.ttf") format("truetype");   /* 844 KB transferred */
}
```

Three observations, all diagnosis, no fix implied:

1. It is transferred on **all seven pages**, and it is the **single largest asset on five of them**.
   On `/case-study/` it is **82 % of the entire page weight** — and that page still scores 100,
   which tells you how much headroom the rest of the page has.
2. The very next `@font-face` block loads the *same* typeface as three `unicode-range`-subsetted
   **woff2** files totalling ~150 KB, which is what a modern build normally ships. Both sets load.
3. `font-display: swap` is set, so it does not block first paint — which is why the observed FCPs
   are fine. It costs bandwidth, which is exactly what the simulated mobile score punishes.

Under simulated Slow 4G, 844 KB is roughly **4.2 s of transfer on its own**. That single line
accounts for most of the distance between the observed and simulated columns above.

## Per-page diagnosis

### `/` — Perf 99, LCP 1.69 s, CLS 0.011

Closest page to green. LCP element is a **text node** (`<p class="has-text-align-center
has-large-font-size …">`), not an image, so LCP is gated on CSS + font, not on media. No
render-blocking resources. Only real defect: **Best Practices 96 for `font-size`** — Lighthouse
reports 21.9 % of text coverage is below 12 px on a mobile viewport, sourced from `app-*.css`. That
is a genuine mobile-legibility finding, not a local artefact. Highest single opportunity:
`server-response-time` 802 ms.

### `/hire-va-4/` — Perf 58, LCP 8.29 s, CLS 0.000 — **joint furthest from target**

The heaviest *code* page: **218 KB of JS**, of which `livewire.js` is 127 KB and
`intlTelInputWithUtils` is 70 KB. LCP element is the `<h1>` — again text, again gated on transfer.
Top opportunities: `unused-javascript` 600 ms, `uses-rel-preconnect` 442 ms
(**`https://track.customer.io` is contacted from the page and is not preconnected**),
`unminified-javascript` 300 ms. Its CLS is a perfect 0.000, which is the good news. Run variance
here was the worst in the set (58 / 61 / 26) — the 26 is a single bad run, not a second mode, but
it means **this page's score should be re-measured on staging before anyone acts on it**.

### `/blog/outsourcing-customer-service/` — Perf 60, LCP 16.14 s — **worst LCP by 2×**

**4.07 MB, of which 3.06 MB is images.** Six single images over 450 KB — `Diana-R.png` 651 KB,
`Patricia-G.png` 583 KB, `Miguel-A.png` 548 KB, the featured image 513 KB, `Carlos-M-1.png` 457 KB
— all **PNG**, all full-size. `modern-image-formats` alone claims 2 400 ms and
`uses-responsive-images` a further 1 800 ms. The LCP element is the featured image
(`img.rl-header-featured-image`), loaded at full resolution. Two further real defects:

- **Mixed content.** `http://remoteleverage-v2.test/app/uploads/2026/09/RL_avatar_02_250x250.jpg`
  is requested over plain HTTP and auto-upgraded by the browser. This is a **stored `http://` URL in
  content, not a local-environment artefact** — it will behave the same on production and it is why
  Best Practices is 79.
- `td-has-header` accessibility failure — a data table without headers.

### `/blog/` — Perf 93, LCP 3.19 s, CLS 0.000

Same disease, milder: **2.61 MB of images across five listing thumbnails**, every one a
`768x512` **PNG** between 321 KB and 391 KB. `modern-image-formats` claims 1 550 ms. LCP element is
the first listing thumbnail. Only 3 points off the performance target and it is a single
image-format decision away from green. The `blog.css` import and the sub-nav filter list produce
one `color-contrast` failure and a `heading-order` failure.

### `/about-us/` — Perf 64, LCP 6.10 s, CLS 0.008

410 KB of images, dominated by one **322 KB `magnific_EqBRpqJuuO-1-1.png`**. LCP element is
`globo-1-2.webp` with `fetchpriority="high"` already set — so the priority hint is right and the
problem is upstream of it: the page also starts fetching
`public/videos/5-minute-VSL_Horizontal_V01.mp4` during initial load, competing for the same
bandwidth. Four `color-contrast` failures on `div.pb-3 > span` elements and a `heading-order`
failure hold accessibility at 94.

### `/impact-report-2026/` — Perf 63, LCP 6.25 s, CLS 0.022

The lightest failing page — only **1.10 MB and 20 requests**, of which 891 KB is the font. Strip
the font and this page is ~235 KB. LCP element is a text paragraph. Top opportunities are
`server-response-time` 504 ms and `uses-rel-preconnect` 317 ms (`track.customer.io` again).
**Lowest accessibility score in the set at 89**: `aria-prohibited-attr`, `color-contrast` and
`heading-order` all fail.

### `/case-study/` — Perf 100, LCP 1.36 s, CLS 0.051 — **fastest, least stable**

Only 75 KB of images and a perfect 100 across three runs. Two things stop it being the reference
page:

- **CLS 0.051, and unstable across runs (0.052 / 0.051 / 0.002).** An intermittent shift, most
  likely the partner-logo row settling — a race, not a missing fixed dimension.
- **Mixed content again, four assets this time** — `chick-fil-a-text-black-logo.png`,
  `bench-2.png`, `emporia-text-black-logo.png`, `jar-text-black-logo-1.png` all stored with
  `http://` URLs. This is the Best Practices 79.
- `link-text`: nine identical "Read More" links.

## Cross-cutting findings

| Finding | Pages affected | Local artefact? |
| :--- | :--- | :--- |
| 844 KB unsubsetted `InterVariable.ttf` loaded alongside the woff2 subsets | **all 7** | ❌ Real |
| Full-size PNGs used for content and listing imagery | `/blog/`, single post, `/about-us/` | ❌ Real |
| `http://` asset URLs stored in content → mixed content | single post, `/case-study/` | ❌ Real |
| `track.customer.io` / `assets.customer.io` contacted with no `preconnect` | `/hire-va-4/`, `/impact-report-2026/`, single post | ❌ Real |
| `color-contrast` failures | 5 of 7 | ❌ Real |
| `heading-order` failures | 4 of 7 | ❌ Real |
| Text below 12 px on mobile | `/` | ❌ Real |
| `server-response-time` flagged as top opportunity | **all 7** | ⚠️ Partly — no page/object cache locally |
| `is-crawlable` failure → SEO capped at ~66 | **all 7** | ✅ Local only (`DISALLOW_INDEXING`) |
| `valid-source-maps` on `livewire.js` | `/hire-va-4/`, single post | ✅ Local only (dev build of vendor JS) |

---

# Part 2 — `CaptureLeadAction` latency and the queue-worker decision rule

## First: the code no longer matches the issue as written

[`known-issues.md`](known-issues.md) states that *"no listener implements `ShouldQueue` … a single
lead submission makes outbound HTTP calls to HubSpot, Slack, PostHog, Customer.io and a webhook
**inside the user's request**"*.

**That is no longer true for those five integrations.** `LeadServiceProvider::boot()` and
`TrackingServiceProvider::boot()` now wrap every one of them in
`dispatch(static fn () => …)->afterResponse()`:

```php
Event::listen(LeadCreated::class, function (LeadCreated $event) {
    dispatch(static fn () => app(HandleLeadEventsForSlack::class)->handleCreated($event))->afterResponse();
});
```

`Roots\Acorn\Application\Concerns\Bootable` sends the response body on WordPress's `shutdown`
action, calls `fastcgi_finish_request()`, and only *then* calls `$kernel->terminate()` — which is
what runs those deferred closures. So the browser has the response before HubSpot, Slack, the
webhook, the email and the Customer.io/PostHog calls begin.

**The one listener still fully synchronous is the booking listener.**
`SchedulingServiceProvider` registers it directly:

```php
Event::listen(LeadCreated::class, [HandleLeadCreatedForBooking::class, 'handle']);
```

and it fires inside `CaptureLeadAction::execute()`, which means it *is* in the user's request — but
only when `preferred_slot` is set, i.e. on the final booking submit, not on the step-1 partial
capture. **This is where the latency now lives, and it is not what the issue was written about.**

`known-issues.md` should be updated. The premise of the proposal has moved.

## Configuration state at measurement time

| Integration | State | Consequence for the measurement |
| :--- | :--- | :--- |
| HubSpot | **Unset** (no token in DB settings or env) | `syncContact()` returns a `simulated_hs_*` id instantly, no network |
| Slack webhook | **Unset** | Listener returns before `wp_remote_post` |
| Lead webhook | **Unset** | Listener returns before `wp_remote_post` |
| Notification emails | **0 recipients** | Listener returns before `wp_mail` |
| PostHog | **Unset** | `capture()` returns `false` immediately |
| **Customer.io** | ✅ **Configured** | **Two real HTTP calls actually happen** |
| **Calendly** | ✅ **Configured, 4-token pool** | **Real HTTP calls actually happen** |
| `queue.default` | `sync` | No worker; `afterResponse()` runs in-process during terminate |

So four of the six integrations named in the issue are no-ops here. **Customer.io and Calendly are
live, and they are what the measured numbers below are actually measuring.**

## (a) Measured — as the system stands today

WP-CLI, in-process, `hrtime()` around the call. Benchmark leads (and their 55 activity-log rows)
were deleted afterwards.

### Pass A — `CaptureLeadAction::execute()`, step-1 partial capture (no booking slot)

This is the path `MultistepBookingWizard::capturePartialLead()` takes. **n = 40.**

| min | p50 | **p95** | p99 | max | mean |
| ---: | ---: | ---: | ---: | ---: | ---: |
| 2.76 ms | 4.25 ms | **6.75 ms** | 62.83 ms | 62.83 ms | 5.83 ms |

**p95 = 6.75 ms against an 800 ms rule — passes by a factor of ~120.** The single 62.83 ms outlier
is the first iteration (cold container / query cache). What this path does: phone validation,
attribution resolution, one `INSERT` into `wp_rl_leads`, one dispatch-stage row into
`wp_rl_lead_activity_logs`, and event dispatch. No network.

### Pass B — the deferred (`afterResponse`) side effects, timed individually

**These do not add to the user's response time.** They are measured to size worker occupancy after
the response is flushed, and to show which ones are currently no-ops. **n = 15 each.**

| Side effect | min | p50 | **p95** | Live? |
| :--- | ---: | ---: | ---: | :--- |
| `HubSpotGateway::syncContact` | 0.11 ms | 0.11 ms | 13.20 ms | ❌ no token — simulated |
| Slack listener | 0.01 ms | 0.01 ms | 0.43 ms | ❌ no webhook |
| Webhook listener | 0.01 ms | 0.01 ms | 0.34 ms | ❌ no webhook |
| Email listener (`wp_mail`) | 0.01 ms | 0.01 ms | 0.24 ms | ❌ 0 recipients |
| **Tracking (Customer.io ×2 + PostHog)** | **523.98 ms** | **557.05 ms** | **685.57 ms** | ✅ **Customer.io live** |
| **Sum of per-listener p95** | | | **699.77 ms** | |

The Tracking listener is the only one doing real work: `CustomerIOClient::identify()` (PUT) and
`CustomerIOClient::track()` (POST) — **two** calls, ~340 ms each. PostHog no-ops. That single
configured integration accounts for **98 % of the total deferred cost**, which is the clearest
possible evidence of what the other five will cost once they are switched on.

### Pass C — the *synchronous* booking path (`preferred_slot` set) — **the real finding**

Only read-only Calendly calls were measured. `createInvitee` was **deliberately not called**,
because it would create a real meeting on the live Calendly account. **n = 12 each.**

| Synchronous call | min | p50 | **p95** | max |
| :--- | ---: | ---: | ---: | ---: |
| `CalendlyClient::findExistingInvitee()` | 3 680.79 ms | **4 056.81 ms** | **6 143.76 ms** | 6 143.76 ms |
| `CalendlyMetadataCache::getEventQuestions()` | 0.18 ms | 0.29 ms | 422.03 ms | 422.03 ms |
| `CalendlyClient::getScheduledEvent()` (404 → full pool failover) | 1 425.04 ms | 1 518.33 ms | 2 040.06 ms | 2 040.06 ms |
| *Calibration:* one plain Calendly `GET` from PHP | 378 ms | 528 ms | 2 222 ms | 2 222 ms |

**`findExistingInvitee()` takes four seconds at p50, inside the user's request.** The cause is in
the code, not the network:

```php
foreach ($this->tokenPool->getEligibleTokens(null) as $item) {   // 4 tokens
    $user = $this->getForToken($token, 'https://api.calendly.com/users/me');        // GET #1
    …
    $response = $this->getForToken($token, '…/scheduled_events', [...]);            // GET #2
    …
}
```

The pool has **4 eligible tokens** (`Primary`, `Pool 2`, `Pool 3`, `Pool 4`). The loop makes **2
sequential GETs per token = 8 sequential round-trips**, and it *never short-circuits* — when the
lead has no prior booking, which is the overwhelmingly common case, it walks all four accounts to
completion. 8 × ~500 ms ≈ 4 s, exactly what was measured. `getEventQuestions` is cached
(0.29 ms warm, 422 ms on the cold first call per event type per TTL).

`getScheduledEvent`'s measured 1.5 s is the **404 failover worst case** — a miss retries through the
whole pool. On the happy path it is one round-trip.

### Pass D — `findExistingInvitee()` after the fix (2026-09-15) — **measured**

The preflight was rewritten (`CalendlyClient::findExistingInvitee`): `users/me` is resolved through
a 12 h per-token identity cache, the pool is used as a failover rather than a fan-out so the first
account that answers ends the loop, the `scheduled_events` query is narrowed to a ±60 s window
around the requested slot, and a 5 s request / 8 s whole-preflight budget replaces the inherited
15 s-per-call default. **One HTTP round-trip on a warm cache instead of eight.**

Measured as an **interleaved A/B in a single WP-CLI process** — a verbatim replica of the old loop
and the new implementation alternating on the same fresh email each iteration, so both arms see
identical network conditions. **n = 20 per arm**, nearest-rank percentiles, `hrtime()`. Read-only;
`createInvitee` was still never called.

| Arm | min | p50 | **p95** | p99 | max | mean |
| :--- | ---: | ---: | ---: | ---: | ---: | ---: |
| **Before** (old loop, replica) | 4 171.51 ms | **4 434.93 ms** | **5 495.00 ms** | 5 553.28 ms | 5 553.28 ms | 4 635.11 ms |
| **After** (current code) | 400.58 ms | **521.20 ms** | **1 159.35 ms** | 1 561.99 ms | 1 561.99 ms | 679.80 ms |
| *Speed-up* | | **8.51×** | **4.74×** | | | 6.82× |

The "after" arm is now indistinguishable from the calibration row above — **one plain Calendly GET
from this machine** (378 ms min / 528 ms p50) — which is the floor for a single round-trip. What is
left is network, not code.

Two further checks, both live and read-only:

- **Cold identity cache** (first submission after a deploy or a 12 h TTL expiry): p50 432.16 ms,
  p95 1 122.30 ms over n = 12, i.e. the extra `users/me` costs one round-trip *once per token per
  12 h*, not once per booking.
- **Correctness against a real booking.** A genuine active scheduled event on the primary pooled
  account was rediscovered by `findExistingInvitee()` in 436 ms, and the negative control (same
  invitee and event type, slot +7 days) correctly returned no match in 889 ms. The narrowed time
  window and the short-circuit do not weaken the duplicate guard.

Regression coverage: `tests/Unit/CalendlyPreflightTest.php` — 429 failover, cached `users/me` not
re-fetched, no-prior-booking short-circuit, 401/403 rotation, 5xx and connection-failure
degradation, and the time-windowed query.

## (b) Every outbound call with all integrations configured

Counted from the code, for one `LeadCreated`:

### In the user's request (synchronous) — final booking submit only

| # | Call | Where | Timeout |
| ---: | :--- | :--- | ---: |
| ~~1–8~~ → **1** | ~~`users/me` + `scheduled_events` × 4 pooled tokens~~ → **one `scheduled_events`** (warm identity cache; +1 `users/me` per token per 12 h) | `CalendlyClient::findExistingInvitee` | ~~15 s each~~ → 5 s/request, 8 s total budget |
| 9 | `event_types/{uuid}` (cached per TTL) | `CalendlyMetadataCache::getEventQuestions` | 15 s |
| 10 | **`POST /scheduled_events/…/invitees`** — creates the booking | `CalendlyClient::createInvitee` | 20 s |
| 11 | `GET /scheduled_events/{uuid}` — resolve the Meet link | `CalendlyClient::getScheduledEvent` | 15 s (×4 on failover) |
| — | *`POST …/cancellation`, only on a genuine reschedule* | `cancelScheduledEvent` | 15 s |
| — | *Google Calendar OAuth + insert, only if the Calendly path returns nothing* | `GoogleCalendarClient` | none set |

~~**Up to 11 sequential third-party round-trips before the user sees a confirmation.**~~
**Now up to 4** (warm) — rows 1–8 collapsed to one after the 2026-09-15 preflight fix; see Pass D.

### After the response is flushed (`dispatch()->afterResponse()`)

| # | Call | Where | Timeout |
| ---: | :--- | :--- | ---: |
| 12 | `POST api.hubapi.com/crm/v3/objects/contacts` | `HubSpotGateway::syncContact` | 10 s |
| 13 | `POST hooks.slack.com/…` | `HandleLeadEventsForSlack` (`wp_remote_post`) | 5 s |
| 14 | `POST <lead webhook url>` | `HandleLeadEventsForWebhook` (`wp_remote_post`) | 5 s |
| 15 | SMTP session | `HandleLeadEventsForEmailNotification` (`wp_mail`) | **none set** |
| 16 | `PUT track.customer.io/api/v1/customers/{id}` | `CustomerIOClient::identify` | **none set** |
| 17 | `POST us.i.posthog.com/capture/` | `PostHogClient::capture` | **none set** |
| 18 | `POST track.customer.io` (track) | `CustomerIOClient::track` | **none set** |
| 19–20 | Slack + webhook **again**, from `LeadBookingCompleted` | same listeners | 5 s each |

**Totals with every integration live:**

| Path | In-request calls | After-response calls | Total |
| :--- | ---: | ---: | ---: |
| Step-1 partial capture | **0** | **7** | 7 |
| Final booking submit | ~~up to 11~~ → **up to 4** | **9** | ~~up to 20~~ → **up to 13** |

Note the four `afterResponse` calls with **no timeout set** (Customer.io ×2, PostHog, `wp_mail`).
They inherit Guzzle / WordPress defaults, so a hung endpoint holds a PHP-FPM worker far longer than
the 5–10 s the explicitly-configured ones allow.

## (c) Estimated realistic p95 — **this is an estimate, not a measurement**

> 🔻 **Everything in this section is arithmetic on stated assumptions.** No HubSpot, Slack, PostHog,
> webhook or SMTP call was ever made, because those credentials are unset. `createInvitee` was never
> called, because it would book a real meeting. **Do not cite these as measurements.**

**Assumptions, stated explicitly:**

1. **Per-call cost ≈ 340–450 ms p95** from this machine. Grounded in two independent measurements:
   the Customer.io pair at 685.57 ms p95 for two calls (≈343 ms each), and direct `curl` probes of
   all five endpoints (`api.hubapi.com`, `hooks.slack.com`, `us.i.posthog.com`, `track.customer.io`,
   `api.calendly.com`) at 230–450 ms end-to-end, **of which ~150–250 ms is TCP + TLS handshake**,
   because each `Http::` call opens a fresh connection.
2. **Production from AWS would be faster** — say 250 ms p50 / 400 ms p95 per call — but not by an
   order of magnitude, since the handshake dominates and it is paid per call either way.
3. Calls are **sequential**. Nothing in the code issues them concurrently.
4. No endpoint is degraded. A single slow integration hitting its timeout adds that timeout to the
   total, additively.

| Scenario | Estimate | Basis |
| :--- | ---: | :--- |
| **Step-1 partial capture, in-request p95** | **~7 ms** | ✅ **Measured** (Pass A) — unaffected by integrations |
| Step-1 partial capture, after-response p95 | ~2.4–3.2 s | 7 ops × ~340–450 ms |
| ~~**Final booking submit, in-request p95 (as coded today)**~~ → **~2–4 s** | ~~**~7–10 s**~~ | ~~preflight 6.1 s p95~~ → measured preflight **1.16 s p95** (Pass D) + createInvitee ~0.4–0.6 s + getScheduledEvent ~0.5–2.0 s |
| ~~Final booking submit, in-request p50 (as coded today)~~ → **~1.5 s** | ~~~5 s~~ | ~~preflight 4.1 s p50~~ → measured preflight **0.52 s p50** (Pass D) + ~1 s for the two writes |
| Final booking submit, in-request p95 (optimistic AWS RTT) | ~~**~4.5–5 s**~~ → **~1.6 s** | ~~11~~ → 4 sequential calls × ~400 ms p95 |
| Final booking submit, after-response p95 | ~3.5–4.5 s | 9 ops × ~400 ms |

## Verdict on the `~800 ms` decision rule

The rule: *"measure the p95 of `CaptureLeadAction` end to end with all integrations live. If it
exceeds ~800 ms, provisioning a queue worker becomes a launch blocker rather than a deferred
nicety."*

**Split the answer, because the two call sites behave completely differently.**

### 1. Step-1 partial capture — ✅ **passes, overwhelmingly**

**Measured p95 = 6.75 ms**, and it will *stay* under 800 ms no matter how many integrations are
switched on, because all seven of its outbound calls are already deferred past the response flush.
For this path the rule is satisfied and a queue worker is **not** a launch blocker.

### 2. Final booking submit — 🔴 ~~**breaches the threshold by roughly 6–8×**~~ → ⚠️ **the breach was a bug, and it is fixed**

`HandleLeadCreatedForBooking` runs synchronously inside `CaptureLeadAction::execute()`. The
duplicate-booking preflight **alone** was a **measured 4 057 ms p50 / 6 144 ms p95** — before the
booking is created and before the Meet link is resolved. Against an 800 ms threshold that was not
marginal.

**Since fixed (2026-09-15).** The preflight now measures **521 ms p50 / 1 159 ms p95** — see
Pass D. This path is still synchronous and still makes up to four sequential third-party calls, so
it will not sit under 800 ms end to end while `createInvitee` and the Meet-link lookup remain in the
request; but the 6–8× breach was one avoidable bug, not a structural property of the design, and
removing it did not need a queue worker.

### 3. …but a queue worker is the wrong remedy for the path that fails

This is the part the original proposal could not have anticipated, because it was written when all
five notification integrations were still in-request. **The user is waiting on the booking result** —
`submitBooking()` reads `$lead->refresh()`, the meeting id and the Meet URL straight out of the
activity log to render the confirmation screen. Move that listener to a queue and the user gets a
spinner and no confirmation, not a fast response. The latency has to be removed, not relocated.

Where it came out (diagnosed here on 2026-09-15, **and acted on the same day** — measurements in
Pass D):

- ✅ `findExistingInvitee` issued `users/me` **on every submission for every one of the 4 tokens**.
  That response is effectively static per token and was not cached. It is now cached for 12 h,
  removing 4 of the 8 preflight round-trips.
- ✅ The loop walked all four accounts even after the query returned cleanly. Most leads have no
  prior booking, so the common case paid the maximum cost. It now stops at the first account that
  answers, which removes the remaining 3.
- ✅ Each of the 4 pooled tokens is a separate Calendly account, so the pool exists for rate-limit
  failover on *writes*; the read preflight does not need to query all four. It now uses the pool as
  a failover — rotating only on 429/401/403/404 — which is also the account `createInvitee` would
  book through.
- ✅ Every preflight call inherited a 15 s timeout with no overall ceiling, so a degraded Calendly
  could hold the booking submit open for a minute. Now 5 s per request under an 8 s whole-preflight
  budget, failing open.

### 4. What the queue worker *is* still worth

Not latency — **throughput and reliability**:

- `afterResponse` work still occupies a PHP-FPM worker for an estimated **2.4–4.5 s per submission**
  after the response is sent. Under concurrent booking-funnel traffic that is a worker-pool
  exhaustion risk, which is a different failure mode from slow responses and one that staging
  traffic will not surface either.
- `afterResponse` closures have **no retry and no dead-letter**. A HubSpot 500 or a Slack timeout is
  logged and lost. A real queue gives retries and a failed-jobs table.
- Four of the deferred calls have **no timeout configured at all**.

**Recommendation for the cutover gate — acted on 2026-09-15:** the rule was recorded as breached on
the booking-submit path **with the remedy reassigned**. The Calendly preflight was fixed (Pass D),
WR-106 was reclassified in [`known-issues.md`](known-issues.md) and
[`adr-status.md`](adr-status.md) as a throughput/reliability item rather than a latency blocker, and
**provisioning the queue worker stays deliberately deferred**.

---

## What could not be measured here, and why

| Not measured | Why |
| :--- | :--- |
| Real p95 with **HubSpot, Slack, PostHog, the lead webhook and SMTP** live | No credentials configured in this environment. An unconfigured gateway returns before it opens a socket, so measuring it produces a number that means nothing. Section (c) estimates it and says so. |
| `CalendlyClient::createInvitee` | Calling it books a real meeting on the live Calendly account. Unchanged for Pass D: the preflight fix was measured read-only, so the *end-to-end* booking-submit latency remains an estimate. |
| The **429 rate-limit failover** against the live API | It cannot be provoked without deliberately exhausting a production Calendly account's quota. It is covered by `tests/Unit/CalendlyPreflightTest.php` against a faked transport instead. |
| The **Google Calendar fallback path** | Only runs when the Calendly path returns nothing; `GOOGLE_CALENDAR_REFRESH_TOKEN` is empty. |
| End-to-end **HTTP** latency of the Livewire submit | `CaptureLeadAction` was timed in-process via WP-CLI. Livewire request overhead (snapshot hydration, component render, response) is **not** included in the Pass A number. |
| That `afterResponse` really does flush first **over HTTP** | Verified by reading `Bootable.php` (`fastcgi_finish_request()` before `$kernel->terminate()`), not by timing a live request. It is a code-level verification, not a measurement. |
| Anything on **staging or production** | The gate in `plan.md` asks for PageSpeed Insights on staging. No staging performance run was possible from here. |
| Performance with **production caching, CDN and OPcache settings** | None of that exists locally. |

## Reproducing this

```bash
cd web/app/themes/remote-leverage
npm run build                       # must run first — do not measure a dev bundle

npx lighthouse@12.8.2 https://remoteleverage-v2.test/ \
  --output=json --output-path=./lh-home.json \
  --only-categories=performance,accessibility,best-practices,seo \
  --chrome-flags="--headless=new --ignore-certificate-errors"
```

Run each URL 3× and take the median; discard any report whose `runtimeError.code` is not
`NO_ERROR`. The Part 2 numbers come from `hrtime()` timings around
`app(CaptureLeadAction::class)->execute()` and the individual listener/gateway calls, run through
`wp eval-file`, with the benchmark leads and their activity-log rows deleted afterwards.

---

# Part 3 — Fixes applied (2026-09-15)

Two front-end changes were made *after* the measurement pass above, and re-measured with the same
method (`lighthouse@12.8.2`, default mobile preset, 3 runs, median). **Because run-to-run variance
on this machine is high, the before column here is a fresh re-measurement taken minutes before the
change, not the Part 1 table** — Part 1 and this re-measurement disagree by up to 3 points on the
same unchanged build, which is itself the best available illustration of the variance warning at
the top of this document.

## Fix 1 — `Inter Display` is now a subsetted woff2

### What was actually wrong

`InterVariable.ttf` was not a distinct "display" typeface. Its own `name` table reports the family
as **"Inter Variable", Inter 4.000**, with axes `opsz 14–32` and `wght 100–900`. The CSS aliased it
to the family name `"Inter Display"`, and the comment beside it claimed optical sizing was "turned
off on headings below".

**That claim was false.** `font-optical-sizing`, `opsz` and `font-variation-settings` appear nowhere
in `resources/`, `app/`, `patterns/` or `theme.json`, so the CSS default (`font-optical-sizing: auto`)
was live: every element on `--font-display` was rendering at `opsz` = its own px size, clamped to
[14, 32]. The `@fontsource-variable/inter` files backing `--font-sans` are axis-subsetted to `wght`
only, which pins `opsz` at 14.

So the obvious cheap fix — delete the family and point its consumers at `Inter Variable` — **was
rejected**: it would have restyled every heading on the site (the type scale runs to 201 px, all of
which currently render at `opsz 32`). The 844 KB was a packaging problem, not a typeface problem.

### What was done

`pyftsubset` was run over that exact TTF, once per unicode range, preserving both axes:

| | Before | After |
| :--- | ---: | ---: |
| `InterVariable.ttf` (TrueType, unsubsetted) | 862 936 B on disk / **864 020 B transferred** | — |
| `inter-display-latin.woff2` | — | 70 380 B / **70 581 B transferred** |
| `inter-display-latin-ext.woff2` | — | 125 212 B, **not requested by any page measured** |
| Per-page font transfer | **891 KB** | **116 KB** |

The latin range is the Inter Variable range already used for body copy, plus `U+2190-2199`,
`U+2264-2265`, `U+2713` and `U+2717` — a `→` in `.rl-also-read-link` on blog articles was verified
to render *in this face*, so dropping it would have been a visible regression.

### Proof the rendering did not move

Pixel diffing alone could not settle this, so the fonts were compared directly and then in the DOM.

1. **Font-level.** Instancing both the source TTF and the subsets at six `(opsz, wght)` locations
   spanning the whole design space — `(14,400) (14,100) (17,500) (24,600) (32,700) (32,900)` — and
   comparing every one of the 1 010 shared glyphs with a decomposing pen:
   **0 advance-width differences and 0 outline differences at every location.** `upem`, `hhea`
   ascender/descender/lineGap and all four OS/2 vertical metrics are identical; `avar`, `HVAR`,
   `MVAR`, `STAT` and `GDEF` all survive; `kern`, `mark`, `mkmk`, `calt`, `ccmp` and `locl` all
   survive. Only opt-in features (`ss01-08`, `cv01-13`, `tnum`, `smcp`, `case`, `salt`, `dlig` …)
   were dropped, and nothing in the theme requests any of them — there is no `font-feature-settings`
   anywhere, and the only `font-variant-numeric` declarations sit on `--font-sans` elements.

   > Using `@fontsource-variable/inter`'s ready-made `*-opsz-*.woff2` files instead would have been
   > less work but is **not** equivalent: those are Inter **4.001**, and the same comparison finds
   > 7 differing outlines and 5 differing advance widths against 4.000 — including the digit `5`,
   > which moves by 30/2048 em (≈ 2.9 px at the 201 px numeral size). Hence subsetting the repo's
   > own TTF rather than swapping in the packaged files.

2. **DOM-level.** A harness rendered 9 text samples (body copy, the hero string, the uppercase
   `CONSULTATION` pill, prices, accented latin-ext, and the arrow/tick/currency run) at 11 sizes
   from 12 px to 201 px × 7 weights under both faces — **693 text runs** — and compared width,
   height and the per-character `Range` rect of every character. **692 of 693 are identical to four
   decimal places.** The one exception is `₱` at 201 px, whose width differs by **0.0156 px** (one
   1/64 px subpixel quantum); the run realigns on the next character. `₱` does not appear on the
   site.

3. **Page-level.** Full-page 1440 px screenshots of `/`, `/hire-va-4/`, `/case-study/` and
   `/case-study/bench-accounting/`, images forced eager and the page scrolled to the end first.
   **Every page height is unchanged to the pixel** and no line break moves. What remains is
   sub-glyph antialiasing: 0.03–0.22 % of pixels differ on the three deterministic pages, mean
   delta 26–32 of 255, confined to glyph edges. The noise floor was established by capturing the
   same build twice: `/`, `/hire-va-4/` and both case-study pages are **byte-identical between
   runs**, except one band on `/` (x 852–1407, y 5733–6222) which is a rotating component and
   accounts for roughly half of that page's raw diff.

### Result

| Page | Perf | LCP (s) | CLS | TBT (ms) | Speed Index (s) | Total weight |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `/` | 97 → **99** | 1.46 → 1.50 | 0.004 → 0.011 | 107 → 76 | 2.47 → 2.32 | 1 206 KB → **432 KB** |
| `/case-study/` | 99 → **99** | 1.38 → 1.83 | 0.052 → 0.002 | 0 → 0 | 1.56 → 1.65 | 1 043 KB → **268 KB** |
| `/hire-va-4/` | 61 → **94** | 6.93 → **2.43** | 0.000 → 0.000 | 137 → 168 | 5.73 → 2.42 | 1 362 KB → **587 KB** |

Every page lost **exactly 775 KB**, which is the whole of the font delta and confirms nothing else
moved between the two runs. Per-run detail, because the medians hide how noisy this machine is:

| Page | LCP before (3 runs, ms) | LCP after (3 runs, ms) |
| :--- | :--- | :--- |
| `/` | 1407 / 5879 / 1463 | 1499 / 1774 / 1315 |
| `/case-study/` | 1375 / 1379 / 1463 | 1376 / 1920 / 1828 |
| `/hire-va-4/` | 6934 / 6181 / 8229 | **2744 / 2328 / 2434** |

`/hire-va-4/` is the unambiguous win: its *worst* post-fix run is 3.4 s faster than its *best*
pre-fix run, and the LCP element is the same `<h1>` text node throughout. `/case-study/`'s median
LCP reading got worse while its fastest run did not (1375 → 1376 ms) — that is variance, not a
regression, and its CLS improved from 0.052 to 0.002. Do not read the `/` and `/case-study/` LCP
columns as a result in either direction.

**Still true after the fix:** `--font-sans` and `--font-display` remain two separate downloads
(48 KB + 70 KB) because they are genuinely two different axis configurations of the same outline.
Collapsing them would save another 48 KB and change how body copy renders.

## Fix 2 — the site-wide 9 px horizontal overflow at 400 px

### What was actually wrong

`document.documentElement.scrollWidth` was **409** in a 400 px viewport on every page. The reported
culprit — the mobile header's `.flex.lg:hidden.items-center.gap-3` reaching `x = 408.8` — was the
symptom. The cause is arithmetic:

| Box | Width |
| :--- | ---: |
| available inside the `px-4` gutters | **368.0** |
| logo `<img>` | 205.3 |
| `CONSULTATION` pill | 135.4 |
| `gap-3` between pill and hamburger | 12.0 |
| hamburger button | 40.0 |
| **content total** | **392.7** |

392.7 into 368 does not go, and **nothing in the row was allowed to give**: the logo column was
`shrink-0`, and the pill and the mobile group carry the flexbox default `min-width: auto`, which
forbids shrinking below content size. The row overflowed its container, and `header` has no
clipping ancestor, so it reached the document.

The logo is 205.3 px rather than the 144 px its own markup implies because
`resources/images/logo.svg` is **154 × 18** (ratio 8.556:1) while the `<img>` declared
`width="168" height="28"` (6:1). `h-6 w-auto` sizes from the *intrinsic* ratio, so the attributes
only ever controlled the box reserved *before* the SVG loads — i.e. they were also a latent CLS
source, and `/` does carry a small non-zero CLS. `footer.blade.php` declared `180 × 40` (4.5:1)
against the same asset, and `header-cta.blade.php` declared nothing at all.

For reference, production's mobile header at 400 px has a **centred 200 px logo and no
Consultation pill** — the inline mobile CTA is something v2 added, and it is what makes the row
not fit.

### What was done

No `overflow-x: hidden` anywhere. The declared intrinsic size was made truthful and the row was
made able to respond:

- all three `logo.svg` `<img>` tags now declare `width="154" height="18"`, matching the asset;
- the header logo column is `min-w-0` instead of `shrink-0`, its `<a>` is `min-w-0`, and the
  `<img>` gains `max-w-full object-contain object-left` — so below ~425 px the logo is the box
  that yields, scaled down inside its own box with its aspect ratio intact rather than squashed
  or clipped;
- the mobile pill + hamburger group is `shrink-0`, so the tap targets never shrink;
- the header row gains `gap-3`, so the logo and the pill cannot touch at the widths where the
  logo has shrunk. Above ~425 px `justify-between` already separates them and the gap is inert.

The same treatment was applied to `header-cta.blade.php` (its `Get Started` pill is now `shrink-0`)
and the footer logo.

### Verification

Playwright, `channel: 'chrome'`, mobile emulation, full-page scroll before measuring, checking
`document.documentElement.scrollWidth` and every element whose right edge clears the viewport
without a clipping ancestor:

| Viewport | `/` | `/samples/` | `/case-study/` | `/case-study/bench-accounting/` | `/hire-va-4/` | `/blog/` | `/blog/outsourcing-customer-service/` | `/about-us/` | `/vapricing` | `/impact-report-2026/` | `/vacalendar` |
| ---: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| 320 px | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 360 px | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 375 px | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 400 px | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

`scrollWidth == clientWidth` in all 44 cases, and the offender list is empty. The drawer was opened
at 320 px and 400 px: `aria-expanded` flips, all six links render, the drawer's right edge lands on
the viewport edge, and `scrollWidth` stays put with the menu open. The logo is scaled, never
clipped — 168.6 px wide at 400 px, 88.6 px at 320 px, aspect ratio preserved by `object-contain`.

At 1440 px the header change is inert: the footer band is **byte-identical**, and the header band
differs by at most 16/255 on the logo's antialiased edges with zero pixels above that threshold.

## Final state

Measured on the tree as it stands after both fixes:

| Page | Perf | LCP | CLS | Total weight |
| :--- | ---: | ---: | ---: | ---: |
| `/` | 98 | 1.43 s | 0.011 | 432 KB |
| `/case-study/` | 99 | 1.47 s | 0.002 | 268 KB |
| `/hire-va-4/` | 92 | 2.18 s | 0.000 | 589 KB |

Two of the three now clear the **Perf ≥ 96** gate and the third went from 58–61 to 92–94.
**LCP < 1.2 s still passes on nothing** — the remaining gap is no longer the font.

> ⚠️ This last table was taken after other work landed in the same tree (`patterns/hire-va.php`
> and `patterns/hire-va-4-testimonials.php` both changed between the Fix 1 and Fix 2 measurements,
> which is also why `/hire-va-4/`'s full-page screenshot grew 26 px taller and its TBT moved).
> The Fix 1 before/after table above is the clean isolation of the font change; this one is a
> snapshot of the tree.

## What was found and deliberately not fixed

- **`--font-sans` and `--font-display` are two 48 KB + 70 KB downloads of the same outline.**
  Merging them is a type-design decision, not a perf one.
- **Neither font is preloaded**, and `font-display: swap` means first paint uses a fallback and
  reflows. With the font now at 70 KB a `<link rel="preload">` is cheap and would remove that swap;
  it was out of scope here.
- **`/blog/` and `/blog/outsourcing-customer-service/` are now overwhelmingly image-bound**
  (2.6 MB and 3.1 MB of full-size PNGs). With the font gone, these are the largest remaining wins
  in the whole document.
- **The logo aspect-ratio attributes were wrong in three templates.** They are corrected, but the
  same class of bug — a declared `width`/`height` that does not match the asset — was not audited
  across the rest of the theme's `<img>` tags.
- **Below ~350 px the header logo gets small** (88.6 px at 320 px). It no longer overflows and it
  is not distorted, but if the mobile `CONSULTATION` pill were dropped — as production has it —
  the logo could stay full size at every width. That is a marketing call, not a layout one.
