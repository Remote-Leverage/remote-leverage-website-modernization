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

- ~~**`--font-sans` and `--font-display` are two 48 KB + 70 KB downloads of the same outline.**
  Merging them is a type-design decision, not a perf one.~~ → **Investigated and rejected**, see
  [Part 4, Fix 4](#fix-4--merging---font-sans-into---font-display-measured-and-rejected).
- ~~**Neither font is preloaded**, and `font-display: swap` means first paint uses a fallback and
  reflows.~~ → **Fixed**, see [Part 4, Fix 3](#fix-3--the-two-above-the-fold-faces-are-preloaded).
- ~~**`/blog/` and `/blog/outsourcing-customer-service/` are now overwhelmingly image-bound**
  (2.6 MB and 3.1 MB of full-size PNGs). With the font gone, these are the largest remaining wins
  in the whole document.~~ → **Fixed**, see [Part 5](#part-5--blog-image-weight-2026-09-15):
  image weight down 89–91 % and all three blog pages now score 99.
- **The logo aspect-ratio attributes were wrong in three templates.** They are corrected, but the
  same class of bug — a declared `width`/`height` that does not match the asset — was not audited
  across the rest of the theme's `<img>` tags.
- **Below ~350 px the header logo gets small** (88.6 px at 320 px). It no longer overflows and it
  is not distorted, but if the mobile `CONSULTATION` pill were dropped — as production has it —
  the logo could stay full size at every width. That is a marketing call, not a layout one.

---

# Part 4 — Font loading, second pass (2026-09-15)

Closes the two font items Part 3 left open. One shipped, one was measured and **rejected**.

> ⚠️ **The Lighthouse numbers in this part are the weakest evidence here, and deliberately not
> the basis for either decision.** This machine was running a video call and several concurrent
> agents while the work was done; a first baseline pass recorded TBT of **1 171 ms** on `/` and
> **3 150 ms** on `/hire-va-4/` at load average 25, against 0–254 ms for the same build at load
> average 8 twenty minutes later. That is a 5–14× swing on an unchanged tree, far beyond the
> variance warning at the top of this document, and it is larger than the whole effect being
> measured. The first baseline was therefore **discarded** and re-taken under conditions matching
> the after run. The conclusions below rest on the two low-variance measurements instead —
> resource timing and layout-shift attribution — both of which are composition facts that carry
> to production.

## Fix 3 — the two above-the-fold faces are preloaded

### What was wrong

Nothing preloaded the fonts, and `font-display: swap` is set. A font face is only discovered
once `app.css` has been fetched *and* parsed, so on every cold visit the browser painted text in
the system fallback and then re-painted and reflowed it when the real face arrived.

Measured, 9 cold trials per state (fresh context, cache disabled, 1.6 Mbps / 150 ms RTT,
4× CPU throttle, 412 × 915), medians:

| | `/` before | `/` after | `/hire-va-4/` before | `/hire-va-4/` after |
| :--- | ---: | ---: | ---: | ---: |
| `app.css` request start | 963 ms | 1 007 ms | 919 ms | 775 ms |
| `app.css` `responseEnd` | 1 423 ms | 1 883 ms | 1 561 ms | 1 823 ms |
| display face request start | 1 603 ms | **1 005 ms** | 1 685 ms | **772 ms** |
| display face `responseEnd` | 3 017 ms | **2 048 ms** | 2 804 ms | **2 144 ms** |
| sans face `responseEnd` | 2 783 ms | **1 941 ms** | 2 592 ms | **1 909 ms** |
| First Contentful Paint | 2 128 ms | 2 592 ms | 2 196 ms | 2 380 ms |
| **face arrives vs FCP** | **+889 ms** | **−544 ms** | **+608 ms** | **−236 ms** |

The last row is the whole point. Before, the real face landed **0.6–0.9 s after** the first
paint — that is the swap, and it is guaranteed on every cold visit. After, both faces are in
place **before** the first paint, so the first paint is already the final one. Font discovery
moves **598 ms** earlier on `/` and **913 ms** earlier on `/hire-va-4/`, from "after the
stylesheet finished" to the same millisecond the stylesheet is requested — i.e. into the
preload scanner.

Note that FCP itself gets *later* (`/`: 2 128 → 2 592 ms). That is real and expected: 118 KB of
font now competes with the stylesheet on a 1.6 Mbps link. The trade is a slightly later first
paint in exchange for that paint being correct, instead of an earlier paint in the wrong
typeface followed by a reflow.

### What was done

Two `<link rel="preload" as="font" type="font/woff2" crossorigin>` in
`resources/views/layouts/app.blade.php`, immediately before the `@vite` call:

| Face | Size | Preloaded | Why |
| :--- | ---: | :-: | :--- |
| `inter-display-latin.woff2` | 70 KB | ✅ | `--font-display`; requested by every page measured |
| `inter-latin-wght-normal.woff2` | 48 KB | ✅ | `--font-sans`; requested by every page measured |
| `inter-display-latin-ext.woff2` | 125 KB | ❌ | U+0100+; **requested by no page measured** |
| `inter-latin-ext-wght-normal.woff2` | — | ❌ | as above |
| `inter-latin-wght-italic.woff2` | — | ❌ | requested on `/about-us/` only, and nothing above the fold there is italic |

Which faces each page actually pulls was measured, not assumed — `/`, `/hire-va-4/`,
`/case-study/`, `/blog/`, `/about-us/` and `/blog/outsourcing-customer-service/` were loaded and
their `woff2` responses recorded. All six request **exactly the two preloaded faces** and no
others. Preloading all five would put ~125 KB of never-parsed bytes on the critical path of
every visit, which is a larger regression than the swap being removed.

The hrefs go through `Vite::asset('resources/fonts/…')`. These filenames are content-hashed
build output (`inter-display-latin-B9ONkGYY.woff2` today); a hardcoded hash would 404 after any
font rebuild while still looking correct in the markup.

`crossorigin` is required even though the fonts are same-origin — `@font-face` always fetches in
anonymous CORS mode, and a preload whose mode does not match is simply fetched a second time.
Verified across all five pages: **each face is fetched exactly once** and Chrome logs no
"preloaded but not used" warning.

### Result — layout stability

7 cold trials per page per state, same throttling. This is the environment-independent number.
"Post-arrival" is the shift that lands in the 450 ms window around the last font's `responseEnd`
— i.e. the portion attributable to the swap.

| Page | CLS before | CLS after | post-arrival before | post-arrival after |
| :--- | ---: | ---: | ---: | ---: |
| `/` | 0.0089 | **0.0030** | 0.0056 | **0.0000** |
| `/hire-va-4/` | 0.0000 | 0.0000 | 0.0000 | 0.0000 |
| `/case-study/` | 0.0502 | **0.0013** | 0.0488 | 0.0013 |
| `/blog/` | 0.0000 | 0.0000 | 0.0000 | 0.0000 |

**`/case-study/`'s CLS was almost entirely the font swap** — 0.0488 of 0.0502 — and it is now
0.0013. On `/`, the font-attributable shift goes to exactly zero. The two pages that were
already 0.0000 are unaffected. Two of the seven `/case-study/` after-runs recorded 0.0257/0.0260
rather than 0.0013; the median is reported.

### Result — Lighthouse (mobile, 3 runs, median)

Both columns taken at load average ≈ 8, twenty minutes apart, on the same build. Read the CLS
and FCP columns; treat LCP as inconclusive.

| Page | Metric | Before | After |
| :--- | :--- | ---: | ---: |
| `/` | Performance | 97 | 99 |
| | FCP | 1 982 ms | **1 250 ms** |
| | LCP | 2 057 ms | 2 067 ms |
| | CLS | 0.0037 | **0.0000** |
| | Speed Index | 2 282 ms | 2 259 ms |
| `/hire-va-4/` | Performance | 94 | 95 |
| | FCP | 1 299 ms | 1 246 ms |
| | LCP | 1 732 ms | 2 296 ms |
| | CLS | 0.0000 | 0.0000 |
| | Speed Index | 2 338 ms | 2 621 ms |

Per-run LCP, because the medians hide the spread: `/` before 1869 / 2063 / 2057, after 2100 /
1994 / 2067 — flat. `/hire-va-4/` before 2416 / 1732 / 1703, after 2296 / 2302 / 2294 — the
before median rests on two fast runs and the after runs are unusually tight, so the apparent
564 ms regression is **not** supported. **LCP did not measurably move in either direction on
this machine.** That is consistent with the mechanism: on both pages the LCP element is a text
node, which `swap` already painted immediately in the fallback, so preloading changes *which
typeface* that paint uses, not when it happens. The wins are in CLS and FCP.

## Fix 4 — merging `--font-sans` into `--font-display`: measured and rejected

**Not applied.** `resources/css/app.css` is unchanged.

The saving is real (~48 KB, dropping `inter-latin-wght-normal.woff2`) and the change is one
line. It was authorised on the understanding that it might alter rendering. It does, visibly,
so it was backed out before shipping.

### First, a correction to the premise

The concern was that merging would change **headings**. It does not. Headings are on
`--font-display`, which keeps the dual-axis face and is untouched. The merge points
`--font-sans` at that same face, so what changes is **body copy, card headings and every other
run on `--font-sans`** — the opposite of the worry. Part 3 had this right: *"Collapsing them
would save another 48 KB and change how body copy renders."*

The mechanism is the `opsz` axis. `--font-sans` is backed by a wght-only subset, which pins
`opsz` at 14; `--font-display` keeps both axes, and with `font-optical-sizing` at its default
`auto` it renders at `opsz` = the element's own px size, clamped to [14, 32]. So the two are
metrically identical only at ≤ 14 px and diverge steadily above it.

Measured directly — same string, size and weight under each family, 260 combinations:

| Font size | Width change if merged |
| ---: | ---: |
| 13 px | −0.08 % |
| 14 px | −0.08 % |
| 15 px | −0.56 % |
| 16 px | −1.07 % |
| 17 px | −1.61 % |
| 18 px | −2.15 % |
| 20 px | −3.22 % |
| 24 px | −5.37 % |
| ≥ 32 px | −9.67 % |

The theme puts a lot of `--font-sans` text above 14 px — `/blog/` alone has 60 runs at 18 px.

### Page-level diff

Full-page 1440 px captures of `/`, `/hire-va-4/`, `/case-study/` and `/blog/`, images forced
eager and the page scrolled to the end first, before and after the merge. Isolation is exact:
the two captures are the same page load with only `--font-sans` repointed (plus an italic face
added to `Inter Display`, so italics are not synthesised), so no other tree change can leak in.
The noise floor is the same page captured twice with no change at all.

| Page | Page height | Runs on `--font-sans` | Wrap points moved | Lines gained/lost | Pixels differing | Diff % | Noise floor |
| :--- | :--- | ---: | ---: | ---: | ---: | ---: | ---: |
| `/` | 11 402 → 11 402 | 24 | 0 | 0 | 376 442 | 2.29 % | 2.04 % |
| `/hire-va-4/` | 8 922 → 8 922 | 97 | **13** | 0 | 65 584 | 0.51 % | 0.07 % |
| `/case-study/` | 3 524 → 3 524 | 66 | **1** | 0 | 24 145 | 0.48 % | 0.08 % |
| `/blog/` | 10 287 → **10 262** | 215 | **60** | **1** | 2 074 429 | **14.04 %** | **0.00 %** |

`/` is the only page that passes, and only because just 24 of its 305 text runs are on
`--font-sans` and all but one are ≤ 14 px — its 2.29 % sits inside a 2.04 % noise floor produced
by a rotating component, and no wrap point moves. The other three are 6×, 6× and unboundedly
above their noise floors.

**`/blog/` settles it.** Its noise floor is **zero** — two captures of the unchanged page are
byte-identical — and the merge changes **14 % of the page**. A row-band analysis of the diff
shows why: from y ≈ 200 to y ≈ 4 800 the per-band differences are modest (2 500–7 700 px) and
are the 60 card headings re-wrapping in place; from y ≈ 4 900 they jump to 40 000–75 000 px per
band and stay there. That step is the card heading *"How Much Does a Virtual Legal Assistant
Cost?"* dropping from three lines to two, which pulls **everything below it up by 25 px**. Half
the blog index moves.

`/hire-va-4/` is milder but not clean: 13 runs re-wrap, including the 28 px
`Book a Free 15-Minute Consultation` heading and a 15 px paragraph whose first line takes on
28.8 px more text.

### Verdict

This is not imperceptible, so per the standing instruction it was not shipped. A re-lined
heading and a 25 px page-height change are exactly the "silently restyled type scale" that is
worse than 48 KB. `--font-sans` and `--font-display` stay as two downloads.

### The version that might work

Worth recording for whoever revisits this: the merge is only unshippable *as a pure swap*. If
`--font-sans` consumers are simultaneously pinned with **`font-optical-sizing: none`**, the
dual-axis face renders at its default `opsz` instead of tracking the element size, which is very
nearly the `opsz 14` pin the wght-only subset provides — so the 48 KB file could go without
re-wrapping anything.

Measured the same way (4 strings × 4 weights per size, widths against the current
`--font-sans` face):

| Font size | Pure swap (`auto`) | With `font-optical-sizing: none` |
| ---: | ---: | ---: |
| 16 px | −1.07 % | 0.34 px (≈ 0.07 %) |
| 18 px | −2.15 % | 0.39 px |
| 20 px | −3.22 % | 0.44 px |
| 24 px | −5.37 % | 0.52 px |
| 32 px | −9.67 % | 0.70 px |

`none` is **not** bit-identical — a residual of about 0.022 px per px of font size remains at
every size, which looks like per-glyph subpixel rounding accumulating over a ~65-character
string rather than a different instance. But it is 15× closer than the pure swap and, at a third
of a pixel per line at body sizes, below the level that moved wrap points above.

That is a larger change than this task allowed and it still needs its own wrap-point and
screenshot pass before anyone trusts it — the 0.022 px/px residual has not been shown to be
harmless on a real page, only small. It is the version worth costing.

---

# Part 5 — Blog image weight (2026-09-15)

Closes the item Part 3 left as *"the largest remaining wins in the whole document"*: **`/blog/`
and the single post are image-bound.** They are not any more.

## Which pipeline the weight was on

**All of it was on the WordPress media library (`web/app/uploads/`), not on
`resources/images/pages/` → `public/images/`.** The `themeImages()` Vite plugin and the
`BlockDefaults::pageImg()` filename contract were **not touched**, nothing was written into
`public/`, and `tests/Unit/ThemeImageSourcesTest.php` is green and unweakened.

The blog templates hand-built a single `src` from an attachment and shipped it with no
`srcset`, no `sizes` and no `width`/`height`:

| Template | Requested | Actually painted at | Cost |
| :--- | :--- | :--- | ---: |
| `partials/blog-index.blade.php` | `medium_large` (768×512 PNG) | ≤ 420 px wide card | 321–391 KB × 20 |
| `partials/content-single.blade.php` — hero | **`full`** (1024×683 PNG) | ≤ 469 px wide, and it is the LCP element | 513 KB |
| `partials/talent-carousel.blade.php` — photos | **the untouched original** (771×1024 PNG) | **220 × 265 px card** | 197–666 KB **× 5** |
| `partials/talent-carousel.blade.php` — flags | the untouched original (512×512 PNG) | **24 × 24 px circle** | 8–18 KB × 5 |
| `partials/content-single.blade.php` — author avatar | the 250×250 original, over **`http://`** | 44 px and 100 px | 65 KB × 2 |

The talent carousel alone was **2.48 MB of the single post's 3.06 MB**, and it renders on all
88 posts that carry it. WordPress had already generated `226x300` and `768x1020` subsizes of
every one of those files at upload time; nothing was using them.

## What changed

New `app/Support/ResponsiveImage.php` builds `src`/`srcset`/`sizes`/`width`/`height` for an
attachment id, routing **every** candidate through the existing `BlockDefaults::preferWebp()`.
The three blog partials now call it. `MediaLibrary::id()` was extracted so the carousel, which
resolves art by filename, can reach the subsizes; `MediaLibrary::url()` is unchanged in
behaviour.

Two smaller defects fixed in passing:

- **`BlockDefaults::filterContentImgTag()` rewrote only `src`.** A matching `srcset` candidate
  always outranks `src`, so for any content image WordPress had given a srcset the WebP was
  never requested and the PNG was downloaded anyway. It now rewrites both.
- **The author avatar's stored `http://` URL** — the single post's mixed-content warning, and
  the reason its Best Practices score was 79 — is resolved to an attachment and re-emitted on
  the site's own scheme.

The first `/blog/` card is the LCP element at every breakpoint and was `loading="lazy"`; it is
now `fetchpriority="high"` with no lazy attribute. Every other blog image stays lazy.

## Before / after — **interleaved** A/B, mobile Lighthouse

⚠️ **The first attempt at these numbers was wrong and is not reported.** Another agent was
editing the same working tree and rebuilt `resources/js/app.js` and `layouts/app.blade.php`
*during* the measurement; the app bundle hash changed between run 1 and run 2, which showed up
as a TBT "regression" of 94 → 1665 ms on the post and cost ~25 performance points. None of it
was this change.

What is reported below is an **interleaved A/B on one tree**: the three partials are swapped
between their pre- and post-change form, three alternating passes, so anything else moving in
the tree is common-mode. `npx lighthouse@12.8.2`, default mobile preset, median of 3, values
per run shown.

| Page | Perf | LCP | CLS | TBT | Total weight | Images |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `/blog/` | 90 → **99** | 3.61 s → **1.56 s** | 0.000 → 0.000 | 0 → 19 ms | 2.74 MB → **0.47 MB** | 2 611 KB → **288 KB** |
| `/blog/outsourcing-customer-service/` | 79 → **99** | 4.71 s → **2.10 s** | 0.000 → 0.000 | 42 → 41 ms | 3.27 MB → **0.59 MB** | 3 011 KB → **274 KB** |
| `/blog/project-manager-cost/` | 81 → **99** | 4.53 s → **1.54 s** | 0.000 → 0.000 | 41 → 64 ms | 3.25 MB → **0.58 MB** | 2 999 KB → **268 KB** |
| `/case-study/` *(control)* | 100 → **100** | 1.68 s → 1.66 s | 0.002 → 0.002 | 0 → 0 ms | 0.26 MB → 0.26 MB | 75 KB → 75 KB |

Per-run performance: `/blog/` 89/90/99 → 99/99/99 · outsourcing 75/79/79 → 100/99/98 ·
project-manager-cost 81/80/82 → 100/99/99 · control 98/100/100 → 100/100/100.

**The control is unmoved on every column**, which is what says the change is scoped to the
blog templates.

**All three blog pages now clear the Perf ≥ 96 gate** — the first pages in this document to do
so besides `/` and `/case-study/`. **LCP < 1.2 s still passes on nothing**; the blog pages went
from 3.6–4.7 s to 1.5–2.1 s and what is left is not image bytes.

Image weight fell **89–91 %**. The single post went from **13 requests totalling 3.06 MB** to the
same 13 requests totalling **274 KB**; the six images that were over 450 KB are now 30–69 KB.

## Proof the rendering did not move

Playwright, `channel: 'chrome'`, full-page screenshots at **1440 px and 400 px**, both arms
captured on the same tree minutes apart, `img.loading` forced to `eager` and the page scrolled
before capture.

| Page | 1440 px | 400 px |
| :--- | ---: | ---: |
| `/blog/` | **0 px differ (0.000 %)** | 68 px (0.001 %) |
| `/blog/outsourcing-customer-service/` | 1 px shorter, see below | 10 270 px (0.111 %) |
| `/blog/project-manager-cost/` | 52 298 px (0.309 %) | 6 976 px (0.093 %) |
| `/case-study/` *(control, unchanged code)* | 8 375 px (0.165 %) | 1 312 px (0.049 %) |

**The control page sets the noise floor at 0.165 %** — it has no code change at all and still
differs by that much between two captures. Every measured page is at or below it. The residual
on the two posts is WebP's lossy edge reconstruction on the hero photo, visible only as sparse
single-pixel speckle in the diff, not as anything perceptible side by side.

A stronger check than pixels — `getBoundingClientRect()` on **every `<img>` on both pages at
both widths**:

```
blog  1440: docHeight 10287 -> 10287, 62 images, 0 broken, every box identical (<= 0.5px)
blog   400: docHeight 28911 -> 28911, 62 images, 0 broken, every box identical (<= 0.5px)
post  1440: docHeight 14375 -> 14374, 15 images, 0 broken, every box identical (<= 0.5px)
post   400: docHeight 23206 -> 23206, 15 images, 0 broken, every box identical (<= 0.5px)
```

The post's **1 px** at 1440 is `.rl-article-header-wrapper` going 472.813 → 472.656 px, i.e.
**0.157 px**, which then rounds the document height down. The hero sits in a flex item with no
definite height, so its box comes from the *loaded* file's intrinsic ratio, and WordPress's
768×512 subsize is 0.666667 where the 1024×683 original is 0.666992. It is sub-pixel, it is
inherent to using the generated sizes, and CLS measured 0.000 on every run of both arms.

### The trap in verifying this

The first screenshot pass reported `/blog/` at **21 % different** with the lower cards blank.
That was the capture, not the page: `decoding="async"` lets Chrome take a `fullPage` screenshot
before a just-loaded image has been rasterised. All 180 image URLs returned 200. Forcing
`img.decoding = 'sync'` and awaiting `img.decode()` on every image before capture took the same
page to **0 px different**. Any future screenshot diff in this repo needs that await, or it will
report phantom regressions on exactly the pages that were made faster.

### Quality at the worst case

The talent card is the largest downscale in the change — a 771×1024 original replaced by a
226×300 WebP in a 220×265 box. Element-level captures at three densities:

| Element | 1440 @1x | 1440 @2x | 400 @2.625x |
| :--- | ---: | ---: | ---: |
| talent carousel | 0.360 % | 0.003 % | 0.002 % |
| article hero | 0.001 % | 0.000 % | 0.002 % |
| author bio card | — | 0.001 % | 0.011 % |

At **@1x** the browser picks the 226 w candidate and the 0.36 % is very slightly softer hair
detail, invisible unless flicked between the two. At **@2x and above — every real phone,
including Lighthouse's own Moto G Power emulation at 2.625x — it picks the 768 w candidate and
the result is pixel-identical.** The 1x arm is the floor, not the common case.

## Found and deliberately not fixed

- **`getScheduledEvent` / mixed content in post *content*.** The two posts measured have no
  in-content images, so `filterContentImgTag`'s srcset fix is reasoned-about but **not
  measured**. A post that does carry body images should be checked before this is called done.
- **No intermediate size between 226 px and 768 px**, so a 220 px card at 2x asks for 577 px and
  is served 768 px (30–61 KB of WebP). Closing that needs a registered `add_image_size` plus a
  `wp media regenerate` across 119 posts — a media-library migration, not a template change.
- **The `2048x2048` and `1536x1536` subsizes are never offered**, because no blog image is wider
  than 1024 px to begin with. The originals are already small; there is nothing above `large`.
- **`/blog/` still loads all 20 cards' art.** They are lazy and the page is now 0.47 MB, so this
  is no longer worth paging or deferring further.
- **Flags are served at `thumbnail` (150 px) into a 24 px circle** because that is the smallest
  registered size. It is 2–4 KB each as WebP; not worth a new subsize.
- **CLS on `/case-study/` is still 0.002–0.052 and still unstable.** Untouched here.

---

# Part 6 — The live homepage on production (2026-09-20)

> ⚠️ **The metric numbers in this Part are superseded.** They are `n = 1` with uncontrolled TTFB.
> A TTFB-banded 2-run median taken the same day reads **Perf 30 / LCP 13.58 s / TTI 16.59 s /
> TBT 1,550 ms** — worse on every metric except TBT. Use
> [`scripts/perf-measure.sh`](../scripts/perf-measure.sh) (`npm run perf`) and see
> [`performance-homepage-plan.md`](performance-homepage-plan.md) for the controlled figures.
>
> **What stands is the composition**, which is what this Part was written to establish: the LCP
> element is a text node, 47 of 61 images are already lazy, and third-party JavaScript — not
> images — is the cost.

**This is the first measurement of this document taken against the real site rather than local
Herd, and it overturns Part 1's reading of `/`.** Local scored `/` at Perf 99 / LCP 1.5 s. The
same page in production scores **43**, with **TTI 14.8 s** — which is the "about 14 seconds to
fully load" that prompted the run.

Measured against `https://remoteleverage.com/` with `lighthouse@12.8.2`, default mobile preset
(Moto G Power, simulated Slow 4G, 4× CPU), headless Chrome. **n = 1**, not the 3-run median used
in Parts 1 and 3 — treat the individual numbers as indicative and the *composition* as the
finding, since composition is the part that does not move between runs.

| Metric | Local (Part 1) | **Production** |
| :--- | ---: | ---: |
| Performance | 99 | **43** |
| FCP | 1.21 s | 1.9 s |
| **LCP** | 1.69 s | **10.1 s** |
| **TTI** | — | **14.8 s** |
| **TBT** | 0 ms | **2 370 ms** |
| CLS | 0.011 | **0.000** ✅ |
| `server-response-time` | 757 ms | **100 ms** ✅ |

The server is not the problem — CloudFront answers the document in 100 ms. CLS is now a clean
zero. **Everything that regressed is main-thread JavaScript, and almost all of it is third party.**

## It is not the images

Worth stating plainly, because "lazy-load the images" was the hypothesis this run was opened to
test:

- The homepage requests **61 images, 47 of them already `loading="lazy"`** (77 %). The 14 eager
  ones are the header logo, the hero, and the above-fold logo strip — all correctly eager.
- All same-origin assets together are **1.12 MB across 65 files**: 519 KB eager images, 305 KB
  lazy images, 284 KB JS, 35 KB CSS.
- **The LCP element is the `<h1>`** — a text node. No image is on the LCP path at all, so
  deferring images cannot move LCP by construction.

Lazy loading is already applied here and has no headroom left. The three hero rasters
(`hero-bruno`, `hero-maria`, `hero-luana`) are ~170 KB each and *are* worth re-encoding, but that
is a byte-size question, not a loading-strategy one, and it is second-order next to the below.

## Third-party JavaScript is ~100 % of the blocking time

| Entity | Main thread | Blocking | Transfer |
| :--- | ---: | ---: | ---: |
| Facebook | 1 145 ms | **979 ms** | 277.1 KiB |
| Google Tag Manager | 926 ms | **749 ms** | 340.8 KiB |
| posthog.com | 960 ms | **654 ms** | 180.3 KiB |
| Customer.io | 89 ms | 0 ms | 29.6 KiB |
| LinkedIn Ads | 68 ms | 0 ms | 24.3 KiB |
| openai.com | 66 ms | 0 ms | 29.5 KiB |
| Bing Ads | 49 ms | 0 ms | 16.3 KiB |
| Google/Doubleclick, stape.ai, other | 17 ms | 0 ms | 8.7 KiB |
| **Total** | **~3 320 ms** | **2 382 ms** | **~907 KiB** |

Measured TBT is 2 370 ms. The three blocking entities sum to 2 382 ms. **Within noise, the
entire Total Blocking Time of the homepage is Meta + Google Tag + PostHog**, and third-party
transfer (~907 KiB) is *nine times* the theme's own JS.

## The two vendors excluded from the defer list are the two that matter

`config/pixels.php` already implements deferred SDK loading, and `MarketingPixelHooks::DEFERRABLE`
is `['linkedin', 'openai', 'bing_uet', 'tiktok']` — **every one of which is already at 0 ms
blocking**. The deferral is working perfectly on vendors that were never the problem. Meta and
`google_tag` are excluded on purpose, and between them they are **1 728 ms of the 2 370 ms TBT**.

The stated reasons, against what production actually measures:

- **`meta` — "Facebook is 67% of paid acquisition … not the pixel to experiment on."** This is a
  business call, not a technical one, and it stands on its own terms. Note only that it is being
  paid for twice: **two pixel IDs are configured and both fire** (`1430907207548734` and
  `1482937899395718`), each pulling its own ~140 KiB `signals/config` bundle, 758 ms of eval
  between them. If both are genuinely needed, the cost is understood; if one is historical, it is
  the single cheapest win available and costs no attribution. Worth asking marketing — this file
  already warns that "production … runs LinkedIn and OpenAI twice each because two people solved
  the same problem in two places."
- **`google_tag` — "the tag is already `async` so it costs no parse time."** ⚠️ **This is
  measurably wrong and should not be relied on.** `async` defers *fetch*, not *evaluation*: once
  downloaded the script still evaluates on the main thread. Google Tag Manager measures **926 ms
  of main-thread time, 749 ms of it blocking, across two containers** (`GT-NCNQ6N2` 195 KiB and
  `AW-1140618301` 146 KiB). Whether GA4 and Ads conversions *should* wait is still a legitimate
  business call — but it should be made knowing the tag costs three quarters of a second of
  blocking time, not zero.

## Also found

- **PostHog session replay is on.** `posthog-recorder.js` (66 KiB) loads on top of `array.js`
  (96.7 KiB); PostHog totals 960 ms of main thread. No `disable_session_recording` /
  `session_recording` setting appears in `TrackingHooks`, so this is the library default rather
  than a decision. Replay on 100 % of homepage traffic is unusual; sampling it is a config change.
- **The theme's own bundle is 84 % unused on this page** — `prod-BXM4E1SX.js`, 110.5 KiB unused of
  132.2 KiB. The only first-party item in the opportunity list.
- **`uses-rel-preconnect` still claims 430 ms**, the same finding as Part 1 — now against six
  distinct third-party origins.

## Intermittent 301-to-self on the apex — **unrelated to the above, worth its own look**

While probing, `https://remoteleverage.com/` was observed returning **`301` with
`location: https://remoteleverage.com/`** — a redirect to itself — on the first request of a
CloudFront cache cycle (`age: 1`), then `200` for the remainder of the 60 s TTL. Reproduced across
12 sequential requests and on both `GET` and `HEAD`, with and without a cache-busting query
string. `cache-control` is `public, s-maxage=60, stale-while-revalidate=30`.

Browsers mostly do not notice, because the 200 is what gets cached and served for the next ~59 s —
which is why this is not visible in the Lighthouse run. But a monitor, link checker or social
scraper that happens to hit the revalidation edge sees an infinite redirect. Mechanism not
established; a canonical/`WP_HOME` redirect firing on the origin's cache-miss path is the obvious
suspect. **Not diagnosed here — flagged only.**

## What this changes about Part 1

Part 1's warning that local numbers are "a floor and a relative signal" is now quantified for one
page: local missed a 2.4 s main-thread stall entirely, because **none of the third-party tags that
cause it are configured on local**. Any future performance claim about this site needs a
production run; the local suite cannot see the dominant cost.

## Follow-up: blocked-vendor arms (controlled, 2026-09-20)

Superseded the uncontrolled arms first recorded here. `npm run perf -- --ceiling --runs 3
--ttfb-max 400` — median of 3, every run inside a 70–233 ms TTFB band:

| Arm | Perf | LCP | TTI | TBT | Δ TBT |
| :--- | ---: | ---: | ---: | ---: | ---: |
| `baseline` | 31 | 13.26 s | 15.71 s | 1,549 ms | — |
| `no-meta` | 34 | 10.95 s | 14.27 s | 1,041 ms | −508 ms |
| `no-google-tag` | 33 | 13.22 s | 16.57 s | 1,062 ms | −487 ms |
| `no-posthog` | 33 | 13.14 s | 13.60 s | 1,380 ms | −169 ms |
| `no-three` | **59** | 8.24 s | 8.36 s | **13 ms** | **−1,536 ms** |

- **The three are 99.2 % of Total Blocking Time**, confirmed under control.
- **Removal is superadditive**: individual savings sum to 1,164 ms, all three together save
  1,536 ms. Each vendor removed alone under-delivers by about a third, because the others expand
  into the freed main thread.
- **PostHog is the smallest contributor (−169 ms), not the largest.** The uncontrolled run that
  ranked it first at −1,360 ms was measuring TTFB noise.
- **`no-three` still only scores 59 with LCP 8.24 s**, so a second, bandwidth-bound problem sits
  underneath the JavaScript one.
- **TBT banded tightly** (`no-meta`: 1041/1043/1036 ms) but **LCP did not** (same runs: 10.45 /
  10.95 / 20.12 s). Do not read LCP across arms.
- **~45 % of attempts were discarded for missing the CDN**, at 400 ms–1.9 s origin TTFB.

What to do about it: [`performance-homepage-plan.md`](performance-homepage-plan.md).
