<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

class TrackingHooks
{
    /**
     * Path fragment => Customer.io event name, as the legacy plugin matched them.
     *
     * @var array<string, string>
     */
    private const CUSTOMER_IO_PAGE_EVENTS = [
        'pricing' => 'Viewed Pricing Page',
        'booking' => 'Viewed Booking Page',
        'appointment' => 'Viewed Booking Page',
        'vacalendar' => 'Viewed Booking Page',
    ];

    /**
     * Register WordPress action hooks for tracking.
     */
    public function register(): void
    {
        add_action('wp_head', [$this, 'injectVisitorCookie'], 1);
        add_action('wp_head', [$this, 'injectPostHogSnippet'], 2);
        add_action('wp_head', [$this, 'injectExperimentRuntime'], 3);
        add_action('wp_footer', [$this, 'injectCustomerIOSnippet'], 20);
    }

    /**
     * Set a first-party visitor cookie, `rl_vid`.
     *
     * This is the only identifier that can recognise a returning visitor. `rl_leads.uuid` is
     * minted per row and `session_id` per component mount, so neither survives a second visit;
     * PostHog's distinct id does, but disappears whenever PostHog is blocked — which is
     * disproportionately the traffic worth recognising.
     *
     * Set client-side rather than from PHP so a page served from the CDN still gets one: a
     * `Set-Cookie` on a cached response is either stripped or, worse, cached and handed to
     * every subsequent visitor, which would give thousands of people the same identity.
     *
     * It identifies a browser, not a person, and is treated as a **weak** identifier
     * accordingly — recorded as evidence, never enough on its own to merge two profiles. See
     * `IdentityResolver`.
     */
    public function injectVisitorCookie(): void
    {
        $days = 365;

        echo <<<HTML
<script>
(function () {
  try {
    var name = 'rl_vid';
    var match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
    var id = match ? match[2] : null;

    if (!id) {
      // crypto.randomUUID is unavailable on older Safari and on any non-secure origin.
      id = (window.crypto && window.crypto.randomUUID)
        ? window.crypto.randomUUID()
        : 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }

    // Re-set on every load so the expiry rolls forward for anyone who keeps visiting.
    document.cookie = name + '=' + id
      + '; path=/; max-age=' + (60 * 60 * 24 * {$days})
      + '; SameSite=Lax'
      + (location.protocol === 'https:' ? '; Secure' : '');
  } catch (e) {
    // Cookies disabled. The visitor stays unrecognised, which is the correct outcome.
  }
})();
</script>
HTML;
    }

