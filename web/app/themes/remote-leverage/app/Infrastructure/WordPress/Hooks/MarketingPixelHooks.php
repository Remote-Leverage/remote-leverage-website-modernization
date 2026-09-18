<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

/**
 * Emits the marketing pixels that production loads outside GTM.
 *
 * See `config/pixels.php` for what each one is and why it is not simply a tag in the container.
 * The short version: they are hardcoded into the Elementor-era pages, so nothing carries them
 * across the cutover, and the largest of them covers two thirds of paid acquisition.
 *
 * Ordering, on `wp_head`:
 *
 *   1  TrackingHooks  visitor cookie
 *   2  TrackingHooks  PostHog
 *   3  SiteKitHooks   GTM containers
 *   4  here           Meta, UET, HubSpot, LinkedIn, OpenAI, Google tag
 *
 * Last deliberately. A pixel here is a fallback for something the container does not carry, so
 * if a tag inside GTM ever starts doing the same job it wins the race to define `fbq` and this
 * one becomes the duplicate rather than the other way round — which is the direction that shows
 * up in GTM's own preview mode, where someone will actually notice it.
 */
class MarketingPixelHooks
{
    public function register(): void
    {
        if (! $this->environmentAllowed()) {
            return;
        }

        add_action('wp_head', [$this, 'injectMetaPixel'], 4);
        add_action('wp_head', [$this, 'injectBingUet'], 4);
        add_action('wp_head', [$this, 'injectHubSpot'], 4);
        add_action('wp_head', [$this, 'injectLinkedIn'], 4);
        add_action('wp_head', [$this, 'injectOpenAi'], 4);
        add_action('wp_head', [$this, 'injectGoogleTag'], 4);
        add_action('wp_head', [$this, 'injectOpenAiConversion'], 5);
        add_action('wp_body_open', [$this, 'injectMetaNoscript'], 2);
        add_action('wp_body_open', [$this, 'injectLinkedInNoscript'], 2);
    }

    /**
     * Meta's pixel, initialised once per configured id.
     *
     * The loader is idempotent by its own first line (`if (f.fbq) return`), so several ids share
     * one SDK and each gets its own `init`. That is Meta's documented way to run more than one,
     * and it is what production does.
     */
    public function injectMetaPixel(): void
    {
        $ids = $this->metaPixelIds();

        if ($ids === []) {
            return;
        }

        $inits = '';

        foreach ($ids as $id) {
            $inits .= "fbq('init', '".esc_js($id)."');\n";
        }

        if ((bool) config('pixels.meta.track_page_view', true)) {
            $inits .= "fbq('track', 'PageView');\n";
        }

        echo <<<HTML

<!-- Meta Pixel (config/pixels.php) -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window,document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
{$inits}</script>
<!-- End Meta Pixel -->

HTML;
    }

    /**
     * Meta's no-JavaScript fallback, one image per pixel id.
     */
    public function injectMetaNoscript(): void
    {
        foreach ($this->metaPixelIds() as $id) {
            printf(
                '<noscript><img height="1" width="1" style="display:none" alt="" src="%s"></noscript>'."\n",
                esc_url('https://www.facebook.com/tr?id='.rawurlencode($id).'&ev=PageView&noscript=1'),
            );
        }
    }

    /**
     * Microsoft Advertising's UET tag.
     *
     * This is what stamps `msclkid`, which the lead export carries and the attribution blob
     * stores. Without it a Bing-sourced booking still arrives, it just stops being attributable
     * to the click that paid for it.
     */
    public function injectBingUet(): void
    {
        $tagId = trim((string) config('pixels.bing_uet.tag_id', ''));

        if ($tagId === '' || ! preg_match('/^\d{6,}$/', $tagId)) {
            return;
        }

        $id = esc_js($tagId);

        echo <<<HTML
<!-- Microsoft UET (config/pixels.php) -->
<script>
(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:"{$id}",enableAutoSpaTracking:true};
o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,n.async=1,
n.onload=n.onreadystatechange=function(){var s=this.readyState;
s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},
i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})
(window,document,"script","//bat.bing.com/bat.js","uetq");
</script>
<!-- End Microsoft UET -->

