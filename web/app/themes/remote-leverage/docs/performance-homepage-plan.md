# Plan — fixing Meta, Google Tag and PostHog on the homepage

Companion to [`performance-baseline.md`](performance-baseline.md) Part 6, which measured the live
homepage at **Perf 43 / LCP 10.1 s / TTI 14.8 s / TBT 2,370 ms** and attributed essentially all of
the blocking time to three third parties. This is what to do about it.

Written 2026-09-20. Nothing in here has been implemented.

---

## The evidence this plan rests on

**Controlled measurement, 2026-09-20.** `npm run perf -- --ceiling --runs 3 --ttfb-max 400`:
median of 3 runs per arm, every run inside a 70–233 ms TTFB band, cache warmed before each run.
Raw JSON in `.perf/ceiling/`.

| Arm | Perf | LCP | TTI | **TBT** | Δ TBT vs baseline |
| :--- | ---: | ---: | ---: | ---: | ---: |
| `baseline` | 31 | 13.26 s | 15.71 s | **1,549 ms** | — |
| `no-meta` | 34 | 10.95 s | 14.27 s | **1,041 ms** | −508 ms |
| `no-google-tag` | 33 | 13.22 s | 16.57 s | **1,062 ms** | −487 ms |
| `no-posthog` | 33 | 13.14 s | 13.60 s | **1,380 ms** | −169 ms |
| **`no-three`** | **59** | **8.24 s** | **8.36 s** | **13 ms** | **−1,536 ms** |

Three findings, in order of how much they change the plan.

### 1. The three together are still ~100 % of blocking time

1,549 ms → 13 ms. **99.2 %.** This is the claim the whole plan rests on and it survives
controlled measurement intact.

### 2. But removal is superadditive — and that inverts the sequencing advice

The individual savings sum to **1,164 ms**; removing all three saves **1,536 ms**. Removing one
vendor frees main thread that the other two immediately expand to fill, so **each removal on its
own under-delivers by roughly a third.**

The practical consequence: *do not* pick the single biggest item, ship it, and judge the approach
by the result — that path pays full cost for partial benefit and will read as failure. The value
is concentrated in getting all three off the critical path. Sequence the work so it lands
together, or at least judge it only at the end.

### 3. PostHog is the *smallest* of the three, not the largest

An earlier uncontrolled run ranked PostHog first at −1,360 ms. **Controlled, it is last at
−169 ms** — a third of Meta's and Google's contribution. The uncontrolled ranking was an artefact
of TTFB noise. Phase 1 below is ordered on the controlled numbers.

### 4. Even with all three gone, the page scores 59

`no-three` is Perf **59**, LCP **8.24 s**, TTI **8.36 s** — with TBT at essentially zero and every
image still loading. So removing the three vendors takes the homepage from "unusable" to
"mediocre", not to "good". **There is a second, bandwidth-bound problem underneath this one**, and
it is not visible until the JavaScript is out of the way. Scope expectations accordingly: this
plan is worth roughly +28 Lighthouse points, not +65.

### ⚠️ Only TBT is comparable across these arms

TTFB was banded, which stabilised TBT to within a few per cent inside each arm — `no-meta` ran
1041 / 1043 / 1036 ms. **LCP did not stabilise**: the same three `no-meta` runs were 10.45 /
10.95 / **20.12** s with TTFB at 75 / 104 / 79 ms. So LCP carries noise that TTFB banding does not
remove, and the LCP column above should not be read across rows without more runs.

Roughly **45 % of all attempts were discarded** for missing the CDN — see "Adjacent findings",
because that is a fact about real visitors, not just about measurement.

## The principle: deferral moves work, removal removes it

The instinct is to add Meta and `google_tag` to the defer list. That should be phase 3, not phase 1,
and the reason is already in the repo:

**PostHog is *already* deferred** — `TrackingHooks::injectPostHogSnippet()` carries its own copy of
the defer logic (`__rlPosthogRequested`, the same interaction/`load`/idle/timeout flush, the same
`pixels.defer.timeout_ms`). It still costs **654 ms of blocking time and 960 ms of main thread**.
`config/pixels.php` says the same thing about TikTok in its own words: *"Deferring reduced that
cost; it did not remove it."*

This is not a bug in the deferral. TBT is the sum of long tasks between FCP and TTI, and the flush
fires on the earliest of interaction, `load`, idle, or 6,000 ms — all of which land *inside* that
window. Deferring reduces contention during LCP, which is worth having. It does not reduce total
main-thread work, and the user's complaint (TTI 14.8 s) is a total-work problem.

