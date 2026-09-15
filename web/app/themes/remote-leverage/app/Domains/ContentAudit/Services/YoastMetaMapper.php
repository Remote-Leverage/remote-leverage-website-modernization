<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Services;

/**
 * Turns production's `yoast_head_json` REST object into the `_yoast_wpseo_*`
 * postmeta that reproduces it locally.
 *
 * There is no database access to production, so the REST head object is the only
 * source. It reports *rendered* output, not the raw postmeta behind it — Yoast
 * emits a value whether it came from a per-post override or from a post-type
 * template in Search Appearance. Carrying a template into postmeta would freeze
 * site configuration onto hundreds of individual posts, so values that repeat
 * across the corpus are treated as templates and skipped (see
 * {@see self::detectSiteDefaults()}). Title and meta description are always
 * carried: they are the parity fields the cutover gate is about, and none of
 * Yoast's site configuration is being migrated alongside them.
 *
 * Canonicals are rewritten from the production origin to the target origin.
 * Carrying them verbatim would point every page on a staging install at
 * production and de-index the copy under test.
 *
 * Robots directives are carried as production renders them, with one named
 * exception: slugs passed as `$forceIndex` keep their own indexability because
 * the page behind the slug was rebuilt in v2 and production's suppression of
 * the old one is stale. Such a page is left with no stored canonical either —
 * Yoast omits the canonical on a noindex URL, and its own self-canonical is the
 * right value once the page is indexable again.
 */
final class YoastMetaMapper
{
    /**
     * Every key this mapper owns. The importer deletes any of these that the
     * mapping does not produce, so a re-run converges rather than accumulating
     * stale overrides.
     *
     * @var list<string>
     */
    public const META_KEYS = [
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_canonical',
        '_yoast_wpseo_meta-robots-noindex',
        '_yoast_wpseo_meta-robots-nofollow',
        '_yoast_wpseo_meta-robots-adv',
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_opengraph-image',
        '_yoast_wpseo_twitter-title',
        '_yoast_wpseo_twitter-description',
        '_yoast_wpseo_twitter-image',
    ];

    /**
     * The `robots` directives Yoast stores in `meta-robots-adv`. Everything else
     * it reports there (`max-snippet`, `max-image-preview`, `max-video-preview`)
     * is site-wide configuration, not postmeta.
     *
     * @var list<string>
     */
    private const ADVANCED_ROBOTS = ['noimageindex', 'noarchive', 'nosnippet'];

    /**
     * The social fields that can be post-type templates rather than per-post
     * overrides, keyed by the head field they come from.
     *
     * @var list<string>
     */
    public const SOCIAL_FIELDS = [
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
    ];

    /**
     * The robots keys that express production's "keep this page out of search"
     * gesture. A slug in {@see self::$forceIndex} drops all three together:
     * production suppressed those pages as one decision, and that decision is
     * what went stale, not just its `noindex` half.
     *
     * @var list<string>
     */
    private const SUPPRESSION_KEYS = [
        '_yoast_wpseo_meta-robots-noindex',
        '_yoast_wpseo_meta-robots-nofollow',
        '_yoast_wpseo_meta-robots-adv',
    ];

    /**
     * Normalised slugs that keep their own indexability. Not promoted: the
     * constructor normalises what it is given.
     *
     * @var list<string>
     */
    private readonly array $forceIndex;

    /**
     * @param  string  $sourceOrigin  Production origin, e.g. `https://remoteleverage.com`.
     * @param  string  $targetOrigin  Where the migrated content lives, e.g. `https://remoteleverage-v2.test`.
     * @param  array<string,list<string>>  $siteDefaults  Social values to treat as templates, keyed by head field.
     * @param  list<string>  $forceIndex  Slugs whose production crawl suppression is stale and must not be carried.
     */
    public function __construct(
        private readonly string $sourceOrigin,
        private readonly string $targetOrigin,
        private readonly array $siteDefaults = [],
        array $forceIndex = [],
    ) {
        $this->forceIndex = array_values(array_unique(array_filter(
            array_map([self::class, 'normaliseSlug'], $forceIndex)
        )));
    }

    /**
     * Whether this slug keeps its own indexability regardless of what
     * production's head object says.
     *
     * A page rebuilt in v2 can share a slug with a production page that was
     * deliberately hidden; carrying that `noindex` across would silently keep
     * the rebuilt page out of the index, and nothing in the rendered head
     * distinguishes "hidden on purpose" from "hidden because the old page was
     * bad". The caller names those slugs.
     */
    public function indexForced(string $slug): bool
    {
        return in_array(self::normaliseSlug($slug), $this->forceIndex, true);
    }

    /**
     * Slugs are compared case-insensitively and without surrounding slashes, so
     * `/Referral-Program/` and `referral-program` are one slug.
     */
    public static function normaliseSlug(string $slug): string
    {
        return trim(strtolower(trim($slug)), '/');
    }