    /**
     * Inject the PostHog snippet into head.
     *
     * `disable_surveys` stops `surveys.js` (33KB) being fetched. Nothing in this codebase calls
     * `getSurveys`, `renderSurvey` or `getActiveMatchingSurveys`, so it was pure weight. Session
     * recording is deliberately left on — `Lead::posthogReplayUrl()` and the Slack "Watch
     * session" links are built from it.
     *
     * **`array.js` is requested immediately, and that reverses a deliberate decision.** Between
     * 2026-09-18 and 2026-09-22 the fetch was held back to the same flush as `pixels.defer`
     * (interaction, load, idle, or `timeout_ms`), because the fetch and the recording worker are
     * the expensive part of this page and the stub is not.
     *
     * That deferral is incompatible with deciding an A/B test during render. Feature flags arrive
     * with the `/flags/` call `init()` makes, and until the real library lands `window.posthog` is
     * the queueing stub — whose `getFeatureFlag()` returns `undefined` rather than queueing an
     * answer. A queued `capture()` is replayed later and is still correct; a queued flag read is
     * not, because the page needed the answer before it painted. Deferring therefore meant the
     * decision could be six seconds behind the paint.
     *
     * The cost is real and is the price of the feature: every page now pays `array.js` up front
     * instead of after the visitor engages. See `docs/ab-testing.md`, and re-measure against
     * `docs/performance-baseline.md` rather than assuming the old numbers still hold.
     *
     * The script element is still appended from inside this inline block rather than written as a
     * separate tag, because ordering matters: the stub must have set `__SV` and queued the `init`
     * arguments on `_i` before `array.js` runs, or the real library initialises with no config and
     * the stub then overwrites it.
     *
     * In flags-only environments (`services.posthog.flag_environments`) the same library loads
     * with capturing opted out, so an experiment can be rehearsed on staging without writing
     * anything into the production project.
     *
     * This is the only loader of PostHog since 2026-09-18. It used to arrive from the GTM
     * container instead, which meant a script our own conversion path depends on could be
     * changed by whoever owns the container. See `delivered_by_gtm` in `config/pixels.php`.
     */
    public function injectPostHogSnippet(): void
    {
        $apiKey = config('services.posthog.api_key');
        $host = rtrim((string) config('services.posthog.host', 'https://us.i.posthog.com'), '/');

        if (! $apiKey || ! $this->postHogSnippetAllowed()) {
            return;
        }

        // Self-hosted instances do not match, and the replace is then a no-op on the same host.
        $assetHost = str_replace('.i.posthog.com', '-assets.i.posthog.com', $host);

        $options = [
            'api_host' => $host,
            'person_profiles' => 'identified_only',
            'disable_surveys' => true,
            /*
             * The reveal in `injectExperimentRuntime()` falls back to the default variant on this
             * timeout, so it bounds how long a visitor can be looking at the control while PostHog
             * decides. posthog-js defaults to 3000ms, which is far too long to hold a paint.
             */
            'feature_flag_request_timeout_ms' => $this->experimentTimeoutMs(),
        ];

        /*
         * Flags-only suppression is a `before_send` that returns null, NOT
         * `opt_out_capturing_by_default`.
         *
         * `before_send` drops every event — pageview, autocapture, `$feature_flag_called`, and
         * anything the booking wizard sends — at the point posthog-js hands it to the queue, and
         * it provably cannot affect the `/flags/` request, because a flag evaluation is not an
         * event. Opting out is the more obvious switch, but in some posthog-js versions it also
         * suppresses the flags request, which would leave staging with no flags and nothing to
         * rehearse. This picks the mechanism whose blast radius is exactly "events".
         *
         * It is emitted as JS rather than JSON because it is a function.
         */
        $flagsOnly = $this->postHogFlagsOnly();

        if ($flagsOnly) {
            $options['disable_session_recording'] = true;
            $options['autocapture'] = false;
        }

        $key = wp_json_encode((string) $apiKey);
        $options = wp_json_encode($options);
        $suppress = $flagsOnly
            ? 'rlPhOpts.before_send = function () { return null; };'
            : '';

        echo <<<HTML
<!-- PostHog Analytics -->
<script>
!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug getPageViewId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
var rlPhOpts = {$options};
{$suppress}
posthog.init({$key},rlPhOpts);
(function (w, d) {
  if (w.__rlPosthogRequested) return;
  w.__rlPosthogRequested = true;
  var p = d.createElement('script');
  p.type = 'text/javascript';
  p.crossOrigin = 'anonymous';
  p.async = true;
  p.src = '{$assetHost}/static/array.js';
  d.head.appendChild(p);
})(window, document);
</script>
HTML;
    }

