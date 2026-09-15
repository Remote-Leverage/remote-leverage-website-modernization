<?php

declare(strict_types=1);

use App\Application\Http\Middleware\MissingPathNotFoundMiddleware;

/*
 * Guards known-issues.md bug #2: with `use_verbose_page_rules` true (permalink structure
 * `/blog/%postname%/`), WP::parse_request() skips the catch-all page rule for a page that
 * does not exist, leaving no query vars — which renders the front page with a 200 instead
 * of a 404.
 */
describe('MissingPathNotFoundMiddleware', function () {
    $routes = ['book-consultation', 'referrer-portal', 'live-call/connect'];

    test('forces a 404 when a non-empty path resolved to nothing', function () use ($routes) {
        $middleware = new MissingPathNotFoundMiddleware;

        expect($middleware->shouldForce404('/zzz-nope-123/', null, [], $routes))->toBeTrue()
            ->and($middleware->shouldForce404('/zzz/deep/nope', null, [], $routes))->toBeTrue()
            ->and($middleware->shouldForce404('/hire-va-4-preview/', '', [], $routes))->toBeTrue();
    });

    test('leaves the front page alone', function () use ($routes) {
        $middleware = new MissingPathNotFoundMiddleware;

        expect($middleware->shouldForce404('/', null, [], $routes))->toBeFalse()
            ->and($middleware->shouldForce404('', null, [], $routes))->toBeFalse();
    });

    test('leaves requests a rewrite rule resolved alone', function () use ($routes) {
        $middleware = new MissingPathNotFoundMiddleware;

        expect($middleware->shouldForce404('/about-us/', '(.?.+?)(?:/([0-9]+))?/?$', ['pagename' => 'about-us'], $routes))->toBeFalse()
            ->and($middleware->shouldForce404('/blog/some-post/', 'blog/([^/]+)/?$', ['name' => 'some-post'], $routes))->toBeFalse();
    });

    test('leaves explicit query vars alone — those already 404 correctly', function () use ($routes) {
        $middleware = new MissingPathNotFoundMiddleware;

        // ?pagename= for a missing page reaches WP_Query and 404s on its own.
        expect($middleware->shouldForce404('/?pagename=zzz-nope', null, ['pagename' => 'zzz-nope'], $routes))->toBeFalse()
            ->and($middleware->shouldForce404('/?p=999999', null, ['p' => '999999'], $routes))->toBeFalse()
            ->and($middleware->shouldForce404('/?s=virtual', null, ['s' => 'virtual'], $routes))->toBeFalse()
            ->and($middleware->shouldForce404('/feed/', 'feed/?$', ['feed' => 'feed'], $routes))->toBeFalse();
    });

    test('never 404s a path Acorn serves as a Laravel route', function () use ($routes) {
        $middleware = new MissingPathNotFoundMiddleware;

        foreach ($routes as $route) {
            expect($middleware->shouldForce404('/'.$route.'/', null, [], $routes))
                ->toBeFalse("'{$route}' is a registered Laravel route and must never be forced to 404");
        }
    });

    test('ignores the query string when deciding, and tolerates no known routes', function () {
        $middleware = new MissingPathNotFoundMiddleware;

        expect($middleware->shouldForce404('/zzz-nope/?utm_source=google', null, [], []))->toBeTrue()
            ->and($middleware->shouldForce404('/book-consultation/?utm_source=x', null, [], ['book-consultation']))->toBeFalse();
    });
});
