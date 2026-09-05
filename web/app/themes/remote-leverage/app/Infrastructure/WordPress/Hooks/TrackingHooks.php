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
        add_action('wp_head', [$this, 'injectPostHogSnippet'], 2);
        add_action('wp_footer', [$this, 'injectCustomerIOSnippet'], 20);
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
     * Inject Customer.io JavaScript snippet in footer.
     */
    public function injectCustomerIOSnippet(): void
    {
        $siteId = config('services.customer_io.site_id');
        if (! $siteId) {
            return;
        }

        echo <<<HTML
<!-- Customer.io Analytics -->
<script type="text/javascript">
var _cio = _cio || [];
(function() {
  var a,b,c; a = function(f) { return function() { _cio.push([f].concat(Array.prototype.slice.call(arguments,0))) } };
  b = ["load","identify","sidentify","track","page"];
  for (c=0; c<b.length; c++) { _cio[b[c]] = a(b[c]); }
  var t = document.createElement('script'),
      s = document.getElementsByTagName('script')[0];
  t.async = true;
  t.id    = 'cio-tracker';
  t.setAttribute('data-site-id', '{$siteId}');
  t.src = 'https://assets.customer.io/assets/track.js';
  s.parentNode.insertBefore(t, s);
})();
</script>
HTML;
    }
}
