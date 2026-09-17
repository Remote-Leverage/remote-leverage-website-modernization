<?php

declare(strict_types=1);

use App\Infrastructure\WordPress\Hooks\SiteKitHooks;

/**
 * Covers GTM delivery and the config-file override of Site Kit's settings.
 *
 * The failures these guard are all silent and all expensive: a container that stops firing takes
 * its Meta and LinkedIn conversion tags with it, and a container that fires twice bills twice.
 * Nothing in either case produces an error anyone would see.
 */
beforeEach(function () {
    config([
        'site-kit.containers' => ['GTM-53JDTQCZ', 'GTM-P4KZNJWL'],
        'site-kit.emit_snippet' => true,
        'site-kit.environments' => ['production', 'development'],
        'site-kit.tagmanager' => [
            'accountID' => '6001234567',
            'ampContainerID' => '',
            'internalContainerID' => '',
            'internalAMPContainerID' => '',
        ],
        'site-kit.force_module_active' => false,
    ]);

    // The stub reads this; left over from a previous test it would decide this one.
    $GLOBALS['wp_environment_type'] = 'development';
});

describe('GTM delivery', function () {
    test('every configured container is loaded, not just the first', function () {
        /*
         * The whole reason delivery lives in the theme. Site Kit's Tag Manager module holds one
         * `containerID` and builds `new Web_Tag($settings['containerID'])` from it, so connecting
         * it would silently drop the second container and everything inside it. Verified against
         * Site Kit 1.187.0; see cutover-decisions.md decision 32.
         */
        $output = captureHook(fn (SiteKitHooks $hooks) => $hooks->injectContainerSnippets());

        expect($output)->toContain("'GTM-53JDTQCZ'")
            ->and($output)->toContain("'GTM-P4KZNJWL'")
            ->and(substr_count($output, 'googletagmanager.com/gtm.js'))->toBe(2);
    });

    test('each container gets a noscript fallback', function () {
        $output = captureHook(fn (SiteKitHooks $hooks) => $hooks->injectNoscriptFallbacks());

        expect($output)->toContain('ns.html?id=GTM-53JDTQCZ')
            ->and($output)->toContain('ns.html?id=GTM-P4KZNJWL')
            ->and(substr_count($output, '<noscript>'))->toBe(2);
    });

    test('a duplicated container id is loaded once', function () {
        // Loading the same container twice doubles every tag inside it, which on a conversion
        // pixel is a real invoice rather than a cosmetic problem.
        config(['site-kit.containers' => ['GTM-53JDTQCZ', 'gtm-53jdtqcz', 'GTM-53JDTQCZ']]);

        $hooks = new SiteKitHooks;

        expect($hooks->containers())->toBe(['GTM-53JDTQCZ'])
            // 'gtm.js' alone appears twice per container -- as the dataLayer event name and in
            // the src -- so the loader URL is what counts loads.
            ->and(substr_count(captureHook(fn ($h) => $h->injectContainerSnippets()), 'googletagmanager.com/gtm.js'))->toBe(1);
    });

    test('a malformed container id is dropped rather than written into a script tag', function () {
        // These values reach an inline <script>. A list that comes up short is recoverable; an
        // injection point is not.
        config(['site-kit.containers' => ['GTM-53JDTQCZ', 'not-a-container', '', 'GTM-'.'<script>']]);

        expect((new SiteKitHooks)->containers())->toBe(['GTM-53JDTQCZ']);
    });

    test('nothing is emitted when no container is configured', function () {
        config(['site-kit.containers' => []]);

        $hooks = new SiteKitHooks;

        expect($hooks->shouldEmit())->toBeFalse()
            ->and(captureHook(fn ($h) => $h->injectContainerSnippets()))->toBe('');
    });
});

describe('environment gating', function () {
    test('containers stay off outside the allowed environments', function () {
        /*
         * The containers hold Meta and LinkedIn conversion pixels. A test booking on staging
         * fires them against the same ad accounts as a real one, teaching the bidding algorithms
         * from traffic that was never a customer. Mirrors Site Kit's own
         * Tag_Environment_Type_Guard, which is production-only for the same reason.
         */
        config(['site-kit.environments' => ['production']]);
        $GLOBALS['wp_environment_type'] = 'staging';

        expect((new SiteKitHooks)->shouldEmit())->toBeFalse();
    });

    test('an environment can be opted in explicitly', function () {
        config(['site-kit.environments' => ['production', 'staging']]);
        $GLOBALS['wp_environment_type'] = 'staging';

        expect((new SiteKitHooks)->shouldEmit())->toBeTrue();
    });

    test('production loads them', function () {
        config(['site-kit.environments' => ['production']]);
        $GLOBALS['wp_environment_type'] = 'production';

        expect((new SiteKitHooks)->shouldEmit())->toBeTrue();
    });
});

describe('Site Kit settings come from the config file', function () {
    test('Site Kit is told not to render its own snippet while the theme renders', function () {
        /*
         * The guard against two GTM snippets on one page. Tag_Manager\Tag_Guard::can_activate()
         * returns false on an empty useSnippet, so with this filtered off Site Kit cannot emit a
         * tag however wp-admin is configured. Filtered at read time rather than written on
         * deploy precisely so there is no window in which both are true.
         */
        expect((new SiteKitHooks)->filterTagManagerSettings()['useSnippet'])->toBeFalse();
    });

    test('Site Kit regains the snippet when the theme stands down', function () {
        config(['site-kit.emit_snippet' => false]);

        $hooks = new SiteKitHooks;

        expect($hooks->filterTagManagerSettings()['useSnippet'])->toBeTrue()
            ->and($hooks->shouldEmit())->toBeFalse();
    });

    test('the primary container and account reach Site Kit so its module is not blank', function () {
        $settings = (new SiteKitHooks)->filterTagManagerSettings();

        expect($settings['containerID'])->toBe('GTM-53JDTQCZ')
            ->and($settings['accountID'])->toBe('6001234567')
            // Every key Site Kit's Settings::get_default() declares must survive, or code
            // reading one of them gets a PHP warning and a null.
            ->and($settings)->toHaveKeys([
                'ownerID', 'accountID', 'containerID', 'ampContainerID',
                'internalContainerID', 'internalAMPContainerID', 'useSnippet',
            ]);
    });

    test('forcing the module active appends rather than replacing what is already on', function () {
        $active = (new SiteKitHooks)->filterActiveModules();

        expect($active)->toContain('tagmanager')
            // Site Kit defaults this option to ['pagespeed-insights']; replacing the list would
            // silently switch off whatever else had been enabled.
            ->and($active)->toContain('pagespeed-insights')
            ->and($active)->toBe(array_values(array_unique($active)));
    });
});

/**
 * Run one of the hook's echo-based renderers and return what it printed.
 */
function captureHook(callable $render): string
{
    $hooks = new SiteKitHooks;

    ob_start();
    $render($hooks);

    return (string) ob_get_clean();
}
