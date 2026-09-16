<?php

declare(strict_types=1);
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Sentry noise control.
 *
 * An alert channel that is mostly noise is worse than no alert channel, because it trains people
 * to ignore the one real error when it arrives. Production's legacy Sentry reached that state —
 * a representative alert is `ReferenceError: oaiq is not defined`, a measurement global a GTM tag
 * expects, from code we do not own.
 *
 * These tests pin the filters so they cannot be quietly removed, and — more usefully — assert
 * that every ignored exception class actually exists. A typo there fails silently: the filter
 * matches nothing and the noise returns with no error anywhere.
 */

describe('server-side filtering', function () {
    test('every ignored exception class exists', function () {
        // The failure this catches: a renamed or misspelled class silently stops filtering.
        $config = require __DIR__.'/../../config/sentry.php';

        $missing = array_values(array_filter(
            $config['ignore_exceptions'] ?? [],
            static fn (string $class) => ! class_exists($class),
        ));

        expect($missing)->toBe([]);
    });

    test('routine traffic is not reported as a defect', function () {
        $config = require __DIR__.'/../../config/sentry.php';
        $ignored = $config['ignore_exceptions'] ?? [];

        // A 404 from a scanner and a visitor mistyping an email are both normal operation.
        expect($ignored)->toContain(NotFoundHttpException::class)
            ->and($ignored)->toContain(ValidationException::class)
            ->and($ignored)->toContain(TokenMismatchException::class);
    });

    test('health and asset endpoints are not traced', function () {
        // They are hit constantly and nobody is waiting on them, so tracing them dominates the
        // performance data with traffic that has no customer behind it.
        $config = require __DIR__.'/../../config/sentry.php';

        expect($config['ignore_transactions'])->toContain('/up')
            ->and($config['ignore_transactions'])->toContain('/favicon.ico');
    });
});

describe('browser-side filtering', function () {
    test('the browser SDK restricts reporting to our own code', function () {
        // The single biggest filter. Without allowUrls, every GTM tag, browser extension and
        // embedded widget error becomes one of our alerts.
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($js)->toContain('allowUrls')
            ->and($js)->toContain('ignoreErrors')
            ->and($js)->toContain('denyUrls');
    });

    test('bots do not initialise the SDK at all', function () {
        // A crawler hitting a JS error says nothing about a customer's experience, and headless
        // engines generate a disproportionate share of the volume.
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($js)->toContain('SENTRY_BOT_UA')
            ->and($js)->toContain('!SENTRY_BOT_UA.test(navigator.userAgent');
    });

    test('an event with no stack frames is dropped', function () {
        // Cross-origin script errors arrive with an empty frame list and group into one
        // enormous unactionable issue.
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        expect($js)->toContain('beforeSend')
            ->and($js)->toContain('frames.length === 0');
    });

    test('the third-party scripts this site embeds are denied', function () {
        $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

        foreach (['googletagmanager', 'connect\\.facebook\\.net', 'cdp\\.customer\\.io', 'assets\\.calendly\\.com', 'js\\.stripe\\.com'] as $host) {
            expect($js)->toContain($host);
        }
    });
});
