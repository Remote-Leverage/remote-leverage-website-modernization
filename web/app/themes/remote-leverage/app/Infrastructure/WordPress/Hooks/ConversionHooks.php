<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

use App\Domains\Tracking\Support\GoogleEnhancedConversion;

/**
 * The conversion and event tags that used to live in `GTM-53JDTQCZ`.
 *
 * Transcribed tag by tag off the published container on 2026-09-18, before it was retired. See
 * `config/pixels.php` for the spec; the conversion labels there are the part that cannot be
 * reconstructed if lost, because a wrong label reports into nothing and the only symptom is a
 * campaign that quietly looks unprofitable.
 *
 * Four trigger kinds, matching what the container actually used:
 *
 *   path:<fragment>    rendered inline on the matching page, server-side. The container did this
 *                      with a `Page Path contains` predicate evaluated in the browser; doing it
 *                      in PHP means it cannot be missed by a script that failed to run.
 *   dl:<event>         a `dataLayer` push whose `event` is <event>. This is what GTM's Custom
 *                      Event triggers watched, and the wizard already publishes to it --
 *                      `MultistepBookingWizard::pushToDataLayer()` pushes `form_submit`
 *                      alongside `partial_form_submitted` on a successful capture. An earlier
 *                      revision of this class listened for a native `submit` instead, which a
 *                      Livewire form never fires, so `generate_lead` and its Ads conversion
 *                      would have reported nothing.
 *   click_text:<s>     a click whose element text contains <s>, case-insensitively.
 *   click_class:<s>    a click whose element classes contain <s>, case-insensitively.
 *
 * A native `<video>` play is turned into a `video_play` dataLayer push by the runtime below, so
 * video events are configured as `dl:video_play` like everything else. The two players that are
 * not `<video>` elements -- the testimonials modal and the case-study Vimeo embed -- push it
 * themselves.
 *
 * Ordering, on `wp_head`:
 *
 *   3  here            consent defaults -- ahead of the Google tag at 4, which reads them
 *   4  MarketingPixel  the pixels, including gtag
 *
 * and the wiring itself on `wp_footer`, where the DOM exists and `gtag` is already defined.
 */
class ConversionHooks
{
    public function register(): void
    {
        if (! $this->environmentAllowed()) {
            return;
        }

        add_action('wp_head', [$this, 'injectConsentDefaults'], 3);
        add_action('wp_footer', [$this, 'injectConversions'], 6);
    }

