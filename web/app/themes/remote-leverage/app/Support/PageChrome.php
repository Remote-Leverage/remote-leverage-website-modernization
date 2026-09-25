<?php

declare(strict_types=1);

namespace App\Support;

use WP_Block_Patterns_Registry;
use WP_Post;

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
 *
 * An editor can override either decision per page from the Header & Footer panel in
 * the editor sidebar (App\Fields\PageChromeFields). That choice is post meta, so unlike
 * the content-derived answer it lives in the database: set it on production, which is
 * what every other environment is refreshed from. Left on Auto, nothing changes.
 */
class PageChrome
{
    /**
     * Post meta holding the editor's header and footer choices (see PageChromeFields).
     */
    public const HEADER_FIELD = '_rl_page_header';

    public const FOOTER_FIELD = '_rl_page_footer';

    /**
     * The stored value for "decide from the page content", and the default.
     */
    public const AUTO = 'auto';

    public const HEADER_SITE = 'site';

    public const HEADER_CTA = 'cta';

    public const HEADER_NONE = 'none';

    public const FOOTER_SLIM = 'slim';

    public const FOOTER_FULL = 'full';

    /**
     * The template that renders no site header at all. Predates the sidebar setting and
     * still works, but the setting wins when both are present.
     */
    private const NO_HEADER_TEMPLATE = 'template-landing.blade.php';

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
     * Pages that want the previous four-column footer (Talents / Careers / Products /
     * Resources) instead of the slim one emit this marker.
     *
     * The slim footer became the site-wide default on 2026-09-16 with the homepage rebuild.
     * Keeping the big one reachable per page is deliberate: it carries the only navigation to
     * a dozen role and resource pages, and a page that leans on that can ask for it back
     * without reverting the default for everyone.
     */
    private const FULL_FOOTER_MARKER = 'rl:full-footer';

    /**
     * How deep to follow `core/pattern` references. A full-page pattern
     * referencing section patterns is two levels; the cap stops a pattern that
     * (directly or indirectly) references itself from recursing forever.
     */
    private const MAX_PATTERN_DEPTH = 4;

    /**
     * Whether the current page renders `acf/hire-va-hero`.
     *
     * Used to emit the LCP image preloads from the layout head — `wp_head` has
     * already run by the time the block itself renders, so the block cannot
     * register them. Same pattern walk as the CTA-only header: page content is
     * a single pattern reference, and the hero sits inside it.
     */
    public static function usesHireVaHero(): bool
    {
        if (! is_singular()) {
            return false;
        }

        $post = get_post();

        if (! $post || ! is_string($post->post_content) || $post->post_content === '') {
            return false;
        }

        return self::contentHasBlock($post->post_content, 'acf/hire-va-hero');
    }

    /**
     * Whether a block comment appears in this content or in any pattern it references.
     *
     * Matched textually rather than via `has_block()` so unit tests can assert
     * against pattern files without booting WordPress.
     */
    public static function contentHasBlock(string $content, string $block, int $depth = 0): bool
    {
        if ($depth > self::MAX_PATTERN_DEPTH || trim($content) === '' || $block === '') {
            return false;
        }

        if (str_contains($content, '<!-- wp:'.$block.' ')) {
            return true;
        }

        foreach (self::patternSlugsIn($content) as $slug) {
            if (self::contentHasBlock(self::patternContent($slug), $block, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which header the current page renders: one of the HEADER_* constants.
     */
    public static function header(): string
    {
        $post = self::currentPost();

        if ($post === null) {
            return self::HEADER_SITE;
        }

        $landingTemplate = is_page_template(self::NO_HEADER_TEMPLATE)
            || get_page_template_slug($post) === self::NO_HEADER_TEMPLATE;

        return self::resolveHeader(
            self::override($post, self::HEADER_FIELD),
            $landingTemplate,
            (string) $post->post_content,
        );
    }

    /**
     * The editor's choice if they made one, then the no-header template, then the content.
     */
    public static function resolveHeader(string $override, bool $landingTemplate, string $content): string
    {
        if (in_array($override, [self::HEADER_SITE, self::HEADER_CTA, self::HEADER_NONE], true)) {
            return $override;
        }

        if ($landingTemplate) {
            return self::HEADER_NONE;
        }

        return self::contentTakesCtaOnlyHeader($content) ? self::HEADER_CTA : self::HEADER_SITE;
    }

    /**
     * Whether the current page takes the CTA-only header (sections.header-cta)
     * in place of the global site header.
     */
    public static function usesCtaOnlyHeader(): bool
    {
        return self::header() === self::HEADER_CTA;
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
     * Which footer the current page renders: FOOTER_SLIM or FOOTER_FULL.
     */
    public static function footer(): string
    {
        $post = self::currentPost();

        if ($post === null) {
            return self::FOOTER_SLIM;
        }

        return self::resolveFooter(
            self::override($post, self::FOOTER_FIELD),
            (string) $post->post_content,
        );
    }

    /**
     * The editor's choice if they made one, otherwise the content's marker.
     */
    public static function resolveFooter(string $override, string $content): string
    {
        if (in_array($override, [self::FOOTER_SLIM, self::FOOTER_FULL], true)) {
            return $override;
        }

        return self::contentHasMarker($content, self::FULL_FOOTER_MARKER)
            ? self::FOOTER_FULL
            : self::FOOTER_SLIM;
    }

    /**
     * Whether the current page asks for the previous four-column footer.
     */
    public static function usesFullFooter(): bool
    {
        return self::footer() === self::FOOTER_FULL;
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
     * The singular post being rendered, or null on archives, search and 404s.
     *
     * Content is not required: a page with an empty body can still carry a sidebar choice.
     */
    private static function currentPost(): ?WP_Post
    {
        if (! is_singular()) {
            return null;
        }

        $post = get_post();

        return $post instanceof WP_Post ? $post : null;
    }

    /**
     * The editor's stored choice for a field, or AUTO when none was made.
     */
    private static function override(WP_Post $post, string $field): string
    {
        $value = get_post_meta($post->ID, $field, true);

        return is_string($value) && $value !== '' ? $value : self::AUTO;
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
