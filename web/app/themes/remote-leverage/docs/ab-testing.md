# A/B testing and experiments

Run an experiment on one URL, in one pattern, with PostHog choosing the variant in the browser.

> **Built 2026-09-22.** The rendering path is complete and covered by tests. Two things are
> deliberately not done yet and are listed under [What is not done](#what-is-not-done) — read that
> before planning an experiment whose success metric is a server-side event.

## What this replaces

On the legacy Elementor site an experiment meant a **page per variant**: duplicate the landing
page, edit the copy, point a slice of ad spend at the second URL. That is why
[content-migration-checklist.md §5](content-migration-checklist.md) lists ~43 in-sitemap pages
called things like `test-variant-b` and `new-home-26-v2b`.

The cost was never the duplication. It was that every variant is a **fork**: a fix to the winner
never reaches the loser, the two drift, and later nobody can say which URL is canonical. The 43
pages are the receipt.

## Authoring an experiment

Two `acf/experiment` arms with the same flag key, each wrapping ordinary blocks. Nothing is forked.

```php
use App\Support\BlockDefaults;

echo BlockDefaults::renderExperiment('home-hero-2026', 'control',
    BlockDefaults::renderHomeHero(),
    true,                                   // the default arm — exactly one per flag
);

echo BlockDefaults::renderExperiment('home-hero-2026', 'test',
    BlockDefaults::renderHomeHero(['headline' => 'Hire your team in 72 hours']),
);
```

`acf/experiment` is the theme's **only InnerBlocks block**, so its markup is an open/close pair
rather than the self-closing comment every other block uses. `renderExperiment()` handles that;
hand-writing the comment and self-closing it drops the whole arm with no error.

Rules that matter:

| Rule | Why |
| :--- | :--- |
| Exactly one arm per flag sets `$isDefault` | It is the arm that renders before the decision and the one everybody falls back to |
| Make the default the **safe** arm, usually the current page | A visitor who blocks PostHog sees it permanently |
| Variant names match PostHog | For a boolean flag use `control` and `test` — PostHog answers those with `false` and `true` |
| Both arms ship in the HTML | Do not put anything in an arm that must not reach a visitor who was not assigned it |

That last row is the real constraint: this hides markup, it does not withhold it. Both arms are in
the page source for everyone. Never use an experiment arm as an access control.

## Testing it before production

This is the part the legacy process could not do at all. Three layers, cheapest first.

### 1. Force a variant, anywhere, with a query parameter

```
/?rl_variant=home-hero-2026:test
/?rl_variant=home-hero-2026:test,pricing-2026:control
```

Works in every environment, including production and local, whether or not PostHog is loaded. It
is the way to show a stakeholder variant B without waiting for a coin flip to land on them.

An override deliberately **does not call `getFeatureFlag()`**, so it fires no `$feature_flag_called`
and registers no `$feature/…` property. QA traffic cannot contaminate the experiment's numbers, and
that is also why an override is not a test that assignment works — only that the arm renders.

The query string is part of the FastCGI cache key, so an override URL gets its own cache entry and
cannot poison the shared one.

### 2. Rehearse the real assignment on staging or locally

`POSTHOG_FLAG_ENVIRONMENTS` (default `local,development,staging`) loads PostHog in **flags-only**
mode: flags evaluate against the real project, and a `before_send` hook returning null drops every
event before it is queued. Session recording and autocapture are off too.

So staging reads the same flag definitions and rollout percentages production will, and writes
nothing back. Toggle the flag in PostHog, reload staging, watch the arm change.

What flags-only costs you: no `$feature_flag_called`, so **exposure counts cannot be checked from
staging** — only that the right arm paints. Checking the funnel itself needs production.

> **Smoke-test this once on staging before trusting it.** In the network tab you should see a
> request to `/flags/` and **no** requests to `/e/`. The suppression uses `before_send` rather than
> `opt_out_capturing_by_default` precisely because opting out takes the flags request with it in
> some posthog-js versions — but `array.js` is loaded from PostHog's CDN at whatever version is
> current, so this is the one behaviour in this document that is not pinned by a test in this repo.

### 3. Check the fallbacks, because they are what most visitors with a problem will see

| Simulate | Expect |
| :--- | :--- |
| JavaScript off | The default arm, immediately |
| Block `*.posthog.com` in devtools | The default arm, after at most `POSTHOG_EXPERIMENT_TIMEOUT_MS` |
| A flag returning a variant name the page does not ship | The default arm |
| Assigned to the default arm | No swap and no flicker at all |

The automated coverage for all of this is `tests/Unit/ExperimentRenderingTest.php`.

## How the decision works

**The default arm renders visible and every other arm renders `hidden`.** That single ordering is
the whole fallback strategy: JavaScript off, PostHog blocked, `/flags/` down, or an unrecognised
variant name all land on a correct page, with no timeout and no layout shift. Hiding every arm and
revealing the winner would have turned all four into a blank section.

The consequence is that someone assigned to a non-default arm can see the default one first. In
practice they usually do not:

1. `array.js` is requested immediately from `<head>` — **no longer deferred**, see below.
2. posthog-js restores flags from `localStorage` during `init()`, so a returning visitor already has
   an answer before the body is parsed.
3. `window.rlExp.resolve()` is called inline, immediately after each arm's markup, rather than on
   `DOMContentLoaded`.

Together those mean the swap normally happens while the parser is still in the body — before first
paint. It is a visitor's first-ever page view that can flicker, bounded by
`POSTHOG_EXPERIMENT_TIMEOUT_MS`.

### The deferral that had to be reversed

Between 2026-09-18 and 2026-09-22, `array.js` was held back until interaction, `load`, idle or 6s,
to keep it off the critical path. That is incompatible with deciding a test during render: until
the real library lands, `window.posthog` is the queueing stub, and **the stub's `getFeatureFlag()`
returns `undefined` rather than queueing an answer**. A queued `capture()` is replayed later and is
still correct; a queued flag read is not, because the page needed the answer before it painted.

So every page now pays `array.js` up front. That is a real cost and the price of the feature —
re-measure against [performance-baseline.md](performance-baseline.md) rather than assuming the
numbers there still hold. `MarketingPixelTest` fails if `requestIdleCallback` reappears in the
snippet, so the deferral cannot come back by accident.

### Why the decision cannot be made in PHP

[`docker/nginx.conf`](../../../../../docker/nginx.conf) caches logged-out HTML and advertises
`public, s-maxage=60, stale-while-revalidate=600` to CloudFront, keyed on
`$scheme$request_method$host$request_uri`, and **nothing invalidates it**. A variant chosen in PHP
is stored and handed to every visitor who hits that entry — a 50/50 split becomes "whoever missed
the cache decides for the next minute". Bootstrapping flags into the HTML has the same problem, and
worse: the bootstrapped distinct id would be cached too, giving everyone in the window one identity.

Requests nginx excludes from the cache — `/livewire-*`, `/wp-json`, non-GET methods, logged-in
sessions, `/referrer-*`, `vathankyou` — *can* decide server-side. `PostHogClient::isFeatureEnabled()`
is still correct there, and that is where a flag on an internal tool or a form branch belongs.

## Configuration

| Variable | Default | Does |
| :--- | :--- | :--- |
| `POSTHOG_ENVIRONMENTS` | `production` | Which environments capture |
| `POSTHOG_FLAG_ENVIRONMENTS` | `local,development,staging` | Which get flags with every event dropped. The capture gate wins where both list an environment, so adding production here cannot silence production |
| `POSTHOG_EXPERIMENT_TIMEOUT_MS` | `1500` | How long the reveal waits before painting the default. A *visible* budget, not a network one: it bounds how long someone assigned to B looks at A. Also passed to posthog-js as `feature_flag_request_timeout_ms` so the two cannot disagree |

## What is not done

1. **Server-side conversion events carry no `$feature/<flag>` property.** PostHog scores an
   experiment from that property on the conversion event. Client-side events are fine — the runtime
   calls `posthog.register()` — but anything sent from PHP (`HandleLeadCreatedForTracking`, the
   booking funnel's Customer.io half, `CheckoutTelemetry`) is not tagged. Person-level attribution
   still works, because `MultistepBookingWizard::identifyInBrowser()` merges the browser's device id
   with the lead's email, so an experiment measured on a *client-side* conversion is safe today.
   Wiring it up means carrying `window.rlExp.assigned` into the Livewire component and forwarding it
   through `PostHogClient::capture()`; the component already does exactly this for
   `posthog_session_id`, so the channel exists.
2. **Nothing enforces one default arm per flag** across a pattern. Two defaults means both arms
   render until the decision; zero means the runtime falls back to the first arm in DOM order.
   Neither breaks the page, and neither is caught at build time.

## Files

| File | Role |
| :--- | :--- |
| `app/Blocks/ExperimentBlock.php` | The block. The theme's only InnerBlocks block |
| `resources/views/blocks/experiment.blade.php` | Renders an arm; renders all arms labelled in the editor |
| `app/Support/BlockDefaults.php` | `renderExperiment()` |
| `app/Infrastructure/WordPress/Hooks/TrackingHooks.php` | `injectPostHogSnippet()`, `injectExperimentRuntime()` |
| `config/services.php` | `posthog.flag_environments`, `posthog.experiment_timeout_ms` |
| `tests/Unit/ExperimentRenderingTest.php` | The snippet, the runtime, key sanitisation, pattern markup |

`EvaluateVariantAction` and `PostHogRedirectMiddleware` were **deleted on 2026-09-22**. They
implemented the legacy redirect-per-variant model in PHP, were registered nowhere, and read a
`ph_distinct_id` cookie PostHog has never set. See [known-issues.md §27](known-issues.md).

## Related

- [domains/tracking.md](domains/tracking.md) — the snippet, dual dispatch, booking funnel events
- [content-migration-checklist.md §5](content-migration-checklist.md) — the 43 legacy variant pages
- [performance-baseline.md](performance-baseline.md) — the budget the eager load spends against
