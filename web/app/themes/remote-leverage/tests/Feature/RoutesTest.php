<?php

declare(strict_types=1);

use App\Application\Http\Middleware\LegacyRedirectMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

describe('Application Routes', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        // Guard each route file on a name it actually defines. A single shared sentinel
        // is order-dependent: GatedDownloadTest registers api.php alone behind the same
        // `api.health` check, so when it ran first this block short-circuited and web.php
        // never loaded — every web-route assertion then failed, while the file still
        // passed in isolation. That made the suite intermittently red.
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
        }

        if (! Route::has('funnel.book-consultation')) {
            Route::middleware([])->group($baseDir.'/routes/web.php');
        }

        Route::getRoutes()->refreshNameLookups();
    });

    test('api routes are registered', function () {
        expect(Route::has('api.webhooks.stripe'))->toBeTrue()
            ->and(Route::has('api.webhooks.calendly'))->toBeTrue()
            ->and(Route::has('api.health'))->toBeTrue();
    });

    test('web routes are registered for standalone funnels', function () {
        expect(Route::has('funnel.book-consultation'))->toBeTrue()
            ->and(Route::has('live-call.connect'))->toBeTrue()
            ->and(Route::has('referrer.portal'))->toBeTrue()
            ->and(Route::has('referrer.register'))->toBeTrue()
            ->and(Route::has('referrer.dashboard.legacy'))->toBeTrue();
    });

    test('health check route returns healthy json response', function () {
        $request = Request::create('/api/health', 'GET');
        $response = Route::dispatch($request);

        expect($response->getStatusCode())->toBe(200);

        $payload = json_decode((string) $response->getContent(), true);
        expect($payload)->toHaveKey('status', 'healthy')
            ->and($payload)->toHaveKey('timestamp');
    });
});

describe('config/redirects.php targets resolve to something real', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
            Route::middleware([])->group($baseDir.'/routes/web.php');
            Route::getRoutes()->refreshNameLookups();
        }
    });

    /*
     * A redirect target must be one of three things: a registered Laravel route, a real
     * WordPress page, or an absolute http(s):// URL on another site (see $externalTargets).
     * Tests have no database, so page-slug targets are declared here and verified by
     * hand against `wp post list --post_type=page`; the date below is that check.
     */
    $wordPressPageTargets = [
        'vathankyou' => 'page ID 126, page-vathankyou.blade.php — verified 2026-09-14',
        'hire-va-4' => 'page ID 1000000, renders patterns/hire-va-4-full.php — created and verified 2026-09-14',

        // Targets of the ADR-0006 cutover map (the 164 discarded production URLs added to
        // config/redirects.php). All verified against `wp post list --post_type=page
        // --post_status=publish` on 2026-09-15.
        '' => 'the site root — front page, page ID 7 (slug "home"), front-page.blade.php — verified 2026-09-15',
        'blog' => 'page ID 799 — verified 2026-09-15',
        'reviews' => 'page ID 292 — verified 2026-09-15',
        'vacalendar' => 'page ID 1000002 — verified 2026-09-15',
        'samples' => 'page ID 1000005 — verified 2026-09-15',
        'contractor-management' => 'page ID 1000006 — verified 2026-09-15',
        'contractor-payments' => 'page ID 1000007 — verified 2026-09-15',
        'hire-for-less' => 'page ID 1000031 — verified 2026-09-15',
        'vaonboardingform' => 'page ID 1000033 — verified 2026-09-15',
        'hire-va-6' => 'page ID 1000041 — verified 2026-09-15',
        'hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b' => 'page ID 1000047 — verified 2026-09-15',
        'vastore5' => 'page ID 1000050 — verified 2026-09-15',
        '1monthonus' => 'page ID 1000052 — verified 2026-09-15',
        'hire-va-isolated-form' => 'page ID 1000053 — verified 2026-09-15',
    ];

    /*
     * Absolute off-site destinations. These are a third valid target class: not a route and
     * not a page, but a real URL on another host. Declared here so adding one stays a
     * deliberate act — an unlisted external target still fails the test below.
     *
     * They are also the only hosts LegacyRedirectMiddleware will ever redirect off-site to,
     * because the allowlist it hands wp_safe_redirect() is derived from this same static map.
     */
    $externalTargets = [
        'https://anyshore.ai/' => 'Anyshore spun out onto its own domain; no v2 equivalent — 2026-09-15',
    ];

    /*
     * Targets known to point at nothing, quarantined so the suite stays green while the
     * underlying content decision is open. Adding an entry should be deliberate — the
     * default is to fix the target, not to list it here.
     */
    $pendingTargets = [
        // Empty: every redirect target currently resolves. Add an entry only when a target is
        // knowingly dead while the content decision is open, and give the reason.
    ];

    test('every target is a registered route, a known page, a static file on disk, a declared external URL, or explicitly quarantined', function () use ($wordPressPageTargets, $externalTargets, $pendingTargets) {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';
        $routeUris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => trim($route->uri(), '/'))
            ->all();

        // A fourth valid target class: a static asset served straight off disk, which is how
        // the retired rl-social-kit plugin's 29 downloads are preserved. Checking the file
        // really exists is the point — a typo or a deleted asset fails here rather than
        // 404ing for whoever clicked an old link.
        $themeRoot = dirname(__DIR__, 2);
        $isStaticFile = static function (string $to) use ($themeRoot): bool {
            $prefix = 'app/themes/remote-leverage/';

            if (! str_starts_with($to, $prefix)) {
                return false;
            }

            return is_file($themeRoot.'/'.substr($to, strlen($prefix)));
        };

        foreach ($config as $from => $to) {
            $resolves = in_array($to, $routeUris, true)
                || isset($wordPressPageTargets[$to])
                || isset($externalTargets[$to])
                || $isStaticFile($to)
                || isset($pendingTargets[$to]);

            expect($resolves)->toBeTrue(
                "'{$from}' => '{$to}': target is not a registered route, not a declared "
                .'WordPress page, not a static file on disk, not a declared external URL, and '
                .'not quarantined. Point it at something real, or add it to $pendingTargets '
                .'with a reason.'
            );
        }
    });

    test('every declared external target is a real absolute http(s) URL', function () use ($externalTargets) {
        foreach (array_keys($externalTargets) as $target) {
            expect(LegacyRedirectMiddleware::isExternalTarget($target))->toBeTrue(
                "'{$target}' is listed as an external target but is not an absolute http(s) URL."
            );
        }
    });

    test('every external target in the map is declared, and nothing else leaves the site', function () use ($externalTargets) {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        $inMap = array_values(array_filter(
            $config,
            fn (string $to): bool => LegacyRedirectMiddleware::isExternalTarget($to)
        ));

        foreach ($inMap as $target) {
            expect(array_key_exists($target, $externalTargets))->toBeTrue(
                "'{$target}' redirects off-site but is not declared in \$externalTargets."
            );
        }

        // The allowlist wp_safe_redirect() is given comes from the map, never from a request.
        expect(LegacyRedirectMiddleware::externalHosts($config))
            ->toBe(array_values(array_unique(array_map(
                fn (string $t): string => (string) parse_url($t, PHP_URL_HOST),
                $inMap
            ))));
    });

    test('no redirect key shadows a page being built in v2', function () {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        // Removed 2026-09-15 when each became a real v2 page. A key equal to a live page slug
        // would 301 that page away.
        foreach (['contractoragreement', 'services', 'store', 'hire-va'] as $liveSlug) {
            expect(array_key_exists($liveSlug, $config))->toBeFalse(
                "'{$liveSlug}' is a live v2 page slug; a redirect key of the same name would 301 the page away."
            );
        }
    });

    test('quarantined targets are still dead, so fixed ones get removed from the list', function () use ($wordPressPageTargets, $pendingTargets) {
        $routeUris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => trim($route->uri(), '/'))
            ->all();

        // Asserted explicitly so the test is not silently vacuous when the list is empty,
        // which is the desired steady state.
        expect($pendingTargets)->toBeArray();

        foreach (array_keys($pendingTargets) as $target) {
            $nowResolves = in_array($target, $routeUris, true) || isset($wordPressPageTargets[$target]);

            expect($nowResolves)->toBeFalse(
                "'{$target}' now resolves — remove it from \$pendingTargets."
            );
        }
    });

    test('the partner-dashboard redirect points at the real portal route', function () {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        expect($config['partner-dashboard'])->toBe('referrer-portal')
            ->and(Route::has('referrer.portal'))->toBeTrue();
    });
});