**So: cut work first, reschedule what remains second.**

---

## Phase 0 — make the measurement trustworthy — **built 2026-09-20**

Nothing below can be validated with the current method. Part 6 is `n = 1`; the arms above show
TTFB swinging 8.5× (and once 200×). This repo has already been bitten twice — Part 1 logs a
58/61/26 spread on one page, and Part 3 had to re-measure its own "before" column because Part 1
disagreed with it by 3 points on an unchanged build.

**[`scripts/perf-measure.sh`](../scripts/perf-measure.sh) now exists**, wired as `npm run perf`
(from the theme directory, like every other npm script):

```bash
npm run perf                                    # 5 runs, mobile, production homepage
npm run perf -- --runs 3 --url https://…/       # another page
npm run perf -- --ceiling                       # + the blocked-vendor arms
npm run perf -- --arm 'no-meta=*connect.facebook.net*'
npm run perf -- --preset desktop --ttfb-max 0   # 0 disables the TTFB band
```

What it does, and why each part is there:

1. **N runs, median reported, every run's value printed.** Per-run values stay visible because
   that is the only way the variance in Part 1 was ever noticed.
2. **TTFB band.** Every run's `server-response-time` is checked against `--ttfb-max` (default
   300 ms). Runs above it are **discarded and retried** — a run that missed the CDN measured a
   cold origin, not the change under test. This is the control that the Part 6 arms lacked.
3. **The discard count is reported**, and if an arm cannot get enough runs inside the band the
   script says so explicitly rather than quietly reporting a median of two. That outcome is
   itself a finding about the origin — see "Adjacent findings".
4. **CDN warm before each run**, two requests, so arms start comparable. Note this deliberately
   measures the *cached* path.
5. **Blocked-vendor ceiling arms** via `--ceiling`, so the theoretical win can be re-measured
   after each phase and compared against what was actually banked.

Output goes to `.perf/<utc-timestamp>/` (gitignored): every run's raw JSON plus a `summary.md`
with a median table and a per-run table, shaped to paste straight into
[`performance-baseline.md`](performance-baseline.md).

The summary also restates the caveat in place, because it is the thing most likely to be
forgotten when someone reads a table six weeks from now: **only TBT is reliably comparable across
arms.**

### First controlled measurement — and it changes the baseline

A validation run (`--runs 2 --ttfb-max 400`) did two things at once.

**It proved the control works.** With TTFB held at 82 ms and 72 ms, run-to-run variance collapsed:

| | run 1 | run 2 | spread |
| :--- | ---: | ---: | ---: |
| Perf | 30 | 29 | 1 pt |
| LCP | 13.34 s | 13.81 s | 3.5 % |
| TBT | 1,583 ms | 1,516 ms | 4.4 % |

That is the first time two runs of this page have agreed. Compare Part 1's 58/61/26 on
`/hire-va-4/`, or the 71 ms–20.5 s TTFB spread in the arms above. **The variance was never
Lighthouse being flaky — it was TTFB, and banding it removes almost all of it.**

**And it moved the baseline.** One run was discarded at 922 ms TTFB; it would have reported
Perf 27 / LCP 12.5 s. The kept median:

| | Part 6 (`n = 1`, uncontrolled) | **Controlled median** |
| :--- | ---: | ---: |
| Perf | 43 | **30** |
| LCP | 10.1 s | **13.58 s** |
| TTI | 14.8 s | **16.59 s** |
| TBT | 2,368 ms | **1,550 ms** |
| CLS | 0.000 | 0.000 |

**The homepage is worse than Part 6 reported, not better.** TTI at 16.6 s means the "about 14
seconds" that prompted this work was, if anything, generous. TBT is lower than the single run
suggested (1,550 ms vs 2,368 ms), so the *absolute* per-vendor savings quoted in "The evidence
this plan rests on" are overstated even though the *proportion* — third parties being effectively
all of it — is not yet re-tested.

**Consequence for this plan: the arm table above is superseded and must be re-run.** The ordering
of the phases does not depend on those absolute numbers, so the plan stands; the figures do not.

## Phase 1 — remove duplicated work

Everything here is waste by inspection. None of it costs a tracked event, and none of it needs a
business decision beyond confirming a fact.

### 1.1 The Google tag fans out to four destinations; the container duplicates two

