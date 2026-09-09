<?php

declare(strict_types=1);

use App\Application\Http\Middleware\LegacyRedirectMiddleware;

describe('LegacyRedirectMiddleware (WR-103, ADR-0006 § SEO & Risk Mitigation)', function () {
    $map = [
        'hire-va-old' => 'hire-va-4',
        'book-a-call' => 'book-consultation',
    ];

    test('resolves a mapped legacy path to its canonical target', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/hire-va-old/', $map))->toBe('/hire-va-4');
        expect($middleware->resolve('hire-va-old', $map))->toBe('/hire-va-4');
    });

    test('returns null for unmapped paths', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/hire-va-4/', $map))->toBeNull();
        expect($middleware->resolve('/some-random-page/', $map))->toBeNull();
    });

    test('preserves query string (UTM parameters) on the redirect target', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        $target = $middleware->resolve('/hire-va-old/?utm_source=google&utm_campaign=spring', $map, 'utm_source=google&utm_campaign=spring');

        expect($target)->toBe('/hire-va-4?utm_source=google&utm_campaign=spring');
    });
});
