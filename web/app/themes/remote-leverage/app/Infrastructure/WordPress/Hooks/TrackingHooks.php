<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

class TrackingHooks
{
    /**
     * Register WordPress action hooks for tracking.
     */
    public function register(): void
    {
        add_action('wp_head', [$this, 'injectVisitorCookie'], 1);
        add_action('wp_head', [$this, 'injectPostHogSnippet'], 2);
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
     * Inject lightweight PostHog snippet into head.
     */
    public function injectPostHogSnippet(): void
    {
        $apiKey = config('services.posthog.api_key');
        $host = config('services.posthog.host', 'https://us.i.posthog.com');

        if (! $apiKey) {
            return;
        }

        echo <<<HTML
<!-- PostHog Analytics -->
<script>
!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug getPageViewId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
posthog.init('{$apiKey}',{api_host:'{$host}',person_profiles:'identified_only'});
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
    public function injectCustomerIOSnippet(): void
    {
        $writeKey = config('services.customer_io.cdp_write_key');

        if (! $writeKey) {
            return;
        }

        // wp_json_encode(), not esc_js(): esc_js() escapes for a quoted HTML *attribute*
        // (it turns " into &quot;), which would corrupt a string literal inside <script>.
        // This emits the quotes itself, so the load() call below has none of its own.
        $writeKey = wp_json_encode((string) $writeKey);

        echo <<<HTML
<!-- Customer.io CDP -->
<script type="text/javascript">
!function(){var i="cioanalytics",analytics=(window[i]=window[i]||[]);if(!analytics.initialize){if(analytics.invoked){window.console&&console.error&&console.error("Snippet included twice.");}else{analytics.invoked=!0;analytics.methods=["trackSubmit","trackClick","trackLink","trackForm","pageview","identify","reset","group","track","ready","alias","debug","page","once","off","on","addSourceMiddleware","addIntegrationMiddleware","setAnonymousId","addDestinationMiddleware"];analytics.factory=function(e){return function(){var t=Array.prototype.slice.call(arguments);t.unshift(e);analytics.push(t);return analytics}};for(var e=0;e<analytics.methods.length;e++){var key=analytics.methods[e];analytics[key]=analytics.factory(key)}analytics.load=function(key,e){var t=document.createElement("script");t.type="text/javascript";t.async=!0;t.setAttribute("data-global-customerio-analytics-key",i);t.src="https://cdp.customer.io/v1/analytics-js/snippet/"+key+"/analytics.min.js";var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(t,n);analytics._writeKey=key;analytics._loadOptions=e};analytics.SNIPPET_VERSION="4.15.3";
analytics.load({$writeKey});
analytics.page();
}}}();
</script>
HTML;
    }
}