    /**
     * Inject Customer.io's CDP (Data Pipelines) snippet in the footer.
     *
     * This is `window.cioanalytics` — the analytics.js-style CDP snippet production serves,
     * verified against https://remoteleverage.com/ on 2026-09-15 (SNIPPET_VERSION 4.15.3).
     * It is also what the legacy payment widget called and what `resources/js/payment-gateway.js`
     * dispatches to, so the client telemetry now lands on the same SDK in every environment.
     *
     * **It takes a CDP write key, not the Track API site id.** Those are different credentials
     * from different Customer.io products: `CUSTOMERIO_SITE_ID` / `CUSTOMERIO_API_KEY` stay
     * server-side for `CustomerIOClient`, which talks to the v1 Track API. Pointing this
     * snippet at a site id would 404 on the CDP asset URL and silently queue every event
     * forever, so the write key is its own variable and its own guard.
     *
     * Two things production's copy does that are deliberately NOT ported:
     *  - an `addSourceMiddleware` that mirrors every event to `POST /wp-json/behavioral/v1/event`.
     *    That is the legacy plugin's unauthenticated write endpoint; v2 records behavioural
     *    events server-side through `RecordBehaviorEventAction` instead.
     *  - a `cio_id` / `cio_form` query-param "identity bridge" for Customer.io-hosted forms.
     *    v2 has no Customer.io-hosted forms; it is a separate feature, not part of the snippet.
     */
    /**
     * The named Customer.io page events the legacy site fired, for the current page.
     *
     * `analytics.page()` alone is not parity. The legacy `rl-customer-io` plugin also fired
     * named `track()` calls — `Viewed Pricing Page` when the slug contained "pricing", and
     * `Viewed Booking Page` when it contained "booking" or "appointment"
     * (`src/Tracking/FrontendTracker.php:39-41` in the 2026-08-27 backup).
     *
     * Those names are not decorative. The plugin seeded a lead-scoring map with them —
     * `Form Submitted` 20, `Viewed Booking Page` 15, `Viewed Pricing Page` 10, `Page Viewed` 1
     * (`src/Database/Migration.php:72-75`) — so a Customer.io score built on them simply stops
     * moving if only the anonymous page call survives.
     *
     * Matched on the request path rather than the post slug, so it holds for a page whose slug
     * and URL have drifted apart.
     */
    protected function customerIoPageEvents(?string $path = null): string
    {
        $path = strtolower($path ?? (string) ($_SERVER['REQUEST_URI'] ?? ''));

        if ($path === '') {
            return '';
        }

        $events = [];

        foreach (self::CUSTOMER_IO_PAGE_EVENTS as $fragment => $event) {
            if (str_contains($path, $fragment)) {
                $events[$event] = true;
            }
        }

        $out = '';

        foreach (array_keys($events) as $event) {
            $out .= 'analytics.track('.wp_json_encode($event).');'."\n";
        }

        return $out;
    }

    public function injectCustomerIOSnippet(): void
    {
        $writeKey = config('services.customer_io.cdp_write_key');

        if (! $writeKey || ! $this->customerIoEnvironmentAllowed()) {
            return;
        }

        // wp_json_encode(), not esc_js(): esc_js() escapes for a quoted HTML *attribute*
        // (it turns " into &quot;), which would corrupt a string literal inside <script>.
        // This emits the quotes itself, so the load() call below has none of its own.
        $writeKey = wp_json_encode((string) $writeKey);

        $pageEvents = $this->customerIoPageEvents();

        echo <<<HTML
<!-- Customer.io CDP -->
<script type="text/javascript">
!function(){var i="cioanalytics",analytics=(window[i]=window[i]||[]);if(!analytics.initialize){if(analytics.invoked){window.console&&console.error&&console.error("Snippet included twice.");}else{analytics.invoked=!0;analytics.methods=["trackSubmit","trackClick","trackLink","trackForm","pageview","identify","reset","group","track","ready","alias","debug","page","once","off","on","addSourceMiddleware","addIntegrationMiddleware","setAnonymousId","addDestinationMiddleware"];analytics.factory=function(e){return function(){var t=Array.prototype.slice.call(arguments);t.unshift(e);analytics.push(t);return analytics}};for(var e=0;e<analytics.methods.length;e++){var key=analytics.methods[e];analytics[key]=analytics.factory(key)}analytics.load=function(key,e){var t=document.createElement("script");t.type="text/javascript";t.async=!0;t.setAttribute("data-global-customerio-analytics-key",i);t.src="https://cdp.customer.io/v1/analytics-js/snippet/"+key+"/analytics.min.js";var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(t,n);analytics._writeKey=key;analytics._loadOptions=e};analytics.SNIPPET_VERSION="4.15.3";
analytics.load({$writeKey});
analytics.page();
{$pageEvents}}}}();
</script>
HTML;
    }