HTML;
    }

    /**
     * HubSpot's browser tracking code.
     *
     * Distinct from the server-side CRM sync in `HubSpotGateway`: that creates and updates the
     * contact, this attaches the page-view history to it. A contact created by the API without
     * this arrives in HubSpot with no idea which pages sold them.
     */
    public function injectHubSpot(): void
    {
        $portalId = trim((string) config('pixels.hubspot.portal_id', ''));
        $region = trim((string) config('pixels.hubspot.region', 'na2'));

        if ($portalId === '' || ! preg_match('/^\d{5,}$/', $portalId) || ! preg_match('/^[a-z0-9]{2,6}$/i', $region)) {
            return;
        }

        printf(
            '<script id="hs-script-loader" async defer src="%s"></script>'."\n",
            esc_url("https://js-{$region}.hs-scripts.com/{$portalId}.js"),
        );
    }

    /**
     * LinkedIn's Insight Tag.
     *
     * Only the partner id production hardcodes into the page. The container carries a second,
     * different one (`9514236`) and both have been firing for some time; which is the live ad
     * account could not be determined from outside, so neither is dropped. See
     * `config/pixels.php`.
     *
     * `_linkedin_data_partner_ids` is an array by design, so this and the container's tag
     * coexist on one SDK load and each id is reported. That is already how production behaves.
     */
    public function injectLinkedIn(): void
    {
        $ids = $this->linkedInPartnerIds();

        if ($ids === []) {
            return;
        }

        $pushes = '';

        foreach ($ids as $id) {
            $pushes .= "window._linkedin_data_partner_ids.push('".esc_js($id)."');\n";
        }

        echo <<<HTML
<!-- LinkedIn Insight (config/pixels.php) -->
<script>
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
{$pushes}(function (l) {
  if (!l) { window.lintrk = function (a, b) { window.lintrk.q.push([a, b]) }; window.lintrk.q = [] }
  var s = document.getElementsByTagName('script')[0];
  var b = document.createElement('script');
  b.type = 'text/javascript';
  b.async = true;
  b.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
  s.parentNode.insertBefore(b, s);
})(window.lintrk);
</script>
<!-- End LinkedIn Insight -->

HTML;
    }

    /**
     * LinkedIn's no-JavaScript fallback, one per partner id.
     */
    public function injectLinkedInNoscript(): void
    {
        foreach ($this->linkedInPartnerIds() as $id) {
            printf(
                '<noscript><img height="1" width="1" style="display:none" alt="" src="%s"></noscript>'."\n",
                esc_url('https://px.ads.linkedin.com/collect/?pid='.rawurlencode($id).'&fmt=gif'),
            );
        }
    }

    /**
     * OpenAI's pixel.
     *
     * Same split as LinkedIn: this emits the page-hardcoded id, the container emits a different
     * one. The loader guards itself with `if (w.oaiq) return`, so whichever runs first installs
     * the SDK and every `init` afterwards still registers — which is how production ends up
     * reporting to both.
     */
    public function injectOpenAi(): void
    {
        $ids = $this->openAiPixelIds();

        if ($ids === []) {
            return;
        }

        $debug = (bool) config('pixels.openai.debug', false) ? 'true' : 'false';
        $inits = '';

        foreach ($ids as $id) {
            $inits .= "oaiq('init', {pixelId: '".esc_js($id)."', debug: {$debug}});\n";
        }

        echo <<<HTML
<!-- OpenAI pixel (config/pixels.php) -->
<script>
!function (w, d, s, u) {
  if (w.oaiq) return;
  var q = function () { q.q.push(arguments) };
  q.q = [];
  w.oaiq = q;
  var j = d.createElement(s);
  j.async = 1;
  j.src = u;
  var f = d.getElementsByTagName(s)[0];
  f.parentNode.insertBefore(j, f);
}(window, document, 'script', 'https://bzrcdn.openai.com/sdk/oaiq.min.js');
{$inits}</script>
<!-- End OpenAI pixel -->

HTML;
    }

    /**
     * The Google tag (gtag.js), as production's Site Kit loads it.
     *
     * Not a duplicate of the container's Google tag: that one is `AW-11406183013`, this one is
     * `GT-NCNQ6N2`, and resolving its payload shows it also routes to `G-JFBLS33ET8` — a GA4
     * property reached through no other path on the site. Dropping it loses that property
     * entirely. See `config/pixels.php`.
     */
    public function injectGoogleTag(): void
    {
        $ids = $this->validIds((array) config('pixels.google_tag.ids', []), '/^(?:GT|G|AW)-[A-Z0-9-]{6,}$/i');

        if ($ids === []) {
            return;
        }

        $primary = esc_js($ids[0]);
        $domains = array_values(array_filter(array_map(
            static fn ($d): string => trim((string) $d),
            (array) config('pixels.google_tag.linker_domains', []),
        )));

        $linker = $domains === []
            ? ''
            : 'gtag("set","linker",'.json_encode(['domains' => $domains], JSON_UNESCAPED_SLASHES).');'."\n";

        $configs = '';

        foreach ($ids as $id) {
            $configs .= 'gtag("config", "'.esc_js($id).'");'."\n";
        }

        echo <<<HTML
<!-- Google tag (config/pixels.php) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$primary}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
{$linker}gtag("js", new Date());
{$configs}</script>
<!-- End Google tag -->

