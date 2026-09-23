<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

use App\Support\PixelDeferral;

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
 *   4  here           defer bootstrap, then Meta, UET, LinkedIn, OpenAI, TikTok, Google tag
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

        /*
         * Registered before the pixels below, which all sit on the same priority: WordPress
         * runs same-priority callbacks in registration order, so this defines `window.rlDefer`
         * ahead of the first caller. `deferWrap()` only emits a call when this emitted the
         * helper, so the two cannot come apart.
         */
        add_action('wp_head', [$this, 'injectDeferBootstrap'], 4);
        add_action('wp_head', [$this, 'injectMetaPixel'], 4);
        add_action('wp_head', [$this, 'injectBingUet'], 4);
        add_action('wp_head', [$this, 'injectLinkedIn'], 4);
        add_action('wp_head', [$this, 'injectOpenAi'], 4);
        add_action('wp_head', [$this, 'injectTikTok'], 4);
        add_action('wp_head', [$this, 'injectGoogleTag'], 4);
        add_action('wp_head', [$this, 'injectOpenAiConversion'], 5);
        add_action('wp_body_open', [$this, 'injectMetaNoscript'], 2);
        add_action('wp_body_open', [$this, 'injectLinkedInNoscript'], 2);
    }

    /**
     * The vendors actually being deferred, in a stable order.
     *
     * Set on Settings → Marketing Pixels; see `PixelDeferral` for the precedence. Meta and the
     * Google tag were excluded until 2026-09-22: browser conversions already travel server-side
     * (CAPI / GoogleEnhancedConversion), and leaving the SDKs on the critical path is what a PSI
     * mobile run measured as 5.4 s LCP against a 1.2 s FCP.
     *
     * @return array<int, string>
     */
    public function deferredVendors(): array
    {
        return PixelDeferral::vendors();
    }

    /**
     * Whether this vendor's SDK fetch waits for the flush.
     */
    public function isDeferred(string $vendor): bool
    {
        return in_array($vendor, $this->deferredVendors(), true);
    }

    /**
     * The `[prefix, suffix]` that wraps a vendor's SDK insertion, or two empty strings.
     *
     * @return array{0: string, 1: string}
     */
    private function deferWrap(string $vendor): array
    {
        return $this->isDeferred($vendor)
            ? ['window.rlDefer(function(){', '});']
            : ['', ''];
    }

    /**
     * Defines `window.rlDefer`, which holds a callback until the page is done being busy.
     *
     * Flushes on the earliest of: the first real user interaction, `window` load,
     * browser idle, or `PixelDeferral::timeoutMs()`. Interaction is included because
     * an engaged visitor should not wait out the timeout to be tracked — but the
     * interaction listener only *schedules* the flush (setTimeout 0), it does not
     * run the loaders inside the event. Flushing synchronously on pointerdown is
     * how deferred pixels become an INP regression: field INP on the homepage was
     * 500 ms on 2026-09-22 with this running inline. Load is included so a visit
     * that already painted does not sit on the Lighthouse ceiling. Idle alone
     * never arrives on a page that stays busy — which is the page this exists for.
     *
     * The flush also pushes `rl_idle` onto `dataLayer`. That is the hook for deferring a tag
     * that lives in the container rather than here: retrigger it on `rl_idle` instead of
     * `gtm.js`. Emitted unconditionally once anything is deferred, so a container tag can rely
     * on the event existing.
     *
     * Every callback runs inside its own try/catch. One vendor's loader throwing must not take
     * the rest of the queue with it.
     */
    public function injectDeferBootstrap(): void
    {
        if ($this->deferredVendors() === []) {
            return;
        }

        $timeout = PixelDeferral::timeoutMs();

        echo <<<HTML
<!-- Deferred pixel loading (config/pixels.php) -->
<script>
(function (w, d) {
  if (w.rlDefer) return;

  var queue = [], flushed = false, timer = null;
  var EVENTS = ['pointerdown', 'keydown', 'touchstart', 'wheel', 'scroll'];

  function flush() {
    if (flushed) return;
    flushed = true;

    if (timer) w.clearTimeout(timer);

    for (var i = 0; i < EVENTS.length; i++) {
      w.removeEventListener(EVENTS[i], scheduleFlush, true);
    }

    w.removeEventListener('load', flush);

    for (var j = 0; j < queue.length; j++) {
      try { queue[j](); } catch (e) {}
    }

    queue.length = 0;

    // Lets a container tag (TikTok, StatCounter) hang off the same moment.
    w.dataLayer = w.dataLayer || [];
    w.dataLayer.push({ event: 'rl_idle' });
  }

  // Off the INP event. The loaders are the expensive part; running them inside
  // pointerdown is what a 500 ms field INP looks like.
  function scheduleFlush() {
    if (flushed) return;
    w.setTimeout(flush, 0);
  }

  for (var k = 0; k < EVENTS.length; k++) {
    w.addEventListener(EVENTS[k], scheduleFlush, { once: true, passive: true, capture: true });
  }

  w.addEventListener('load', flush);
  timer = w.setTimeout(flush, {$timeout});

  if (typeof w.requestIdleCallback === 'function') {
    w.requestIdleCallback(flush, { timeout: {$timeout} });
  }

  w.rlDefer = function (fn) { flushed ? fn() : queue.push(fn); };

  if (d.readyState === 'complete') {
    flush();
  }
})(window, document);
</script>

HTML;
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

        [$defer, $endDefer] = $this->deferWrap('meta');

        /*
         * Stub and init stay synchronous — `fbq`'s own queue drains when fbevents.js arrives,
         * so a PageView queued here is not lost. Only the SDK insertion waits. The original
         * IIFE returned early when `fbq` already existed (GTM, a second copy of this snippet)
         * and skipped the insert; the flag below preserves that so we do not fetch the SDK
         * twice.
         */
        echo <<<HTML

<!-- Meta Pixel (config/pixels.php) -->
<script>
!function(f,n){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];f.__rlMetaNeedsSdk=1}(window);
{$inits}{$defer}(function(){
  if (!window.__rlMetaNeedsSdk) return;
  window.__rlMetaNeedsSdk=0;
  var t=document.createElement('script');t.async=!0;
  t.src='https://connect.facebook.net/en_US/fbevents.js';
  var s=document.getElementsByTagName('script')[0];
  s.parentNode.insertBefore(t,s);
})();{$endDefer}
</script>
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
        [$defer, $endDefer] = $this->deferWrap('bing_uet');

        /*
         * `uetq` is created before the wrapper rather than inside it, so a `uetq.push()` from
         * anywhere else still lands in the array the loader drains. Deferring the fetch without
         * this would throw on any early push.
         */
        echo <<<HTML
