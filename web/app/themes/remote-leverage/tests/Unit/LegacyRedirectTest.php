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

        expect($middleware->resolve('/hire-va-old/', $map))->toBe('/hire-va-4/');
        expect($middleware->resolve('hire-va-old', $map))->toBe('/hire-va-4/');
    });

    test('returns null for unmapped paths', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/hire-va-4/', $map))->toBeNull();
        expect($middleware->resolve('/some-random-page/', $map))->toBeNull();
    });

    test('preserves query string (UTM parameters) on the redirect target', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        $target = $middleware->resolve('/hire-va-old/?utm_source=google&utm_campaign=spring', $map, 'utm_source=google&utm_campaign=spring');

        expect($target)->toBe('/hire-va-4/?utm_source=google&utm_campaign=spring');
    });
});

describe('absolute external targets', function () {
    $map = [
        'anyshore' => 'https://anyshore.ai/',
        'anyshore-utm' => 'https://anyshore.ai/?ref=rl',
        'hire-va-old' => 'hire-va-4',
    ];

    test('an absolute http(s) target is emitted verbatim, not prefixed with a slash', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/anyshore/', $map))->toBe('https://anyshore.ai/');
    });

    test('query strings and UTM parameters survive onto an external target', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/anyshore/?utm_source=google', $map, 'utm_source=google'))
            ->toBe('https://anyshore.ai/?utm_source=google');

        // A target that already carries a query string gets the visitor's appended, not a
        // second '?' that would truncate it.
        expect($middleware->resolve('/anyshore-utm/?utm_source=google', $map, 'utm_source=google'))
            ->toBe('https://anyshore.ai/?ref=rl&utm_source=google');
    });

    test('isExternalTarget only accepts a real absolute http(s) URL', function () {
        expect(LegacyRedirectMiddleware::isExternalTarget('https://anyshore.ai/'))->toBeTrue()
            ->and(LegacyRedirectMiddleware::isExternalTarget('http://anyshore.ai/x'))->toBeTrue()
            ->and(LegacyRedirectMiddleware::isExternalTarget('hire-va-4'))->toBeFalse()
            ->and(LegacyRedirectMiddleware::isExternalTarget(''))->toBeFalse()
            // Protocol-relative and scheme-lookalike strings must stay relative, so they land
            // under home_url() rather than jumping off-site.
            ->and(LegacyRedirectMiddleware::isExternalTarget('//evil.example'))->toBeFalse()
            ->and(LegacyRedirectMiddleware::isExternalTarget('https:/evil.example'))->toBeFalse()
            ->and(LegacyRedirectMiddleware::isExternalTarget('javascript:alert(1)'))->toBeFalse()
            ->and(LegacyRedirectMiddleware::isExternalTarget('/\\evil.example'))->toBeFalse();
    });

    test('externalHosts is derived from the map alone', function () use ($map) {
        expect(LegacyRedirectMiddleware::externalHosts($map))->toBe(['anyshore.ai'])
            ->and(LegacyRedirectMiddleware::externalHosts(['a' => 'b']))->toBe([]);
    });
});

