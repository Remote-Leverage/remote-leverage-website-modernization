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
 * Generating it here fixes that twice over:
 *
 *  - Yoast appends the sitemap itself (`robots_txt`, priority 99999) using its own base URL,
 *    so the line is correct per environment with nothing hardcoded.
 *  - When the site is not public — `DISALLOW_INDEXING` on dev, staging and the preview host —
 *    core emits a blanket `Disallow: /` and this leaves it alone. The old file served a
 *    permissive allow-list on hosts that were meant to be entirely excluded.
 *
 * The directives themselves are unchanged from that file: core lives under `/wp/` in this
 * layout, and the authenticated portals and machine endpoints have nothing indexable.
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
     * @param  bool  $public  The `blog_public` option — false on every noindex environment.
     */
    public static function filter(string $output, $public): string
    {
        // Not public: core has already emitted `Disallow: /`, which is stricter than anything
        // below and must not be softened by appending Allow rules after it.
        if (! $public) {
            return $output;
        }

        $lines = [
            rtrim($output),
            '',
            '# WordPress core lives under /wp/ in this Bedrock layout, not at the web root.',
            'Disallow: /wp/wp-admin/',
            'Allow: /wp/wp-admin/admin-ajax.php',
            '',
            '# Application routes with no indexable content.',
        ];

        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        return implode("\n", $lines)."\n";
    }
}