    /**
     * @param  array<string,mixed>  $head  A `yoast_head_json` object.
     * @param  string  $postTitle  The production post title, used to tell a real
     *                             OpenGraph override from Yoast echoing the title.
     * @param  string  $slug  The local slug, checked against the force-index list.
     * @return array<string,string> Meta key => value, keys with no value omitted.
     */
    public function map(array $head, string $postTitle = '', string $slug = ''): array
    {
        $meta = [];

        $title = $this->text($head['title'] ?? '');
        if ($title !== '') {
            $meta['_yoast_wpseo_title'] = $title;
        }

        $description = $this->text($head['description'] ?? '');
        if ($description !== '') {
            $meta['_yoast_wpseo_metadesc'] = $description;
        }

        // Yoast omits `canonical` entirely on noindex URLs, which is why 28 of
        // production's pages have none — absence here is not a missing canonical.
        $canonical = $this->rewriteUrl((string) ($head['canonical'] ?? ''));
        if ($canonical !== '') {
            $meta['_yoast_wpseo_canonical'] = $canonical;
        }

        $robots = is_array($head['robots'] ?? null) ? $head['robots'] : [];

        // '1' is noindex; Yoast's default '0' means "follow the post-type
        // setting", so index is expressed by leaving the key off.
        if (($robots['index'] ?? '') === 'noindex') {
            $meta['_yoast_wpseo_meta-robots-noindex'] = '1';
        }

        if (($robots['follow'] ?? '') === 'nofollow') {
            $meta['_yoast_wpseo_meta-robots-nofollow'] = '1';
        }

        $advanced = array_values(array_intersect(self::ADVANCED_ROBOTS, array_keys($robots)));
        if ($advanced !== []) {
            $meta['_yoast_wpseo_meta-robots-adv'] = implode(',', $advanced);
        }

        // A force-indexed slug drops the suppression wholesale rather than
        // never reading it: the keys still have to be *absent* from the result
        // so the importer deletes any that a previous run wrote. Everything
        // else production says about the page — title, description, social — is
        // still carried.
        if ($this->indexForced($slug)) {
            foreach (self::SUPPRESSION_KEYS as $key) {
                unset($meta[$key]);
            }
        }

        $ogTitle = $this->social($head, 'og_title');
        if ($ogTitle !== '' && $ogTitle !== $postTitle && $ogTitle !== $title) {
            $meta['_yoast_wpseo_opengraph-title'] = $ogTitle;
        }

        $ogDescription = $this->social($head, 'og_description');
        if ($ogDescription !== '' && $ogDescription !== $description) {
            $meta['_yoast_wpseo_opengraph-description'] = $ogDescription;
        }

        // Media stays on the production origin: the uploads path differs under
        // Bedrock (`/app/uploads/`), so a rewritten URL would 404 where the
        // production one still resolves.
        $ogImage = $this->social($head, 'og_image');
        if ($ogImage !== '') {
            $meta['_yoast_wpseo_opengraph-image'] = $ogImage;
        }

        // Yoast only renders twitter_* when it differs from the OpenGraph value,
        // so anything present here is already a deliberate divergence.
        foreach (['title', 'description', 'image'] as $field) {
            $value = $this->social($head, 'twitter_'.$field);
            if ($value !== '') {
                $meta['_yoast_wpseo_twitter-'.$field] = $value;
            }
        }

        return $meta;
    }

    /**
     * Rewrites a production URL onto the target origin, leaving genuinely
     * external URLs (a cross-domain canonical) untouched.
     */
    public function rewriteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $host = self::host($url);

        if ($host === '' || $host !== self::host($this->sourceOrigin)) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        // A bare origin canonical (production consolidates its duplicate landing
        // pages onto the homepage) has no path at all.
        if ($path === '') {
            $path = '/';
        }

        $suffix = isset($parts['query']) ? '?'.$parts['query'] : '';
        $suffix .= isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return rtrim($this->targetOrigin, '/').$path.$suffix;
    }

    /**
     * Host of a URL, with `www.` stripped so the two spellings of one site match.
     */
    public static function host(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return '';
        }

        return preg_replace('/^www\./i', '', strtolower($host)) ?? '';
    }

    /**
     * Finds the social values that are post-type templates rather than per-post
     * overrides, by looking for the same string across many posts.
     *
     * Yoast's Search Appearance holds a social title/description/image template
     * per post type, and the REST head reports the rendered result, so a template
     * shows up as one value repeated across the corpus. On production, one
     * OpenGraph description covers 252 of 355 items — that is configuration, not
     * 252 editorial decisions.
     *
     * @param  iterable<array<string,mixed>>  $heads
     * @return array<string,list<string>>
     */
    public static function detectSiteDefaults(iterable $heads, float $threshold = 0.05): array
    {
        $counts = [];
        $total = 0;

        foreach ($heads as $head) {
            $total++;

            foreach (self::SOCIAL_FIELDS as $field) {
                $value = self::value($head, $field);

                if ($value !== '') {
                    $counts[$field][$value] = ($counts[$field][$value] ?? 0) + 1;
                }
            }
        }

        // A small corpus makes a percentage meaningless — three posts sharing a
        // string is the floor at which "template" beats "coincidence".
        $minimum = max(3, (int) ceil($threshold * $total));
        $defaults = [];

        foreach ($counts as $field => $values) {
            foreach ($values as $value => $count) {
                if ($count >= $minimum) {
                    $defaults[$field][] = (string) $value;
                }
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string,mixed>  $head
     */
    private function social(array $head, string $field): string
    {
        $value = self::value($head, $field);

        if ($value === '' || in_array($value, $this->siteDefaults[$field] ?? [], true)) {
            return '';
        }

        return $value;
    }

    /**
     * Reads one head field as a string. `og_image` is a list of image objects;
     * only the first is ever used as the override.
     *
     * @param  array<string,mixed>  $head
     */
    private static function value(array $head, string $field): string
    {
        $raw = $head[$field] ?? '';

        if ($field === 'og_image') {
            $raw = is_array($raw) ? ($raw[0]['url'] ?? '') : '';
        }

        return is_string($raw) ? trim($raw) : '';
    }

    /**
     * REST returns titles and descriptions HTML-encoded. Stored raw, WordPress
     * escapes the entity again and the tag ships `&amp;#8211;` to the crawler.
     */
    private function text(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        return trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
