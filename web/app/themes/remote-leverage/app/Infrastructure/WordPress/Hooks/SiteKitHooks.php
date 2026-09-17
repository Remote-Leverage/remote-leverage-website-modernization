<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

/**
 * Serves Google Site Kit its configuration from `config/site-kit.php`, and delivers the GTM
 * containers itself.
 *
 * ## Why the theme emits the snippet rather than Site Kit
 *
 * Production serves **two** containers, `GTM-53JDTQCZ` and `GTM-P4KZNJWL`
 * (`docs/cutover-decisions.md` decision 32), and Site Kit can only hold one: its
 * `Modules\Tag_Manager::register_tag()` builds `new Web_Tag($settings['containerID'])` from a
 * single value, and `ampContainerID` is a separate AMP-only render path rather than a second
 * container on the same page. Verified against Site Kit 1.187.0.
 *
 * Connecting Site Kit to one of them would silently stop every tag in the other — LinkedIn
 * Insight, Meta Pixel and the rest — which is exactly the ad-spend parity decision 32 exists to
 * protect. So delivery lives here, where the container list is just a list.
 *
 * Site Kit stays installed and is still configured from the same file, so its dashboards work
 * if anyone connects it. It is simply told, through `useSnippet`, not to render a tag.
 *
 * ## Why the settings are filtered rather than written
 *
 * `config/wordfence.php` is applied by writing to the database on deploy. This does not, because
 * Site Kit reads every setting through `get_option()` and the failure mode here is worse: a
 * deploy-time write leaves a window in which anyone who connects Tag Manager in wp-admin gets a
 * second GTM snippet on every page — duplicate pageviews, duplicate conversions — and nothing
 * corrects it until the next release. Filtered at read time, the config file always wins.
 */
class SiteKitHooks
{
    /** Site Kit's option holding the Tag Manager module settings. */
    private const SETTINGS_OPTION = 'googlesitekit_tagmanager_settings';

    /** Site Kit's option listing the modules it will register. */
    private const ACTIVE_MODULES_OPTION = 'googlesitekit_active_modules';

    /**
     * A well-formed GTM container id.
     *
     * Checked rather than trusted: these values reach an inline `<script>`, and a malformed one
     * is either an injection point or a container that silently never loads. Both are better
     * caught by a list that comes up empty than by reading the page source months later.
     */
    private const CONTAINER_PATTERN = '/^GTM-[A-Z0-9]{4,}$/i';

    public function register(): void
    {
        add_filter('pre_option_'.self::SETTINGS_OPTION, [$this, 'filterTagManagerSettings']);

        if ((bool) config('site-kit.force_module_active', false)) {
            add_filter('pre_option_'.self::ACTIVE_MODULES_OPTION, [$this, 'filterActiveModules']);
        }

        if (! $this->shouldEmit()) {
            return;
        }

        /*
         * Priority 3 puts the containers after TrackingHooks' visitor cookie (1) and PostHog
         * snippet (2). The cookie one matters: `rl_vid` is written by an inline script at
         * priority 1, so any GTM tag that wants to read it is guaranteed to find it rather than
         * racing the script that sets it.
         */
        add_action('wp_head', [$this, 'injectContainerSnippets'], 3);
        add_action('wp_body_open', [$this, 'injectNoscriptFallbacks'], 1);
    }

    /**
     * Site Kit's Tag Manager settings, from config rather than from the database.
     *
     * Returning a non-null value from `pre_option_*` short-circuits `get_option()` entirely, so
     * whatever wp-admin last wrote is never consulted.
     *
     * @return array<string, mixed>
     */
    public function filterTagManagerSettings(): array
    {
        $configured = (array) config('site-kit.tagmanager', []);
        $containers = $this->containers();

        return array_merge([
            'ownerID' => 0,
            'accountID' => '',
            'containerID' => '',
            'ampContainerID' => '',
            'internalContainerID' => '',
            'internalAMPContainerID' => '',
        ], $configured, [
            /*
             * The primary container, so Site Kit's `is_connected()` and its admin screens report
             * something truthful rather than an unconfigured module. It does not cause a tag to
             * render — `useSnippet` below governs that.
             */
            'containerID' => $configured['containerID'] ?? ($containers[0] ?? ''),

            /*
             * The guard against two snippets on one page. `Tag_Manager\Tag_Guard::can_activate()`
             * returns false on an empty `useSnippet`, so with the theme emitting, Site Kit cannot
             * render a tag however it is configured in wp-admin.
             */
            'useSnippet' => ! $this->emitsSnippet(),
        ]);
    }