`config/pixels.php` already documents this and has already done the hard research:

> Cost: this tag fans out to four destination configs, ~730KB transferred on a cold load, and the
> container's Google tag duplicates two of them. The overlap is worth removing, but which side to
> cut is a question for the ads accounts and GTM Preview rather than something to infer from a
> payload.

The live page confirms it: `GT-NCNQ6N2` (195 KiB) pulls in `AW-1140618301` (146 KiB) through its
own routing map, not through our `google_tag.ids` — which is just `GT-NCNQ6N2`. So this is **not
fixable in this repo**; it is a change in the Google tag / GTM UI.

That note has been sitting there since 2026-09-18 with the research done and the decision
outstanding. It is the largest single transfer item on the page (341 KiB, 749 ms blocking), and it
is the cheapest thing on this list because the duplication is already established.

**Action:** take the four destinations to whoever owns the ads accounts, confirm in GTM Preview
which are live, delete the duplicated pair. No deploy.

### 1.2 Two Meta pixels, each pulling its own config bundle

`META_PIXEL_IDS` defaults to `1430907207548734,1482937899395718` and both `fbq('init')` calls fire.
Each initialised id makes `fbevents.js` fetch its own `signals/config/<id>` bundle — ~140 KiB and
~139 KiB, 280 ms and 478 ms of eval respectively, on top of `fbevents.js` itself (110 KiB, 386 ms).

Roughly **half of Meta's cost on this page is the second pixel.**

This file already warns about exactly this failure mode: *"Production is the cautionary tale: it
runs LinkedIn and OpenAI twice each because two people solved the same problem in two places, years
apart, and nothing anywhere said so."*

**Action:** check Meta Events Manager for whether both pixels are live destinations receiving spend.
If one is historical, drop it from `META_PIXEL_IDS` — one env var, no code. Note
`MetaConversionsApiClient::pixelIds()` reads the same config, so the server-side events follow
automatically and stay consistent. `MarketingPixelTest` asserts on the first id only, so the test
suite does not block this.

**If both are genuinely needed, this line item closes as "understood cost", not as a fix.**

### 1.3 PostHog session replay runs on 100 % of traffic

`posthog-recorder.js` (66 KiB) loads on top of `array.js` (96.7 KiB). The init call is:

```js
posthog.init('…', {api_host:'…', person_profiles:'identified_only', disable_surveys:true});
```

`person_profiles` and `disable_surveys` were set deliberately; **session recording was not** — no
`disable_session_recording` and no sampling appears anywhere in `TrackingHooks`. So replay-on is
the library default rather than a decision anyone made.

**Controlled, PostHog is the *smallest* of the three — −169 ms when blocked**, against Meta's −508 ms and the Google tag's −487 ms. An earlier uncontrolled run had it first at −1,360 ms; that was TTFB noise. It stays on the list because replay-on-100 % is an unmade decision rather than a considered one, and because 66 KiB of transfer is worth having back — but it is the *least* valuable of the three for blocking time, and should not be the thing anyone starts with.

**Action:** decide a sampling rate rather than leaving it at the default. Options, cheapest first:
enable PostHog's own server-side `sample_rate` in project settings (no deploy); or set
`disable_session_recording: true` on pages with no conversion path and start it explicitly on the
booking flow via `posthog.startSessionRecording()` — which is already in the stub's method list.

Watch out: `MultistepBookingWizard` reads `posthog_session_id` and the replay URL for the lead
timeline, and `resources/js/payment-gateway.js` reads `get_session_id` / `get_session_replay_url`.
**Sampling replay away on the booking path would blind the lead timeline**, so the booking flow must
stay at 100 %. That is an argument for per-page control, not for a blanket rate.

---

## Phase 2 — the server-side path already exists, and it changes the Meta argument

Before deferring or trimming the Meta browser pixel, note what is already built and live:

- **`MetaConversionsApiClient`** sends `Lead` server-side to *every* configured pixel id, with
  `event_id` deduplication so the browser and server copies of the same conversion collapse into
  one. Its config comment says the token and API version were *"verified against its send log
  2026-09-18"*, i.e. this is running, not scaffolding.
- **`SendLeadToMetaConversionsApi`** is wired to `LeadCreated`.
- **`GoogleEnhancedConversion`** builds a byte-identical `event_id` for the Google side.