    /**
     * The client-side runtime that decides which variant of an `acf/experiment` block paints.
     *
     * Emitted on every front-end page, including where PostHog is not loaded at all. That is
     * deliberate: the runtime is what makes `?rl_variant=` work locally, and a page with an
     * experiment on it must still resolve correctly when PostHog never arrives.
     *
     * **The default variant renders visible and the others render `hidden`**, rather than hiding
     * everything and revealing the winner. Every failure then lands on a correct page with no
     * timeout involved: JavaScript off, PostHog blocked by an extension, `/flags/` down, an
     * unknown variant name in the markup. Hiding everything first would make all four of those a
     * blank section, and would add a layout shift on the path that works.
     *
     * The consequence is that a visitor assigned to a non-default variant can see the default one
     * first. In practice they usually do not: `resolve()` is called inline immediately after the
     * block's markup, posthog-js restores flags from localStorage during `init()`, and `init()`
     * runs in `<head>` — so for any returning visitor the swap happens while the parser is still
     * in the body, before the first paint. It is the first-ever page view that can flicker, and
     * `experiment_timeout_ms` bounds it.
     *
     * A `?rl_variant=` override deliberately does **not** call `getFeatureFlag()`, so it emits no
     * `$feature_flag_called` and QA traffic cannot contaminate the experiment's results.
     */
    public function injectExperimentRuntime(): void
    {
        if (function_exists('is_admin') && is_admin()) {
            return;
        }

        $timeout = $this->experimentTimeoutMs();

        echo <<<HTML
<!-- Experiments -->
<script>
(function (w, d) {
  if (w.rlExp) return;

  var TIMEOUT = {$timeout};
  var overrides = {};
  var settled = {};
  var waiting = {};

  // ?rl_variant=flag:variant,other_flag:control
  try {
    var raw = new URLSearchParams(w.location.search).get('rl_variant');
    if (raw) {
      raw.split(',').forEach(function (pair) {
        var i = pair.indexOf(':');
        if (i > 0) overrides[pair.slice(0, i).trim()] = pair.slice(i + 1).trim();
      });
    }
  } catch (e) {}

  function nodesFor(flag) {
    var all = d.querySelectorAll('[data-rl-exp]');
    var out = [];
    for (var i = 0; i < all.length; i++) {
      if (all[i].getAttribute('data-rl-exp') === flag) out.push(all[i]);
    }
    return out;
  }

  // PostHog answers a boolean flag with true/false and a multivariate one with a string. The
  // markup only knows variant names, so the booleans get the two conventional ones.
  function normalise(value) {
    if (value === true) return 'test';
    if (value === false) return 'control';
    if (typeof value === 'string') return value;
    return null;
  }

  function decide(flag) {
    if (Object.prototype.hasOwnProperty.call(overrides, flag)) return overrides[flag];
    var ph = w.posthog;
    // `__loaded` is set by array.js, never by the stub. Without it getFeatureFlag() is the
    // stub's queueing no-op, which returns undefined and would read as "no such flag".
    if (ph && ph.__loaded && typeof ph.getFeatureFlag === 'function') {
      try { return normalise(ph.getFeatureFlag(flag)); } catch (e) {}
    }
    return null;
  }

  function apply(flag, variant) {
    var nodes = nodesFor(flag);
    if (!nodes.length) return null;

    var chosen = null, fallback = null, i;
    for (i = 0; i < nodes.length; i++) {
      if (fallback === null && nodes[i].hasAttribute('data-rl-exp-default')) fallback = nodes[i];
      if (chosen === null && variant !== null && nodes[i].getAttribute('data-rl-exp-variant') === variant) chosen = nodes[i];
    }
    // An assignment naming a variant this page does not ship is not an error worth blanking the
    // page over -- PostHog can hold a third arm that was never built here.
    if (chosen === null) chosen = fallback || nodes[0];

    for (i = 0; i < nodes.length; i++) {
      if (nodes[i] === chosen) nodes[i].removeAttribute('hidden');
      else nodes[i].setAttribute('hidden', '');
    }
    return chosen.getAttribute('data-rl-exp-variant');
  }

  function commit(flag, variant) {
    settled[flag] = true;
    var painted = apply(flag, variant);
    w.rlExp.assigned[flag] = painted;

    // So every later event in the session reports which arm the visitor saw. Skipped for an
    // override, which must not look like a real assignment in the data.
    if (painted !== null && !Object.prototype.hasOwnProperty.call(overrides, flag)) {
      var ph = w.posthog;
      if (ph && typeof ph.register === 'function') {
        var props = {};
        props['\$feature/' + flag] = painted;
        try { ph.register(props); } catch (e) {}
      }
    }
  }

  function resolve(flag) {
    if (settled[flag]) return;

    var variant = decide(flag);
    if (variant !== null) { commit(flag, variant); return; }

    if (waiting[flag]) return;
    waiting[flag] = true;

    var ph = w.posthog;
    // Queues on the stub and is replayed once array.js lands, so this is safe before load.
    if (ph && typeof ph.onFeatureFlags === 'function') {
      try { ph.onFeatureFlags(function () { if (!settled[flag]) commit(flag, decide(flag)); }); } catch (e) {}
    }
    w.setTimeout(function () { if (!settled[flag]) commit(flag, decide(flag)); }, TIMEOUT);
  }

  w.rlExp = { resolve: resolve, assigned: {}, overrides: overrides };
})(window, document);
</script>
HTML;
    }

