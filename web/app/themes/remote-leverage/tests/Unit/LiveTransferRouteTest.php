<?php

declare(strict_types=1);

use App\Support\PageRobots;

/**
 * The live-transfer booking form route.
 *
 * Recovered from production page 51147 on 2026-09-17. Public by decision — reps reach it by URL
 * with no login, as on production — but never indexed, which production's copy was not: it sits
 * in `page-sitemap.xml` today and serves the full working form to anyone anonymous.
 *
 * The noindex cannot be proven by fetching the page locally: Bedrock's `bedrock-disallow-indexing`
 * mu-plugin noindexes every non-production environment, so the homepage and this route look
 * identical whether or not `forceNoindex()` does anything at all. These assert the filter
 * directly, which is the only place the difference is visible.
 */
afterEach(function () {
    PageRobots::clearForced();
});

describe('route-level noindex', function () {
    test('a forced posture noindexes a page that has no post behind it', function () {
        /*
         * `currentPagePosture()` opens with an `is_singular()` check, so it can never speak for a
         * route. Without the forced posture a route is silently indexable however it is written —
         * the wrong default for an internal tool that creates CRM contacts.
         */
        PageRobots::forceNoindex();

        $robots = PageRobots::filter(['index' => true, 'follow' => true, 'max-image-preview' => 'large']);

        expect($robots)->toHaveKey('noindex')
            ->and($robots['noindex'])->toBeTrue()
            ->and($robots)->toHaveKey('nofollow')
            // The positive directives must go, or the tag contradicts itself.
            ->and($robots)->not->toHaveKey('index')
            ->and($robots)->not->toHaveKey('follow')
            ->and($robots)->not->toHaveKey('max-image-preview');
    });

    test('the follow variant keeps link equity flowing', function () {
        PageRobots::forceNoindex(follow: true);

        $robots = PageRobots::filter(['index' => true, 'follow' => true]);

        expect($robots['noindex'])->toBeTrue()
            ->and($robots)->not->toHaveKey('nofollow');
    });

    test('nothing is forced until a route asks', function () {
        // A leaked static would noindex every page rendered after one internal tool.
        expect(PageRobots::filter(['index' => true, 'follow' => true]))
            ->toBe(['index' => true, 'follow' => true]);
    });

    test('clearing the forced posture restores normal behaviour', function () {
        PageRobots::forceNoindex();
        PageRobots::clearForced();

        expect(PageRobots::filter(['index' => true]))->toBe(['index' => true]);
    });
});

describe('the recovered view', function () {
    function liveTransferView(): string
    {
        return (string) file_get_contents(
            __DIR__.'/../../resources/views/pages/live-transfer-contact-creation.blade.php'
        );
    }

    test('Blade directives are balanced', function () {
        /*
         * Guards a real 500. The file's own header comment described the verbatim block by name,
         * and Blade expands `@verbatim` *before* it strips `{{-- --}}` comments — so the mention
         * inside the comment opened a block that swallowed `@section('content')` and left
         * `@endsection` orphaned: "Cannot end a section without first starting one".
         *
         * Counting is enough to catch it, and it catches the general case too.
         */
        $view = liveTransferView();

        // `@endverbatim` contains `@verbatim`, so count the opener by subtracting the closer.
        $openers = substr_count($view, '@verbatim') - substr_count($view, '@endverbatim');

        expect($openers)->toBe(1)
            ->and(substr_count($view, '@endverbatim'))->toBe(1)
            ->and(substr_count($view, '@section('))->toBe(substr_count($view, '@endsection'))
            ->and(substr_count($view, '@extends('))->toBe(1);
    });

    test('the webhook and timezone come from config, not from the markup', function () {
        /*
         * On production the n8n URL was hardcoded in the page body, so changing it meant editing
         * content in Elementor. Here it arrives through a data attribute the script reads.
         */
        $view = liveTransferView();

        expect($view)->toContain('data-webhook="{{ $webhookUrl }}"')
            ->and($view)->toContain('data-timezone="{{ $timezone }}"')
            // The literal must not survive anywhere in the view.
            ->and($view)->not->toContain('https://n8n.srv1338052.hstgr.cloud');
    });

    test('the load-bearing behaviours survived the port', function () {
        $view = liveTransferView();

        expect($view)->toContain('submissionId')
            // Eastern time is the deliberate default, whatever the rep's own clock says.
            ->and($view)->toContain('America/New_York')
            ->and($view)->toContain('bdrfForm');
    });
});

describe('config', function () {
    test('a blank webhook is expressible, so a missing value fails visibly', function () {
        $config = require __DIR__.'/../../config/live-transfer.php';

        expect($config)->toHaveKeys(['webhook_url', 'timezone'])
            ->and($config['timezone'])->toBe('America/New_York');
    });
});