<!-- Microsoft UET (config/pixels.php) -->
<script>
window.uetq = window.uetq || [];
{$defer}(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:"{$id}",enableAutoSpaTracking:true};
o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,n.async=1,
n.onload=n.onreadystatechange=function(){var s=this.readyState;
s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},
i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})
(window,document,"script","//bat.bing.com/bat.js","uetq");{$endDefer}
</script>
<!-- End Microsoft UET -->

HTML;
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
     *
     * **Switched off by default since 2026-09-21** — `linkedInPartnerIds()` returns nothing
     * unless `pixels.linkedin.enabled` is true, and this emits nothing at all rather than a
     * dormant `lintrk` stub. The noscript pixel below reads the same list, so both go quiet
     * together.
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

        [$defer, $endDefer] = $this->deferWrap('linkedin');

        /*
         * Split from one IIFE into stub-then-fetch so the fetch can wait. The partner ids and
         * the `lintrk` queue are still installed synchronously, so a `lintrk('track', ...)`
         * before the SDK arrives queues exactly as it did before.
         */
        echo <<<HTML
<!-- LinkedIn Insight (config/pixels.php) -->
<script>
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
{$pushes}if (!window.lintrk) { window.lintrk = function (a, b) { window.lintrk.q.push([a, b]) }; window.lintrk.q = [] }
{$defer}(function () {
  var s = document.getElementsByTagName('script')[0];
  var b = document.createElement('script');
  b.type = 'text/javascript';
  b.async = true;
  b.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';
  s.parentNode.insertBefore(b, s);
})();{$endDefer}
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
     *
     * **Switched off by default since 2026-09-21** — `openAiPixelIds()` returns nothing unless
     * `pixels.openai.enabled` is true, and this emits nothing at all rather than a dormant `oaiq`
     * stub. `injectOpenAiConversion()` reads the same list, so the conversion cannot fire into a
     * pixel that never loaded.
     *
     * Unrelated to the `OPENAI_API_KEY` behind `/wp-json/jobwidget/v1/*` — see
     * `config/job-widget.php`.
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

        [$defer, $endDefer] = $this->deferWrap('openai');

        /*
         * The `w.oaiq` guard and the queueing stub stay synchronous — `{$inits}` below pushes
         * onto them immediately, and `injectOpenAiConversion()` at priority 5 relies on the stub
         * existing. Only the SDK fetch is wrapped.
         */
        echo <<<HTML