HTML;
    }

    /**
     * The OpenAI conversion for this page, if it is one.
     *
     * Priority 5, after `injectOpenAi()` at 4, so `oaiq` is already the queueing stub by the
     * time this pushes onto it.
     *
     * The legacy form fired this from `gform_confirmation_loaded`; v2's equivalent moment is the
     * thank-you page load. It is in neither GTM container, so without this the OpenAI pixel
     * reports page views and no conversions at all. See `config/pixels.php`.
     */
    public function injectOpenAiConversion(): void
    {
        if ($this->openAiPixelIds() === []) {
            return;
        }

        $event = $this->openAiConversionForRequest();

        if ($event === null) {
            return;
        }

        printf(
            '<script>window.oaiq && oaiq("measure", "%s", {type: "customer_action"});</script>'."\n",
            esc_js($event),
        );
    }

    /**
     * The conversion event configured for the current path, or null.
     *
     * Case-insensitive, deliberately: the GTM trigger's case sensitivity is a trap this codebase
     * already had to work around once (see MultistepBookingWizard's redirect), and there is no
     * reason to reproduce it in our own comparison.
     */
    public function openAiConversionForRequest(?string $path = null): ?string
    {
        $path = $path ?? (string) ($_SERVER['REQUEST_URI'] ?? '');

        if ($path === '') {
            return null;
        }

        foreach ((array) config('pixels.openai.conversions', []) as $fragment => $event) {
            $fragment = trim((string) $fragment);

            if ($fragment !== '' && stripos($path, $fragment) !== false) {
                return (string) $event;
            }
        }

        return null;
    }

    /**
     * Valid LinkedIn partner ids from config, de-duplicated.
     *
     * @return array<int, string>
     */
    public function linkedInPartnerIds(): array
    {
        return $this->validIds((array) config('pixels.linkedin.partner_ids', []), '/^\d{5,}$/');
    }

    /**
     * Valid OpenAI pixel ids from config, de-duplicated.
     *
     * @return array<int, string>
     */
    public function openAiPixelIds(): array
    {
        return $this->validIds((array) config('pixels.openai.pixel_ids', []), '/^[A-Za-z0-9]{10,}$/');
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    private function validIds(array $ids, string $pattern): array
    {
        $valid = array_filter(
            array_map(static fn ($id): string => trim((string) $id), $ids),
            static fn (string $id): bool => (bool) preg_match($pattern, $id),
        );

        return array_values(array_unique($valid));
    }

    /**
     * Valid Meta pixel ids from config, de-duplicated.
     *
     * Numeric-only: the id is interpolated into an inline script, and a duplicate would fire two
     * PageViews into the same ad account and inflate exactly the number the bidding optimises
     * against.
     *
     * @return array<int, string>
     */
    public function metaPixelIds(): array
    {
        $ids = (array) config('pixels.meta.pixel_ids', []);

        $valid = array_filter(
            array_map(static fn ($id): string => trim((string) $id), $ids),
            static fn (string $id): bool => (bool) preg_match('/^\d{10,}$/', $id),
        );

        return array_values(array_unique($valid));
    }

    /**
     * Whether this environment loads the pixels. See `config/pixels.php`.
     */
    public function environmentAllowed(): bool
    {
        $allowed = (array) config('pixels.environments', ['production']);

        if (! function_exists('wp_get_environment_type')) {
            return in_array('production', $allowed, true);
        }

        return in_array(wp_get_environment_type(), $allowed, true);
    }
}
