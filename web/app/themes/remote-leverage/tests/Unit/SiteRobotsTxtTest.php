<?php

declare(strict_types=1);

use App\Support\SiteRobotsTxt;

/**
 * Covers the `robots.txt` generated on 2026-09-17 in place of the static `web/robots.txt`.
 *
 * A physical file in the Bedrock web root wins over WordPress's rewrite, so that file was the
 * one every host answered with — and it hardcoded `Sitemap: https://remoteleverage.com/…`.
 * That is the apex, which meant staging and the production preview host were advertising the
 * *legacy* site's sitemap to crawlers. No value of a hardcoded line is right on every host, so
 * the fix is for the line not to be there at all: Yoast appends it at priority 99999 from its
 * own base URL.
 *
 * Two things therefore have to hold, and neither is visible by reading the deployed file on a
 * dev machine — where the site is noindexed and the output looks nothing like production's:
 *
 *   · a non-public host is left strictly alone, because core has already written the blanket
 *     `Disallow: /` and anything appended after it can only loosen it;
 *   · the public output carries the directives and no host or sitemap of its own.
 */
describe('robots.txt is generated per host', function () {
    $core = "User-agent: *\nDisallow: /\n";

    test('a non-public host is returned byte for byte', function () use ($core) {
        // Not "contains what core wrote" — identical. An `Allow:` line appended below core's
        // blanket disallow is honoured by Google, so softening staging or the preview host is
        // a single careless line away, and it fails silently: the page still renders, it just
        // becomes indexable. That is how a preview host outranks the real one.
        expect(SiteRobotsTxt::filter($core, false))->toBe($core);
    });

    test('the falsy option value core actually passes is recognised', function () use ($core) {
        // do_robots() hands over get_option('blog_public') raw, and the option is stored as a
        // string. A `=== false` check would sail straight past '0' and publish the allow-list
        // on every noindex environment, which is precisely the state being fixed here.
        expect(SiteRobotsTxt::filter($core, '0'))->toBe($core)
            ->and(SiteRobotsTxt::filter($core, 0))->toBe($core)
            ->and(SiteRobotsTxt::filter($core, ''))->toBe($core);
    });

    test('a public host keeps what came before and appends the directives', function () {
        $output = SiteRobotsTxt::filter("User-agent: *\nDisallow:\n", '1');

        expect($output)->toStartWith("User-agent: *\nDisallow:")
            ->and($output)->toContain('Disallow: /wp/wp-admin/')
            ->and($output)->toContain('Allow: /wp/wp-admin/admin-ajax.php');

        foreach (['/referrer-portal', '/referrer-register', '/referral-dashboard', '/partner-dashboard', '/live-call/connect', '/api/'] as $path) {
            expect($output)->toContain('Disallow: '.$path);
        }
    });

    test('admin-ajax is allowed after wp-admin is disallowed, not before', function () {
        $output = SiteRobotsTxt::filter("User-agent: *\n", '1');

        // Order is load-bearing for the pair: the Allow is an exception carved out of the
        // Disallow above it, and admin-ajax.php is a real front-end endpoint that blocks
        // rendering for crawlers if it is excluded with the rest of wp-admin.
        expect(strpos($output, 'Allow: /wp/wp-admin/admin-ajax.php'))
            ->toBeGreaterThan(strpos($output, 'Disallow: /wp/wp-admin/'));
    });

    test('nothing hostname-specific or sitemap-shaped is emitted', function () {
        $output = SiteRobotsTxt::filter("User-agent: *\n", '1');

        // The entire reason this moved out of a static file. Yoast writes the Sitemap line at
        // priority 99999 with the base URL of whichever host is serving, so a line here would
        // not merely be redundant — it would be the wrong one on two of three environments.
        expect($output)->not->toContain('Sitemap')
            ->and($output)->not->toContain('remoteleverage.com');
    });

    test('the filter is registered with both arguments', function () {
        // `$public` is the second argument, and add_filter() defaults to passing one. Registered
        // with the default, $public would arrive as null on every host and the function would
        // return core's output untouched everywhere — a no-op that looks wired up.
        expect(file_get_contents(dirname(__DIR__, 2).'/app/setup.php'))
            ->toContain("add_filter('robots_txt', [SiteRobotsTxt::class, 'filter'], 10, 2);");
    });
});