describe('external targets cannot be turned into an open redirect', function () {
    /*
     * The whole safety property in one place: the request selects a KEY, the map supplies the
     * TARGET. Nothing a visitor can type — a path, a query parameter, an encoded absolute URL —
     * may come back out of resolve() as the destination.
     */
    $map = [
        'anyshore' => 'https://anyshore.ai/',
        'hire-va-old' => 'hire-va-4',
    ];

    test('an attacker-controlled URL never becomes the target', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        // The complete set of destinations this map can ever produce, computed from the map.
        $permitted = array_map(
            function (string $to): string {
                if (LegacyRedirectMiddleware::isExternalTarget($to)) {
                    return $to;
                }

                $path = '/'.trim($to, '/');

                return $path === '/' ? $path : $path.'/';
            },
            array_values($map)
        );

        $attacks = [
            'https://evil.example/',
            'http://evil.example/phish',
            '//evil.example/',
            '/\\evil.example/',
            // The host is attacker-controlled and the PATH happens to match a key: the visitor
            // still goes to the map's destination, never to evil.example.
            'https://evil.example/anyshore',
            'https://user:pass@evil.example/hire-va-old',
            '/anyshore.evil.example/',
            '/%2F%2Fevil.example',
            'anyshore/../../https://evil.example',
        ];

        foreach ($attacks as $attack) {
            $result = $middleware->resolve($attack, $map);

            if ($result === null) {
                continue;
            }

            expect(in_array($result, $permitted, true))->toBeTrue(
                "resolve('{$attack}') returned '{$result}', which is not one of the map's own targets"
            );
            expect(parse_url($result, PHP_URL_HOST))->not->toBe('evil.example');
        }
    });

    test('an unmapped attacker URL resolves to nothing at all', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        foreach (['https://evil.example/', '//evil.example/', '/\\evil.example/', '/%2F%2Fevil.example'] as $attack) {
            expect($middleware->resolve($attack, $map))->toBeNull(
                "resolve('{$attack}') produced a target; an unmapped path must produce none"
            );
        }
    });

    test('a URL smuggled through the query string only ever rides along as a query string', function () use ($map) {
        $middleware = new LegacyRedirectMiddleware;

        $target = $middleware->resolve('/anyshore/?next=https://evil.example', $map, 'next=https%3A%2F%2Fevil.example');

        expect($target)->toStartWith('https://anyshore.ai/')
            ->and(parse_url($target, PHP_URL_HOST))->toBe('anyshore.ai');

        $internal = $middleware->resolve('/hire-va-old/?next=https://evil.example', $map, 'next=https%3A%2F%2Fevil.example');

        expect($internal)->toStartWith('/hire-va-4/?')
            ->and(LegacyRedirectMiddleware::isExternalTarget((string) $internal))->toBeFalse();
    });

    test('every external host the map can reach is one the map itself declares', function () {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        // buy.stripe.com added 2026-09-16: production 301s /deposit/ to a Stripe payment link.
        // form.jotform.com added 2026-09-17: production 301s /refund/ to a JostForm refund link.
        // The rest added 2026-09-18 with the ported vanity short links; order follows the map.
        expect(LegacyRedirectMiddleware::externalHosts($config))->toBe([
            'anyshore.ai', 'buy.stripe.com', 'form.jotform.com',
            // The 2026-09-18 legacy audit ported 63 vanity short links off the GoDaddy box,
            // where they lived in the `301-redirects` plugin and had never reached v2. They
            // are operational links (recruiting, Zoom rooms, Calendly, assessment forms), so
            // each one widens this allowlist. Full audit: docs/legacy-redirects-audit.md.
            'remoteleveragejobs.com', 'calendly.com', 'vimeo.com', 'us02web.zoom.us',
            'forms.gle', 'www.livechat.com', 'remoteleveragetech.atlassian.net',
            'drive.google.com', 'sso.online.tableau.com', 'g.page', 'recruitcrm.io',
            'docs.google.com', 'stats.uptimerobot.com', 'get.deel.com',
            'recruiting-helper.replit.app',
        ]);
    });
});

describe('config/redirects.php map', function () {
    $config = require dirname(__DIR__, 2).'/config/redirects.php';

    test('every target resolves to a real destination, not another redirect key', function () use ($config) {
        foreach ($config as $from => $to) {
            // An absolute external URL is terminal by definition: it leaves this site, so it
            // can never collide with a key in this map and cannot start a 301 chain here.
            if (LegacyRedirectMiddleware::isExternalTarget($to)) {
                expect($config)->not->toHaveKey($to);

                continue;
            }

            // NOT `expect($config)->not->toHaveKey($to, $message)`. Pest reads a second argument
            // to toHaveKey as the expected VALUE, not a failure message, so that form only
            // asserts "not this key holding this value" — weaker than intended, and it passes
            // for the wrong reason whenever the value differs.
            expect(array_key_exists($to, $config))->toBeFalse(
                "'{$from}' redirects to '{$to}', which is itself a redirect key (301 chain)"
            );
        }
    });

    test('the retired pages that became real v2 pages are no longer redirect keys', function () use ($config) {
        // contractoragreement / services / store are built as v2 pages as of 2026-09-15.
        // A key matching a live page slug would 301 that page away.
        expect($config)->not->toHaveKey('contractoragreement')
            ->and($config)->not->toHaveKey('services')
            ->and($config)->not->toHaveKey('store');
    });

    test('hire-us-uk-now points at the canonical hire page, not the LATAM pricing page', function () use ($config) {
        // Production /hire-us-uk-now/ offers American and British professionals at $10-$15/hr;
        // /hire-for-less/ sells Latin American VAs at $6-$10/hr and misstated the offer.
        expect($config['hire-us-uk-now'])->toBe('hire-va-4');
    });

    test('the retired tools index and the retired signature generator each 301 to their own target', function () use ($config) {
        // 'tools' => '' is a deliberate 301 of production's retired tools index. Matching is by
        // exact normalized path, never by prefix, so the deeper path resolves on its own key:
        // the Livewire signature generator was removed 2026-09-15 in favour of
        // /social-media-kit/, which is a strict superset of it.
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/tools/', $config))->toBe('/')
            ->and($middleware->resolve('/tools/signature-generator/', $config))->toBe('/social-media-kit/')
            ->and($middleware->resolve('/tools/signature-generator', $config))->toBe('/social-media-kit/');
    });

    test('duplicate thank-you slug 301s to the canonical vathankyou page', function () use ($config) {
        $middleware = new LegacyRedirectMiddleware;

        expect($middleware->resolve('/thank-you/', $config))->toBe('/vathankyou/');
    });
});
