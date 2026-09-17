<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The site's `robots.txt`, served by WordPress rather than as a static file.
 *
 * It used to be `web/robots.txt`, a physical file in the Bedrock web root. A physical file
 * takes precedence over WordPress's rewrite, which made it the one that answered — and it
 * carried a hardcoded `Sitemap: https://remoteleverage.com/sitemap_index.xml`. That is the
 * apex, so staging and the production preview host were both advertising the *legacy* site's
 * sitemap. A static file cannot know which host is serving it, so there was no value of that
 * line that would have been right everywhere.
 *
 * Generating it here lets Yoast append the sitemap itself (`robots_txt`, priority 99999)
 * using its own base URL, so the line is correct per environment with nothing hardcoded.
 *
 * Two things this deliberately does NOT do, both of which the first version got wrong:
 *
 * It does not emit a wp-admin rule. Core's `do_robots()` already writes the `Disallow:` and
 * `Allow:` pair from `admin_url()`, which is Bedrock-aware and correctly yields `/wp/wp-admin/`
 * in this layout. Adding our own produced a duplicate pair.
 *
 * It does not add `Disallow: /` when the site is non-public, and it does not vary on `$public`
 * at all. Core stopped emitting a blanket disallow for non-public sites in 5.3, on the
 * reasoning that a crawler told not to fetch a page never reads the `noindex` meta on it, which
 * can strand already-indexed URLs in the index. `DISALLOW_INDEXING` keeps dev, staging and the
 * preview host out through that meta tag, which is the mechanism core wants doing this job —
 * see known-issues.md #4. Skipping these directives on those hosts bought nothing and meant
 * this filter contributed exactly nothing on the three environments that are not production.
 */
class SiteRobotsTxt
{
    /**
     * Paths with no indexable content: authenticated portals and machine endpoints.
     *
     * @var list<string>
     */
    private const DISALLOW = [
        '/referrer-portal',
        '/referrer-register',
        '/referral-dashboard',
        '/partner-dashboard',
        '/live-call/connect',
        '/api/',
    ];

    /**
     * Filter callback for `robots_txt`.
     *
     * @param  string  $output  What core (and any earlier filter) has produced so far.
     * @param  bool  $public  The `blog_public` option. Accepted because the filter passes it,
     *                        and deliberately unused — see the class docblock.
     */
    public static function filter(string $output, $public): string
    {
        $lines = [
            rtrim($output),
            '',
            '# Application routes with no indexable content.',
        ];

        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        return implode("\n", $lines)."\n";
    }
}