    /**
     * Google's consent defaults, as the container's "Consent - Default Granted" tag set them.
     *
     * Must precede the Google tag, which is why this sits at priority 3. Not a consent manager:
     * it is the state Google's tags assume before any CMP speaks. A real CMP would have to run
     * ahead of this and set these itself.
     */
    public function injectConsentDefaults(): void
    {
        if (! (bool) config('pixels.consent.enabled', true)) {
            return;
        }

        $defaults = (array) config('pixels.consent.defaults', []);

        if ($defaults === []) {
            return;
        }

        $json = wp_json_encode($defaults, JSON_UNESCAPED_SLASHES);

        echo <<<HTML
<!-- Consent defaults (config/pixels.php) -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {$json});
</script>

HTML;
    }

    /**
     * Everything the container fired on a trigger.
     *
     * Path-matched actions are emitted as direct calls, because this request already knows
     * whether it is that page. The rest become a small table the runtime below dispatches.
     */
    public function injectConversions(): void
    {
        $immediate = [];
        $wired = [];

        foreach ($this->actions() as $action) {
            $trigger = $action['trigger'];

            if (str_starts_with($trigger, 'path:')) {
                if ($this->pathMatches(substr($trigger, 5))) {
                    $immediate[] = $action['call'];
                }

                continue;
            }

            $wired[] = ['trigger' => $trigger, 'call' => $action['call']];
        }

        if ($immediate === [] && $wired === []) {
            return;
        }

        /*
         * Enhanced conversions, for the one page that knows which lead it is confirming.
         *
         * Only the path-matched calls get this. The wired table fires on clicks and dataLayer
         * pushes anywhere on the site, where there is no lead and a `transaction_id` from a
         * previous booking still sitting in the session would be flatly wrong.
         *
         * **The gate is what keeps the HTML cache alive.** Reading the session opens one, and an
         * open session means a `Set-Cookie` on the response — which `docker/nginx.conf` treats,
         * correctly, as "never cache this". Calling it unconditionally from `wp_footer` would
         * therefore have turned every page on the site into `private, no-store`. Only a page
         * that already has a gtag conversion to decorate touches the session, which today is
         * `/VAThankYou/` alone, and that page is excluded from the cache anyway.
         */
        $enhanced = $this->hasGtagConversion($immediate) ? $this->enhancedConversion() : null;

        if ($enhanced !== null) {
            foreach ($immediate as $i => $call) {
                if (($call['vendor'] ?? '') === 'gtag' && ($call['name'] ?? '') === 'conversion') {
                    $immediate[$i]['params']['transaction_id'] = $enhanced['transaction_id'];
                }
            }
        }

        $calls = '';

        /*
         * `set` before `event`: gtag applies user data to conversions sent after it, so emitting
         * this below the sends would attach it to nothing.
         */
        if (($enhanced['user_data'] ?? []) !== []) {
            $userData = wp_json_encode($enhanced['user_data'], JSON_UNESCAPED_SLASHES);
            $calls .= '  if (typeof w.gtag === "function") w.gtag("set", "user_data", '.$userData.");\n";
        }

        foreach ($immediate as $call) {
            $calls .= '  '.$this->renderCall($call)."\n";
        }

        $table = wp_json_encode($wired, JSON_UNESCAPED_SLASHES);

        echo <<<HTML
<!-- Conversions, ported from GTM-53JDTQCZ (config/pixels.php) -->
<script>
(function (w, d) {
  function send(call) {
    try {
      if (call.vendor === 'posthog') {
        if (w.posthog && typeof w.posthog.capture === 'function') w.posthog.capture(call.name, call.params || {});
        return;
      }
      if (typeof w.gtag === 'function') w.gtag('event', call.name, call.params || {});
    } catch (e) {}
  }

{$calls}
  var WIRED = {$table};

  if (!WIRED.length) return;

  function matching(kind, test) {
    for (var i = 0; i < WIRED.length; i++) {
      var t = WIRED[i].trigger;
      if (t.indexOf(kind) === 0 && test(t.slice(kind.length))) send(WIRED[i].call);
    }
  }

  /*
   * dataLayer is the bus GTM's Custom Event triggers listened on, and the booking wizard
   * already publishes to it. Existing entries are replayed first: a push that happened before
   * this script parsed would otherwise be missed.
   */
  w.dataLayer = w.dataLayer || [];

  function fromDataLayer(entry) {
    if (!entry || typeof entry !== 'object' || !entry.event) return;
    matching('dl:', function (name) { return name === entry.event; });
  }

  for (var n = 0; n < w.dataLayer.length; n++) fromDataLayer(w.dataLayer[n]);

  var nativePush = w.dataLayer.push;

  w.dataLayer.push = function () {
    var result = nativePush.apply(this, arguments);

    for (var i = 0; i < arguments.length; i++) fromDataLayer(arguments[i]);

    return result;
  };

  /*
   * `play` does not bubble, so this listens in the capture phase. Covers every `<video>` on the
   * site -- the sample-applicant grid and the media-copy player -- without either block needing
   * to know this exists.
   */
  d.addEventListener('play', function (e) {
    var el = e.target;

    if (!el || el.tagName !== 'VIDEO') return;

    w.dataLayer.push({ event: 'video_play', video_src: el.currentSrc || el.src || '' });
  }, true);

  d.addEventListener('click', function (e) {
    var el = e.target;
    if (!el || el.nodeType !== 1) return;

    // GTM's Click Text is the text of the clicked element; a click usually lands on a child,
    // so the nearest clickable ancestor is what the trigger meant.
    var clickable = el.closest ? (el.closest('a,button,[role="button"]') || el) : el;
    // Lower-cased on both sides: the CTA copy is authored as `BOOK A CONSULTATION` in
    // HomeHeroBlock, AboutHeroBlock, RolePages and several patterns, so a case-sensitive
    // `indexOf` matched only the title-case half of the buttons and the Ads conversion
    // `oEXvCN-QnpcbEOWU8r4q` under-reported for every uppercase one. Same reasoning as
    // `pathMatches()` and `openai.conversions` — no reason to inherit GTM's case-sensitive
    // `_cn` predicate a third time.
    var text = (clickable.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    var classes = String(clickable.className && clickable.className.baseVal !== undefined
      ? clickable.className.baseVal
      : (clickable.className || '')).toLowerCase();

    matching('click_text:', function (needle) { return text.indexOf(needle.toLowerCase()) !== -1; });
    matching('click_class:', function (needle) { return classes.indexOf(needle.toLowerCase()) !== -1; });
  }, true);
})(window, document);
</script>

HTML;
    }

    /**
     * Every configured action, flattened to `{trigger, call}`.
     *
     * @return array<int, array{trigger: string, call: array<string, mixed>}>
     */
    public function actions(): array
    {
        $out = [];

        $conversionId = trim((string) config('pixels.google_ads.conversion_id', ''));

        if ($conversionId !== '' && preg_match('/^AW-[0-9]{6,}$/i', $conversionId)) {
            foreach ((array) config('pixels.google_ads.conversions', []) as $c) {
                $label = trim((string) ($c['label'] ?? ''));
                $trigger = trim((string) ($c['trigger'] ?? ''));

                if ($label === '' || $trigger === '' || ! preg_match('/^[A-Za-z0-9_-]{8,}$/', $label)) {
                    continue;
                }

                $params = ['send_to' => $conversionId.'/'.$label];

                if (($c['value'] ?? null) !== null) {
                    $params['value'] = $c['value'];
                    $params['currency'] = (string) ($c['currency'] ?? 'USD');
                }

                $out[] = ['trigger' => $trigger, 'call' => ['vendor' => 'gtag', 'name' => 'conversion', 'params' => $params]];
            }
        }

        $measurementId = trim((string) config('pixels.ga4.measurement_id', ''));

        if ($measurementId !== '' && preg_match('/^G-[A-Z0-9]{6,}$/i', $measurementId)) {
            foreach ((array) config('pixels.ga4.events', []) as $e) {
                $name = trim((string) ($e['name'] ?? ''));
                $trigger = trim((string) ($e['trigger'] ?? ''));

                if ($name === '' || $trigger === '') {
                    continue;
                }

                $out[] = ['trigger' => $trigger, 'call' => [
                    'vendor' => 'gtag', 'name' => $name, 'params' => ['send_to' => $measurementId],
                ]];
            }
        }

        foreach ((array) config('pixels.posthog_events', []) as $e) {
            $name = trim((string) ($e['name'] ?? ''));
            $trigger = trim((string) ($e['trigger'] ?? ''));

            if ($name === '' || $trigger === '') {
                continue;
            }

            $out[] = ['trigger' => $trigger, 'call' => [
                'vendor' => 'posthog', 'name' => $name, 'params' => ['page_path' => $this->currentPath()],
            ]];
        }

        return $out;
    }

    /**
     * Whether any of these calls is a Google Ads conversion, and so has somewhere to put a
     * `transaction_id`.
     *
     * @param  array<int, array<string, mixed>>  $calls
     */
    private function hasGtagConversion(array $calls): bool
    {
        foreach ($calls as $call) {
            if (($call['vendor'] ?? '') === 'gtag' && ($call['name'] ?? '') === 'conversion') {
                return true;
            }
        }

        return false;
    }

    /**
     * The enhanced-conversion payload the booking wizard left in the session, if this request is
     * the thank-you page load that followed a booking.
     *
     * Read rather than pulled, so a reload of `/VAThankYou/` re-sends the same `transaction_id`
     * and Google collapses the two into one conversion instead of counting both.
     *
     * Returns null on every other page, on a direct visit that never booked, and in any context
     * without a session — which keeps this a no-op for the WIRED table and for tests that do not
     * boot one.
     *
     * @return array{transaction_id: string, user_data: array<string, mixed>}|null
     */
    public function enhancedConversion(): ?array
    {
        if (! function_exists('session')) {
            return null;
        }

        try {
            $payload = session()->get(GoogleEnhancedConversion::SESSION_KEY);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload) || ($payload['transaction_id'] ?? '') === '') {
            return null;
        }

        return [
            'transaction_id' => (string) $payload['transaction_id'],
            'user_data' => (array) ($payload['user_data'] ?? []),
        ];
    }

    /**
     * One immediate call, as JavaScript.
     *
     * @param  array<string, mixed>  $call
     */
    private function renderCall(array $call): string
    {
        return 'send('.wp_json_encode($call, JSON_UNESCAPED_SLASHES).');';
    }

    /**
     * Whether the current request path contains this fragment.
     *
     * Case-insensitive, unlike the container's predicate. `/VAThankYou/` is what the wizard
     * redirects to and the capitals have always matched, but a lowercase variant serves the
     * identical page and would silently match nothing. See MultistepBookingWizard.
     */
    public function pathMatches(string $fragment): bool
    {
        $fragment = trim($fragment);

        return $fragment !== '' && stripos($this->currentPath(), $fragment) !== false;
    }

    /**
     * The current request path, without its query string.
     */
    private function currentPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return (string) (parse_url($uri, PHP_URL_PATH) ?? $uri);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function deferWrap(string $vendor): array
    {
        $configured = array_map(
            static fn ($v): string => strtolower(trim((string) $v)),
            (array) config('pixels.defer.vendors', []),
        );

        return in_array($vendor, $configured, true)
            ? ['window.rlDefer(function(){', '});']
            : ['', ''];
    }

    /**
     * Whether this environment fires conversions. Shares `pixels.environments` with the pixels
     * themselves, so one switch still moves the whole tracking surface together.
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