    /**
     * Site Kit's active module list, with Tag Manager guaranteed present.
     *
     * Only registered when `force_module_active` is on. Site Kit defaults this option to
     * `['pagespeed-insights']` when it is absent, so appending rather than replacing keeps
     * whatever else has been switched on.
     *
     * @return array<int, string>
     */
    public function filterActiveModules(): array
    {
        $active = get_option(self::ACTIVE_MODULES_OPTION);

        if (! is_array($active)) {
            $active = get_option('googlesitekit-active-modules');
        }

        if (! is_array($active)) {
            $active = ['pagespeed-insights'];
        }

        $active[] = 'tagmanager';

        return array_values(array_unique(array_filter($active, 'is_string')));
    }

    /**
     * The GTM container loaders, one per configured container.
     *
     * Each is the standard loader Google publishes and Site Kit's `Web_Tag` renders. Running it
     * more than once is how Google itself documents multiple containers: `w[l] = w[l] || []`
     * means every container shares the one `dataLayer`, so an event pushed by a tag in one is
     * visible to tags in the other.
     */
    public function injectContainerSnippets(): void
    {
        $containers = $this->containers();

        if ($containers === []) {
            return;
        }

        echo "\n<!-- Google Tag Manager (config/site-kit.php) -->\n";

        foreach ($containers as $container) {
            $id = esc_js($container);

            echo <<<HTML
<script>
(function (w, d, s, l, i) {
  w[l] = w[l] || [];
  w[l].push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
  var f = d.getElementsByTagName(s)[0],
      j = d.createElement(s),
      dl = l != 'dataLayer' ? '&l=' + l : '';
  j.async = true;
  j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
  f.parentNode.insertBefore(j, f);
})(window, document, 'script', 'dataLayer', '{$id}');
</script>

HTML;
        }

        echo "<!-- End Google Tag Manager -->\n";
    }

    /**
     * The no-JavaScript fallback iframes, immediately inside `<body>`.
     *
     * `wp_body_open()` is the first statement in the layout's `<body>`, which is where Google
     * requires these to sit.
     */
    public function injectNoscriptFallbacks(): void
    {
        $containers = $this->containers();

        if ($containers === []) {
            return;
        }

        foreach ($containers as $container) {
            $src = 'https://www.googletagmanager.com/ns.html?id='.rawurlencode($container);

            printf(
                '<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'."\n",
                esc_url($src),
            );
        }
    }

    /**
     * Whether the containers should load on this request.
     */
    public function shouldEmit(): bool
    {
        return $this->emitsSnippet()
            && $this->environmentAllowed()
            && $this->containers() !== [];
    }

    /**
     * Every valid container id from config, de-duplicated and in order.
     *
     * A duplicate would load the same container twice and double every tag inside it, which
     * costs real money on a conversion pixel.
     *
     * @return array<int, string>
     */
    public function containers(): array
    {
        $containers = (array) config('site-kit.containers', []);

        $valid = array_filter(
            array_map(static fn ($container): string => trim((string) $container), $containers),
            static fn (string $container): bool => (bool) preg_match(self::CONTAINER_PATTERN, $container),
        );

        return array_values(array_unique(array_map('strtoupper', $valid)));
    }

    private function emitsSnippet(): bool
    {
        return (bool) config('site-kit.emit_snippet', true);
    }

    /**
     * Whether this environment is one the containers are allowed to load on.
     *
     * Mirrors Site Kit's own `Tag_Environment_Type_Guard`, which restricts tags to production by
     * default. The reason is not tidiness: the containers hold Meta and LinkedIn conversion
     * pixels, and a test booking on staging fires them against the same ad accounts as a real
     * one, teaching the bidding algorithms from traffic that was never a customer.
     */
    private function environmentAllowed(): bool
    {
        $allowed = (array) config('site-kit.environments', ['production']);

        if (! function_exists('wp_get_environment_type')) {
            return in_array('production', $allowed, true);
        }

        return in_array(wp_get_environment_type(), $allowed, true);
    }
}