    /**
     * How long the reveal waits for a flag before painting the default variant.
     */
    protected function experimentTimeoutMs(): int
    {
        return max(0, (int) config('services.posthog.experiment_timeout_ms', 1500));
    }

    /**
     * Whether the browser snippet is emitted at all — to capture, or for flags only.
     */
    public function postHogSnippetAllowed(): bool
    {
        return $this->postHogEnvironmentAllowed() || $this->postHogFlagsOnly();
    }

    /**
     * Whether this environment gets flags without capturing.
     *
     * The capture gate wins where both list an environment, so adding production to
     * `POSTHOG_FLAG_ENVIRONMENTS` cannot accidentally silence production.
     */
    public function postHogFlagsOnly(): bool
    {
        if ($this->postHogEnvironmentAllowed()) {
            return false;
        }

        $allowed = (array) config('services.posthog.flag_environments', []);

        if (! function_exists('wp_get_environment_type')) {
            return false;
        }

        return in_array(wp_get_environment_type(), $allowed, true);
    }

    /**
     * Whether this environment loads the PostHog browser snippet.
     *
     * See `services.posthog.environments`. This exists because the `phc_` key now has a
     * default: before that, an unset variable was doing the gating by accident, and the
     * accident was the only thing keeping local and staging traffic out of the production
     * project.
     */
    public function postHogEnvironmentAllowed(): bool
    {
        $allowed = (array) config('services.posthog.environments', ['production']);

        if (! function_exists('wp_get_environment_type')) {
            return in_array('production', $allowed, true);
        }

        return in_array(wp_get_environment_type(), $allowed, true);
    }

    /**
     * Whether this environment loads the Customer.io browser snippet.
     *
     * See `services.customer_io.environments`. Required now that the CDP write
     * key is defaulted — the same accident the PostHog key had.
     */
    public function customerIoEnvironmentAllowed(): bool
    {
        $allowed = (array) config('services.customer_io.environments', ['production']);

        if (! function_exists('wp_get_environment_type')) {
            return in_array('production', $allowed, true);
        }

        return in_array(wp_get_environment_type(), $allowed, true);
    }
}