<!-- OpenAI pixel (config/pixels.php) -->
<script>
!function (w, d) {
  if (w.oaiq) return;
  var q = function () { q.q.push(arguments) };
  q.q = [];
  w.oaiq = q;
  {$defer}(function () {
    var j = d.createElement('script');
    j.async = 1;
    j.src = 'https://bzrcdn.openai.com/sdk/oaiq.min.js';
    var f = d.getElementsByTagName('script')[0];
    f.parentNode.insertBefore(j, f);
  })();{$endDefer}
}(window, document);
{$inits}</script>
<!-- End OpenAI pixel -->

HTML;
    }

    /**
     * TikTok's pixel, ported out of the GTM container.
     *
     * The snippet is the container's own, unchanged apart from the split: `ttq` and its method
     * stubs stay synchronous and `ttq.page()` queues on them immediately, while `ttq.load()` —
     * the call that actually inserts `events.js` — is what waits. By the time the SDK executes,
     * `load()` has registered the pixel id and the queued PageView is still there to drain, so
     * deferring costs no event.
     *
     * **Switched off by default since 2026-09-19** — `tikTokPixelIds()` returns nothing unless
     * `pixels.tiktok.enabled` is true, and this emits nothing at all rather than a dormant stub.
     *
     * See `config/pixels.php`: the container tag has to be deleted, or the account gets two
     * PageViews per visit and the bidding optimises against an inflated number.
     */
    public function injectTikTok(): void
    {
        $ids = $this->tikTokPixelIds();

        if ($ids === []) {
            return;
        }

        [$defer, $endDefer] = $this->deferWrap('tiktok');

        $loads = '';

        foreach ($ids as $id) {
            $loads .= "{$defer}ttq.load('".esc_js($id)."');{$endDefer}\n";
        }

        if ((bool) config('pixels.tiktok.track_page_view', true)) {
            $loads .= "ttq.page();\n";
        }

        echo <<<HTML
<!-- TikTok Pixel (config/pixels.php) -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject = t;
  var ttq = w[t] = w[t] || [];
  ttq.methods = "page track identify instances debug on off once ready alias group enableCookie disableCookie holdConsent revokeConsent grantConsent".split(" ");
  ttq.setAndDefer = function (a, b) { a[b] = function () { a.push([b].concat(Array.prototype.slice.call(arguments, 0))) } };
  for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
  ttq.instance = function (a) {
    var b = ttq._i[a] || [];
    for (var c = 0; c < ttq.methods.length; c++) ttq.setAndDefer(b, ttq.methods[c]);
    return b
  };
  ttq.load = function (a, b) {
    var u = "https://analytics.tiktok.com/i18n/pixel/events.js";
    ttq._i = ttq._i || {};
    ttq._i[a] = [];
    ttq._i[a]._u = u;
    ttq._t = ttq._t || {};
    ttq._t[a] = +new Date;
    ttq._o = ttq._o || {};
    ttq._o[a] = b || {};
    var s = d.createElement("script");
    s.type = "text/javascript";
    s.async = !0;
    s.src = u + "?sdkid=" + a + "&lib=" + t;
    var f = d.getElementsByTagName("script")[0];
    f.parentNode.insertBefore(s, f)
  };
}(window, document, 'ttq');
{$loads}</script>
<!-- End TikTok Pixel -->

