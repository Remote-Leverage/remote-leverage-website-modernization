<?php

declare(strict_types=1);

use App\Blocks\ExperimentBlock;
use App\Infrastructure\WordPress\Hooks\TrackingHooks;
use App\Support\BlockDefaults;

/**
 * Covers the client-side A/B testing surface: the PostHog snippet that has to load eagerly for a
 * flag to be readable during render, the flags-only mode that makes an experiment rehearsable off
 * production, and the runtime that decides which arm paints.
 *
 * See docs/ab-testing.md.
 */
beforeEach(function () {
    config([
        'services.posthog.api_key' => 'phc_test_key',
        'services.posthog.host' => 'https://us.i.posthog.com',
        'services.posthog.environments' => ['production'],
        'services.posthog.flag_environments' => ['local', 'development', 'staging'],
        'services.posthog.experiment_timeout_ms' => 1500,
    ]);

    $GLOBALS['wp_environment_type'] = 'production';
});

function renderTracking(callable $render): string
{
    $hooks = new TrackingHooks;

    ob_start();
    $render($hooks);

    return (string) ob_get_clean();
}

describe('PostHog snippet', function () {
    test('array.js is requested immediately, not behind the interaction deferral', function () {
        /*
         * The reason this feature exists. While the fetch was deferred, `window.posthog` was the
         * queueing stub for up to 6s — and the stub's getFeatureFlag() returns undefined rather
         * than queueing an answer, so no flag was readable during render.
         */
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectPostHogSnippet());

        expect($out)->toContain('/static/array.js')
            ->and($out)->toContain('us-assets.i.posthog.com')
            // The deferral's triggers. Any of these coming back means the fetch waits again.
            ->and($out)->not->toContain('pointerdown')
            ->and($out)->not->toContain('requestIdleCallback')
            ->and($out)->not->toContain("addEventListener('load'");
    });

    test('init still runs before array.js is appended', function () {
        // Ordering is load-bearing: the stub must have set __SV and queued the init arguments on
        // _i before array.js runs, or the real library initialises with no config.
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectPostHogSnippet());

        expect(strpos($out, 'posthog.init('))->toBeLessThan(strpos($out, '/static/array.js'));
    });

    test('the flag request timeout is passed to posthog-js', function () {
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectPostHogSnippet());

        expect($out)->toContain('"feature_flag_request_timeout_ms":1500');
    });

    test('production captures normally and is never downgraded to flags-only', function () {
        // Listing production in POSTHOG_FLAG_ENVIRONMENTS must not silence production.
        config(['services.posthog.flag_environments' => ['production', 'staging']]);

        $hooks = new TrackingHooks;
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectPostHogSnippet());

        expect($hooks->postHogFlagsOnly())->toBeFalse()
            // The suppression must never leak into the environment that has to capture for real.
            ->and($out)->not->toContain('before_send');
    });

    test('staging gets flags without writing anything into the project', function () {
        $GLOBALS['wp_environment_type'] = 'staging';

        $hooks = new TrackingHooks;
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectPostHogSnippet());

        expect($hooks->postHogSnippetAllowed())->toBeTrue()
            ->and($hooks->postHogFlagsOnly())->toBeTrue()
            /*
             * `before_send` returning null, rather than opting out: it drops every event at the
             * queue and provably cannot suppress the `/flags/` request, which is not an event.
             * Opting out would be the obvious switch but takes the flags with it in some
             * posthog-js versions, leaving staging with nothing to rehearse.
             */
            ->and($out)->toContain('before_send = function () { return null; }')
            ->and($out)->toContain('"disable_session_recording":true')
            ->and($out)->toContain('"autocapture":false')
            // Flags still have to arrive, or there is nothing to rehearse.
            ->and($out)->toContain('/static/array.js');
    });

    test('an environment in neither list gets no snippet at all', function () {
        $GLOBALS['wp_environment_type'] = 'qa-sandbox';

        expect(renderTracking(fn ($h) => $h->injectPostHogSnippet()))->toBe('');
    });

    test('nothing renders without an api key', function () {
        config(['services.posthog.api_key' => '']);

        expect(renderTracking(fn ($h) => $h->injectPostHogSnippet()))->toBe('');
    });
});

describe('experiment runtime', function () {
    test('the $feature super-property prefix survives PHP heredoc interpolation', function () {
        /*
         * Regression guard. The runtime is emitted from a heredoc, where an unescaped `$feature`
         * is read as a PHP variable and interpolates to an empty string — leaving the events
         * tagged `'/' + flag` and every experiment unattributable, with no error anywhere.
         */
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectExperimentRuntime());

        expect($out)->toContain("'\$feature/' + flag")
            ->and($out)->not->toContain("'/' + flag");
    });

    test('it emits regardless of PostHog, because the fallback must still work', function () {
        // A page with an experiment on it has to resolve when PostHog is absent or blocked.
        config(['services.posthog.api_key' => '']);
        $GLOBALS['wp_environment_type'] = 'qa-sandbox';

        expect(renderTracking(fn ($h) => $h->injectExperimentRuntime()))->toContain('w.rlExp');
    });

    test('it carries the configured timeout and the QA override parameter', function () {
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectExperimentRuntime());

        expect($out)->toContain('var TIMEOUT = 1500;')
            ->and($out)->toContain('rl_variant');
    });

    test('it only trusts getFeatureFlag once the real library has loaded', function () {
        // Without the __loaded check the stub's no-op return reads as "no such flag", which would
        // settle every experiment on its default arm before the flags ever arrived.
        $out = renderTracking(fn (TrackingHooks $h) => $h->injectExperimentRuntime());

        expect($out)->toContain('ph.__loaded');
    });
});

describe('variant keys', function () {
    test('a key that could break out of the inline script is stripped', function () {
        // The flag reaches a <script> tag, so this is a security boundary.
        expect(ExperimentBlock::sanitiseKey("');alert(1);//"))->toBe('alert1')
            ->and(ExperimentBlock::sanitiseKey('<script>'))->toBe('script')
            ->and(ExperimentBlock::sanitiseKey(null))->toBe('');
    });

    test('the keys PostHog actually issues pass through untouched', function () {
        expect(ExperimentBlock::sanitiseKey('new-hero-2026'))->toBe('new-hero-2026')
            ->and(ExperimentBlock::sanitiseKey('booking_wizard_v2'))->toBe('booking_wizard_v2');
    });
});

describe('pattern markup', function () {
    test('an arm nests its inner blocks rather than self-closing', function () {
        // acf/experiment is the theme's only InnerBlocks block. A self-closing comment here
        // would drop every block inside the arm with no error.
        $out = BlockDefaults::renderExperiment(
            'home-hero-2026',
            'test',
            '<!-- wp:acf/home-hero /-->',
        );

        expect($out)->toStartWith('<!-- wp:acf/experiment ')
            ->and($out)->toEndWith('<!-- /wp:acf/experiment -->')
            ->and($out)->toContain('<!-- wp:acf/home-hero /-->')
            ->and($out)->not->toContain('/-->'."\n");
    });

    test('field keys follow the convention the editor resolves against', function () {
        $out = BlockDefaults::renderExperiment('f', 'control', '', true);

        expect($out)->toContain('"_flag":"field_experiment_block_flag"')
            ->and($out)->toContain('"_variant":"field_experiment_block_variant"')
            ->and($out)->toContain('"_is_default":"field_experiment_block_is_default"')
            ->and($out)->toContain('"is_default":1');
    });
});
