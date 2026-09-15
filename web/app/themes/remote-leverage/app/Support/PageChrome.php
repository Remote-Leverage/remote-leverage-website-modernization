<?php

declare(strict_types=1);

namespace App\Support;

use WP_Block_Patterns_Registry;

/**
 * Decides which header a page gets.
 *
 * Most pages take the global site header (logo + nav + consultation pill) from
 * `sections.header`. The hire-va landing pages do not: `acf/hire-va-hero`
 * renders its own logo-plus-single-CTA bar as the first thing in the hero, and
 * production serves those pages with no site nav at all — a conversion page
 * deliberately offers no way out. Rendering both produced two stacked headers.
 *
 * Rather than relying on a page template assignment (database state, lost on a
 * refresh) or a hand-maintained slug list, this inspects what the page actually
 * renders. Page content is a single pattern reference, and the hero sits one or
 * two levels down inside that pattern, so the pattern registry is walked to find
 * it.
 */
class PageChrome
{
    /**
     * Blocks whose pages take the CTA-only header instead of the site header.
     */
    private const CTA_ONLY_HEADER_BLOCKS = [
        'acf/hire-va-hero',
        'acf/consult-landing-hero',
    ];

    /**
     * Patterns whose hero is hand-written rather than a block can opt in by emitting
     * this marker. Keeps the decision with the page that makes it, rather than in a
     * hand-maintained slug list here — same reasoning as the block list above.
     *
     * Used by the P4 campaign families whose heroes are bespoke markup:
     * resources/patterns/steal-campaign.php and resources/patterns/va-roles-landing.php.
     */
    private const CTA_ONLY_HEADER_MARKER = 'rl:cta-only-header';

    /**
     * How deep to follow `core/pattern` references. A full-page pattern
     * referencing section patterns is two levels; the cap stops a pattern that
     * (directly or indirectly) references itself from recursing forever.
     */
    private const MAX_PATTERN_DEPTH = 4;

    /**
     * Whether the current page takes the CTA-only header (sections.header-cta)
     * in place of the global site header.
     */
    public static function usesCtaOnlyHeader(): bool
    {
        if (! is_singular()) {
            return false;
        }

        $post = get_post();

        if (! $post || ! is_string($post->post_content) || $post->post_content === '') {
            return false;
        }

        return self::contentTakesCtaOnlyHeader($post->post_content, 0);
    }

    /**
     * Walk parsed block content, following pattern references, looking for a
     * block that brings its own header.
     */
    public static function contentTakesCtaOnlyHeader(string $content, int $depth = 0): bool
    {
        if ($depth > self::MAX_PATTERN_DEPTH || trim($content) === '') {
            return false;
        }

        if (str_contains($content, self::CTA_ONLY_HEADER_MARKER)) {
            return true;
        }

        foreach (self::CTA_ONLY_HEADER_BLOCKS as $block) {
            if (str_contains($content, '<!-- wp:'.$block.' ')) {
                return true;
            }
        }

        foreach (self::patternSlugsIn($content) as $slug) {
            if (self::contentTakesCtaOnlyHeader(self::patternContent($slug), $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a marker string appears in this content or in any pattern it references.
     *
     * The pattern walk is the reusable half of this class: page content is a single pattern
     * reference, so anything a page wants to declare about itself has to be followed through
     * the registry to be seen. Exposed so other page-level decisions (see App\Support\PageRobots)
     * can be declared the same self-describing way rather than by a hand-maintained slug list.
     */
    public static function contentHasMarker(string $content, string $marker, int $depth = 0): bool
    {
        if ($depth > self::MAX_PATTERN_DEPTH || trim($content) === '' || $marker === '') {
            return false;
        }

        if (str_contains($content, $marker)) {
            return true;
        }

        foreach (self::patternSlugsIn($content) as $slug) {
            if (self::contentHasMarker(self::patternContent($slug), $marker, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Slugs of every `core/pattern` reference in a chunk of block markup.
     *
     * Matched textually rather than via parse_blocks() so this stays usable
     * before blocks are parsed and in unit tests without WordPress.
     *
     * @return array<int, string>
     */
    private static function patternSlugsIn(string $content): array
    {
        preg_match_all(
            '/<!--\s*wp:pattern\s+(\{.*?\})\s*\/-->/s',
            $content,
            $matches,
        );

        $slugs = [];

        foreach ($matches[1] ?? [] as $json) {
            $attrs = json_decode($json, true);

            if (is_array($attrs) && ! empty($attrs['slug']) && is_string($attrs['slug'])) {
                $slugs[] = $attrs['slug'];
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * The registered content for a pattern slug, or an empty string when the
     * pattern is not registered (a typo, or a pattern file removed).
     */
    private static function patternContent(string $slug): string
    {
        if (! class_exists(WP_Block_Patterns_Registry::class)) {
            return '';
        }

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

        return is_array($pattern) && isset($pattern['content']) && is_string($pattern['content'])
            ? $pattern['content']
            : '';
    }
}
