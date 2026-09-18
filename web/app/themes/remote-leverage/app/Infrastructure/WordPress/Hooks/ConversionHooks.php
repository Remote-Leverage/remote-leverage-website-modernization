<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

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
 *   click_text:<s>     a click whose element text contains <s>.
 *   click_class:<s>    a click whose element classes contain <s>.
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
        add_action('wp_footer', [$this, 'injectRewardful'], 5);
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
     * Rewardful's referral tracking, ported verbatim.
     *
     * Appended to `body` rather than `head` because that is what its own snippet does, and it is
     * emitted in the footer so the element exists. Deferrable like any other SDK: the `rewardful`
     * queue is installed synchronously and only the fetch waits.
     */
    public function injectRewardful(): void
    {
        $key = trim((string) config('pixels.rewardful.api_key', ''));

        if ($key === '' || ! preg_match('/^[a-z0-9]{4,}$/i', $key)) {
            return;
        }

        $id = esc_js($key);
        [$defer, $endDefer] = $this->deferWrap('rewardful');

        echo <<<HTML
<!-- Rewardful (config/pixels.php) -->
<script>
(function (w, r) { w._rwq = r; w[r] = w[r] || function () { (w[r].q = w[r].q || []).push(arguments) } })(window, 'rewardful');
{$defer}(function () {
  var s = document.createElement('script');
  s.async = true;
  s.setAttribute('src', 'https://r.wdfl.co/rw.js');
  s.setAttribute('data-rewardful', '{$id}');
  document.body.appendChild(s);
})();{$endDefer}
</script>
<!-- End Rewardful -->

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

        $calls = '';

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
    var text = (clickable.textContent || '').replace(/\s+/g, ' ').trim();
    var classes = String(clickable.className && clickable.className.baseVal !== undefined
      ? clickable.className.baseVal
      : (clickable.className || ''));

    matching('click_text:', function (needle) { return text.indexOf(needle) !== -1; });
    matching('click_class:', function (needle) { return classes.indexOf(needle) !== -1; });
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