HTML;
    }

    /**
     * Valid TikTok pixel ids from config, de-duplicated, or none while the pixel is switched off.
     *
     * The `enabled` check lives here rather than in the injector so that there is one answer to
     * "does TikTok fire", and a future caller cannot read the configured ids and act on them while
     * the switch is off. The id stays in config either way — see `pixels.tiktok.enabled`.
     *
     * TikTok's sdkid is an uppercase alphanumeric string; anything else would be interpolated
     * into an inline script, so it is checked rather than trusted.
     *
     * @return array<int, string>
     */
    public function tikTokPixelIds(): array
    {
        if (! (bool) config('pixels.tiktok.enabled', false)) {
            return [];
        }

        return $this->validIds((array) config('pixels.tiktok.pixel_ids', []), '/^[A-Z0-9]{10,}$/i');
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

        /*
         * `accept_incoming` and `url_passthrough` reproduce the container's Conversion Linker
         * (`__gclidw` with cross-domain and URL passthrough enabled), which was retired with
         * GTM-53JDTQCZ. Without them a gclid stops surviving a cross-domain hop and the click
         * that paid for a booking stops being attributable to it.
         */
        $linker = $domains === []
            ? ''
            : 'gtag("set","linker",'.json_encode(
                ['domains' => $domains, 'accept_incoming' => true],
                JSON_UNESCAPED_SLASHES,
            ).');'."\n";

        $configs = '';

        foreach ($ids as $id) {
            $configs .= 'gtag("config", "'.esc_js($id).'", {"url_passthrough": true});'."\n";
        }

        [$defer, $endDefer] = $this->deferWrap('google_tag');

        /*
         * `dataLayer` / `gtag()` stay inline so a conversion queued before the SDK arrives is
         * not lost. Only the gtag/js fetch waits — `async` never meant "free": it defers
         * *fetch*, not evaluation, and GT-NCNQ6N2 was measured at 749 ms blocking.
         */
        echo <<<HTML
<!-- Google tag (config/pixels.php) -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
{$linker}gtag("js", new Date());
{$configs}{$defer}(function(){
  var s=document.createElement('script');
  s.async=true;
  s.src='https://www.googletagmanager.com/gtag/js?id={$primary}';
  var f=document.getElementsByTagName('script')[0];
  f.parentNode.insertBefore(s,f);
})();{$endDefer}
</script>
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
     * Valid LinkedIn partner ids from config, de-duplicated, or none while the tag is off.
     *
     * The `enabled` check lives here for the same reason it does in `tikTokPixelIds()`: one
     * answer to "does LinkedIn fire", which both the head tag and the noscript pixel read. The
     * ids stay in config either way — see `pixels.linkedin.enabled`.
     *
     * @return array<int, string>
     */
    public function linkedInPartnerIds(): array
    {
        if (! (bool) config('pixels.linkedin.enabled', false)) {
            return [];
        }

        return $this->validIds((array) config('pixels.linkedin.partner_ids', []), '/^\d{5,}$/');
    }

    /**
     * Valid OpenAI pixel ids from config, de-duplicated, or none while the pixel is off.
     *
     * Also what switches off `injectOpenAiConversion()`, which already returns early on an empty
     * list — so the conversion cannot outlive the pixel it measures into. See
     * `pixels.openai.enabled`.
     *
     * @return array<int, string>
     */
    public function openAiPixelIds(): array
    {
        if (! (bool) config('pixels.openai.enabled', false)) {
            return [];
        }

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