The stated reason Meta is excluded from deferral is *"Facebook is 67% of paid acquisition … not the
pixel to experiment on."* That protects **conversions** — and conversions are already delivered
server-side and deduplicated. What the browser pixel uniquely provides is PageView, Meta's
automatic-event collection, and the `_fbp` cookie for audience building.

That does not make the exclusion wrong. It does mean the risk being managed is **"late or missing
PageViews for visitors who bounce before the flush"**, not "lost conversions" — a materially
smaller thing than the comment implies, and worth re-deciding on that basis.

**Action:** confirm in Meta Events Manager that CAPI `Lead` events are arriving and deduplicating
against the browser pixel (Events Manager shows the dedup rate directly). If they are, phase 3
becomes a much easier decision. If they are not, **fix that first** — it is a conversion-tracking
bug that matters more than page speed.

---

## Phase 3 — defer Meta and the Google tag

Only after phases 1–2, and knowing this buys LCP rather than TBT.

### 3.1 Correct the stated rationale for `google_tag`

`config/pixels.php` currently says:

> the tag is already `async` so it costs no parse time

**This is measurably false and should be corrected in the file regardless of whether anything else
changes here.** `async` defers *fetch*, not *evaluation*; the script still evaluates on the main
thread when it lands. Measured: 926 ms main thread, 749 ms blocking. Whether GA4 and Ads
conversions should wait behind an idle callback is a legitimate business call — but it is currently
being made against a stated cost of zero, and the real number is three quarters of a second.

### 3.2 The mechanics, if the decision is to defer

Both are straightforward because the pattern already exists and is test-enforced:

