<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides whether the site header should sit transparently over the page's hero.
 *
 * Pages whose first section is a dark, full-bleed hero need the header to float over it in
 * white rather than sit on its own pale band. Rather than a per-page setting in the database,
 * this is derived from the content: the page references a pattern, the pattern renders one of
 * the dark hero blocks, so the answer is already in git.
 */
class HeaderMode
{
    /**
     * Hero blocks that paint a dark full-bleed band at the very top of the page.
     *
     * @var array<int, string>
     */
    public const DARK_HERO_BLOCKS = [
        'acf/partner-hero',
        'acf/contractor-payments-hero',
    ];

    public static function isOverDarkHero(): bool
    {
        if (! function_exists('is_singular') || ! is_singular()) {
            return false;
        }

        $post = function_exists('get_post') ? get_post() : null;

        if (! $post || ! isset($post->post_content)) {
            return false;
        }

        return self::contentOpensWithDarkHero((string) $post->post_content);
    }

    /**
     * Pages authored the house way hold a single pattern reference, so follow it before
     * looking for hero blocks. Only the opening section counts — a dark band further down
     * the page is not what the header sits on.
     */
    public static function contentOpensWithDarkHero(string $content): bool
    {
        if (preg_match('#wp:pattern\s*\{"slug":"([^"]+)"#', $content, $m) === 1) {
            $content = self::patternContent($m[1]) ?? $content;
        }

        $opening = substr($content, 0, 2000);

        foreach (self::DARK_HERO_BLOCKS as $block) {
            $at = strpos($opening, $block);

            if ($at === false) {
                continue;
            }

            if (self::blockIsLightToned($content, $at)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * `acf/partner-hero` paints either the dark partner gradient or production's pale
     * #F4F6FC band, chosen by its `tone` field. Only the dark tone wants a white header
     * floating over it — over the light band the inverted logo and white nav links are
     * invisible, which is what `/ecommerce-virtual-assistant/` shipped.
     *
     * The tone sits in the block comment's own JSON, which can run past the opening
     * window, so it is read from the full content rather than from `$opening`.
     */
    protected static function blockIsLightToned(string $content, int $blockAt): bool
    {
        $end = strpos($content, '/-->', $blockAt);

        $comment = $end === false
            ? substr($content, $blockAt)
            : substr($content, $blockAt, $end - $blockAt);

        return str_contains($comment, '"tone":"light"')
            || str_contains($comment, '\"tone\":\"light\"');
    }

    protected static function patternContent(string $slug): ?string
    {
        if (! class_exists(\WP_Block_Patterns_Registry::class)) {
            return null;
        }

        $pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

        return is_array($pattern) && isset($pattern['content']) ? (string) $pattern['content'] : null;
    }
}