describe('every route() name referenced in code is registered (known-issues.md bug #1)', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
            Route::middleware([])->group($baseDir.'/routes/web.php');
            Route::getRoutes()->refreshNameLookups();
        }
    });

    /*
     * `redirect()->route('partner.portal')` shipped for months and threw on every click,
     * because nothing checked that the name existed. This scans routes and Blade views for
     * route() / redirect()->route() names and asserts each one is registered.
     */
    test('no code references an unregistered route name', function () {
        $baseDir = dirname(__DIR__, 2);
        $files = array_merge(
            glob($baseDir.'/routes/*.php') ?: [],
            glob($baseDir.'/resources/views/**/*.blade.php', GLOB_BRACE) ?: [],
            glob($baseDir.'/resources/views/*.blade.php') ?: [],
        );

        $referenced = [];
        foreach ($files as $file) {
            preg_match_all("/(?:->)?route\(\s*'([a-zA-Z0-9_.-]+)'/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $name) {
                $referenced[$name][] = basename($file);
            }
        }

        expect($referenced)->not->toBeEmpty('scan found no route() calls — the pattern is wrong');

        foreach ($referenced as $name => $files) {
            expect(Route::has($name))->toBeTrue(
                "route('{$name}') is referenced in ".implode(', ', array_unique($files))
                .' but no route is registered under that name.'
            );
        }
    });

    test('the partner directory CTAs resolve to the referrer portal and registration', function () {
        expect(Route::has('referrer.portal'))->toBeTrue()
            ->and(Route::has('referrer.register'))->toBeTrue();

        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/archive-rl_partner.blade.php');

        expect($blade)->toContain("route('referrer.register')")
            ->and($blade)->toContain("route('referrer.portal')")
            ->and($blade)->not->toContain('partner-dashboard');
    });
});