- `MarketingPixelHooks::DEFERRABLE` is `['linkedin', 'openai', 'bing_uet', 'tiktok']` — add the two.
- **Wrap only the SDK insertion, never the stub.** For Meta that means the
  `!function(f,b,e,v,n,t,s){…}` loader goes inside `window.rlDefer(function(){…})` while
  `fbq('init', …)` and `fbq('track','PageView')` stay synchronous — `fbq`'s own queue drains when
  `fbevents.js` arrives, so no event is lost. This is exactly the LinkedIn shape, and
  `MarketingPixelTest` already asserts it ("LinkedIn queues its ids and stub before the wrapper, not
  inside it").
- For the Google tag, `<script async src="…gtag/js">` becomes an `rlDefer`'d injection; the
  `dataLayer` / `gtag()` shim and the `config` calls stay inline.
- **`MarketingPixelTest` has a test named "Meta and the Google tag are not deferrable, however they
  are configured"** that asserts `isDeferred('meta') === false` and that the rendered output does
  not contain `rlDefer`. It has to be rewritten in the same change — it is the encoded form of the
  decision, so changing it *is* the decision.

### 3.3 Expect diminishing returns

The arm table shows the three vendors contend. Deferring Meta and the Google tag after phase 1 has
already cut PostHog and the duplicate destinations will bank **less** than the numbers above
suggest, because freed main thread gets taken by whatever is left. Re-run the phase 0 harness
rather than assuming the deltas add up.

---

## Phase 4 — the smaller, first-party items

- **The theme bundle is 84 % unused on this page** — `prod-*.js`, 110.5 KiB unused of 132.2 KiB.
  `resources/js/app.js` is a single 1,206-line entry for the whole site. It already dynamic-imports
  `intl-tel-input`, Sentry and PostHog and sets `modulePreload: false`, so the cheap wins are taken;
  going further means per-route splitting. **Lowest value of anything here** — it is 132 KiB against
  ~907 KiB of third party, and it is the only item requiring real refactoring.
- **Hero images.** `hero-bruno`, `hero-maria`, `hero-luana` are ~170 KiB each, ~508 KiB for three.
  Correctly eager (they are above the fold) and not on the LCP path, since **LCP is the `<h1>`**.
  Worth re-encoding for bytes; will not move LCP.

---

## The origin and the CDN — measured 2026-09-20, one change made

This started as "raise the CDN TTL" and turned into two separate problems.

### What was measured

| Probe | What it exercises | TTFB observed |
| :--- | :--- | :--- |
| `/healthz` | nginx only, literal `return 200 "ok\n"`, **no PHP** | 0.29 s – **2.46 s** |
| `/__probe-<rand>/` | full WordPress 404 render, unique cache key | 0.74 s – **3.60 s** |
| `/` on a CloudFront hit | edge only | ~0.20 s typical, spikes to **15.7 s** |
| Lighthouse runs | — | **~45 % exceeded a 400 ms band** |

**`/healthz` is the important row.** It returns a hardcoded string from nginx and never touches
PHP; it should be ~50 ms. At 2.46 s the latency is upstream of WordPress entirely, so **this is
not a WordPress problem and not a cache-TTL problem.** The nginx config notes the origin runs on
1 vCPU; a CPU-starved container or the ALB path fits what is measured. It needs origin-side
investigation (task CPU, task count, ALB target latency) that cannot be done from here.

> An earlier probe in this session reported a "~36 s cold render" using `?cb=` query strings to
> force a miss. **That measurement was invalid** — CloudFront does not reliably key on arbitrary
> query strings, so those requests did not do what was intended. The table above replaces it.
> (Search is unaffected: `/?s=…` returns real results, so there is no query-string bug.)

### What was fixed: the stale window was far too small

`age` was observed cycling **9 → 69** under steady traffic. 69 > `s-maxage=60` is direct proof
CloudFront honours `stale-while-revalidate` on this distribution — the mechanism works, the window
was just wrong.

At `s-maxage=60, stale-while-revalidate=30`, an object idle for more than ~90 s falls out of the
stale-serve window and the next visitor pays a **blocking** origin fetch. On this traffic profile
that is common, and it is exactly why ~45 % of Lighthouse attempts missed: runs are minutes apart.
Real visitors arriving after a quiet spell hit the same thing.

**Changed in [`docker/nginx.conf`](../../../../../docker/nginx.conf)** (validated with `nginx -t`,
not deployed):

```diff
-"public, s-maxage=60, stale-while-revalidate=30"
+"public, s-maxage=60, stale-while-revalidate=600, stale-if-error=86400"
```

- `stale-while-revalidate=600` — CloudFront answers instantly from cache after a quiet spell and
  refreshes in the background. **This does not widen real staleness**: the first stale request
  triggers the revalidation, which completes in seconds, so content is behind by one request
  rather than by the window. The window only matters when revalidation keeps failing, which is
  when serving stale is the right answer. 600 s rather than a day, so a persistently broken origin
  surfaces rather than being masked.
- `stale-if-error=86400` — an origin 5xx serves the last good copy instead of an error page.

### What was deliberately NOT changed: `s-maxage`

**`s-maxage` stays at 60 s, and raising it is blocked on a prerequisite.** Nothing invalidates this
cache: there is no CloudFront invalidation on deploy and no purge on publish —
[`deployment.md`](deployment.md) documents invalidation as a *manual* step taken only after a
cache-header change. So `s-maxage` is the sole bound on how long an editor's change takes to go
live, and raising it directly degrades the publishing experience.

To unlock a longer TTL, add a purge first. In rough order of value:

1. **Invalidate on deploy** — one `aws cloudfront create-invalidation --paths '/*'` step in
   `deploy-production.yml` / `deploy-staging.yml`. The command is already documented; it is simply
   not wired in. Also fixes the stale-response-header problem `deployment.md` warns about.
2. **Purge on publish** — a `save_post` / `transition_post_status` hook invalidating the affected
   URLs plus `/`. This is what actually decouples TTL from editor experience.
3. **Then** raise `s-maxage` to hours and let invalidation handle freshness.

### Note on the deploy-time header change

The new `Cache-Control` only reaches visitors for objects fetched after the deploy. Entries
CloudFront already holds keep serving the old `stale-while-revalidate=30` **and their stored
headers** until they age out. Per `deployment.md`, **invalidate the distribution after deploying
this**, or the change will look like it did nothing.

### Still open

- **The origin is slow independently of caching** (`/healthz` at 2.46 s). Widening the stale window
  hides this from most visitors; it does not fix it, and everyone who does reach the origin still
  waits. Highest-value remaining item on this page, and it is infrastructure, not code.
- **Intermittent `301` to itself** at the start of a cache cycle (see
  [`performance-baseline.md`](performance-baseline.md) Part 6). Plausibly the same root cause as
  the latency spikes; not diagnosed.

## What this plan deliberately does not do

**Lazy-load more images.** 47 of the homepage's 61 images are already lazy, the 14 eager ones are
above the fold, the LCP element is a text node, and all same-origin assets together are 1.12 MB
against ~907 KiB of third-party JavaScript. There is no headroom there, and the blocked-vendor
table shows why: with three script origins blocked and every image still loading, TBT is 14 ms.
