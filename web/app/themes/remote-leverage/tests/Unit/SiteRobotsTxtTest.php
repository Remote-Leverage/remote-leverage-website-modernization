<?php

declare(strict_types=1);

use App\Support\SiteRobotsTxt;

/**
 * Covers the `robots.txt` generated on 2026-09-17 in place of the static `web/robots.txt`.
 *
 * A physical file in the Bedrock web root wins over WordPress's rewrite, so that file was the
 * one every host answered with — and it hardcoded `Sitemap: https://remoteleverage.com/…`.
 * That is the apex, which meant staging and the production preview host were advertising the
 * *legacy* site's sitemap to crawlers. No hardcoded value is right on every host, so the fix is
 * for the line not to be there at all: Yoast appends it at priority 99999 from its own base URL.
 *
 * What is asserted here is mostly what the filter must *not* do, because each of those was got
 * wrong once and none of them is visible from a dev machine:
 *
 *   · it must not vary on `$public`. Core stopped emitting a blanket `Disallow: /` for
 *     non-public sites in 5.3 — `DISALLOW_INDEXING` works through the `noindex` meta instead —
 *     so an early return on non-public hosts does not defer to a stricter rule, it just means
 *     the filter contributes nothing on the three environments that are not production;
 *   · it must not emit its own wp-admin pair, which core already writes from `admin_url()`;
 *   · and it must not name a host or a sitemap.
 */
describe('robots.txt is generated per host', function () {
    // What core's do_robots() actually produces in this layout: admin_url() is Bedrock-aware,
    // so the pair already points at /wp/wp-admin/ before this filter ever runs.
    $core = "User-agent: *\nDisallow: /wp/wp-admin/\nAllow: /wp/wp-admin/admin-ajax.php\n";

    test('it keeps what core wrote and appends the portal and API disallows', function () use ($core) {
        $output = SiteRobotsTxt::filter($core, '1');

        expect($output)->toStartWith("User-agent: *\nDisallow: /wp/wp-admin/\nAllow: /wp/wp-admin/admin-ajax.php");

        foreach (['/referrer-portal', '/referrer-register', '/referral-dashboard', '/partner-dashboard', '/live-call/connect', '/api/'] as $path) {
            expect($output)->toContain("\nDisallow: ".$path."\n");
        }
    });

    test('the directives do not depend on the blog_public option', function () use ($core) {
        // The whole point of the 2026-09-17 rewrite. `$public` arrives as the raw option value,
        // so an early return guarded on it fired on dev, staging and the preview host — the
        // three environments nobody reads robots.txt on, which is why it went unnoticed.
        $public = SiteRobotsTxt::filter($core, '1');

        expect(SiteRobotsTxt::filter($core, '0'))->toBe($public)
            ->and(SiteRobotsTxt::filter($core, 0))->toBe($public)
            ->and(SiteRobotsTxt::filter($core, false))->toBe($public)
            ->and(SiteRobotsTxt::filter($core, ''))->toBe($public);
    });

    test('it adds no wp-admin rule of its own', function () use ($core) {
        // Core writes that pair already. A second copy is not harmful to a crawler, but it is
        // the visible symptom of this filter assuming it owns lines it does not, and it is the
        // one duplicate a human reading robots.txt would report as a bug.
        expect(substr_count(SiteRobotsTxt::filter($core, '1'), 'Disallow: /wp/wp-admin/'))->toBe(1)
            ->and(substr_count(SiteRobotsTxt::filter($core, '1'), 'Allow: /wp/wp-admin/admin-ajax.php'))->toBe(1);

        // And with nothing handed in, it contributes no wp-admin line at all.
        expect(SiteRobotsTxt::filter("User-agent: *\n", '1'))->not->toContain('wp-admin');
    });

    test('nothing hostname-specific or sitemap-shaped is emitted', function () use ($core) {
        // The entire reason this moved out of a static file. Yoast writes the Sitemap line at
        // priority 99999 with the base URL of whichever host is serving, so a line here would
        // not merely be redundant — it would be the wrong one on two of three environments.
        expect(SiteRobotsTxt::filter($core, '1'))->not->toContain('Sitemap')
            ->and(SiteRobotsTxt::filter($core, '1'))->not->toContain('remoteleverage.com');
    });

    test('the filter is registered with both arguments', function () {
        // add_filter() passes one argument by default, and filter() declares two without a
        // default — so registering with the default is not a subtle behaviour change, it is an
        // ArgumentCountError on every request that serves robots.txt.
        expect(file_get_contents(dirname(__DIR__, 2).'/app/setup.php'))
            ->toContain("add_filter('robots_txt', [SiteRobotsTxt::class, 'filter'], 10, 2);");
    });
});
