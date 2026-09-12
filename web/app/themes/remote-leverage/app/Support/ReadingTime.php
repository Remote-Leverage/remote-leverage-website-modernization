<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Estimated reading time for a post, in whole minutes.
 *
 * Article cards and the post header both showed a hardcoded "5 min read"
 * before this — the number is worth having be true, since it is the one piece
 * of metadata a reader uses to decide whether to start.
 */
class ReadingTime
{
    /**
     * Words per minute. 225 is the usual figure for adults reading prose on a
     * screen; the migrated posts are all prose, with no code to slow anyone down.
     */
    public const WORDS_PER_MINUTE = 225;

    /**
     * @param  string  $content  Post content, raw or rendered.
     */
    public static function minutes(string $content): int
    {
        $text = strip_shortcodes($content);

        // Tags have to become whitespace, not vanish: "<p>a</p><p>b</p>" strips
        // to "ab", which counts a post built from block paragraphs as one word.
        // This also clears the <!-- wp:* --> block delimiters in raw content.
        $text = (string) preg_replace('/<[^>]*+>/', ' ', $text);

        $words = str_word_count(wp_strip_all_tags($text));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /**
     * Reading time for a post, ready to print.
     */
    public static function label(?int $postId = null): string
    {
        $post = get_post($postId);

        if (! $post) {
            return '';
        }

        return sprintf(
            /* translators: %d is the estimated reading time in minutes */
            __('%d min read', 'remote-leverage'),
            self::minutes($post->post_content),
        );
    }
}
