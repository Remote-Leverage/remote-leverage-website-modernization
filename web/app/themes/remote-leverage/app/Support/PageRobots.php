<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Keeps individual pages out of search results.
 *
 * Bedrock's `bedrock-disallow-indexing` mu-plugin already noindexes every non-production
 * environment, which is why a local page looks correctly excluded whether or not anything
 * here runs. That protection disappears the moment the site is production, so a page that
 * must never be indexed needs its own rule — this one.
 *
 * A page opts in by emitting the `rl:noindex` marker anywhere in its pattern, the same
 * self-describing way `PageChrome` picks a header. Page content is a single pattern
 * reference, so the marker is found by walking the pattern registry (see
 * PageChrome::contentHasMarker).
 *
 * Note this controls indexing only. It is not access control: a noindex page is still
 * served to anyone who has the URL.
 */
class PageRobots
{
    /**
     * Emitted by a pattern that must not be indexed or followed.
     */
    public const NOINDEX_MARKER = 'rl:noindex';

    /**
     * Filter callback for `wp_robots`.
     *
     * @param  array<string, mixed>  $robots
     * @return array<string, mixed>
     */
    public static function filter(array $robots): array
    {
        if (! self::currentPageIsNoindex()) {
            return $robots;
        }

        // Mirror production's `noindex, nofollow` exactly, and drop the positive
        // directives core adds by default so the two cannot contradict each other.
        unset($robots['index'], $robots['follow'], $robots['max-image-preview']);

        $robots['noindex'] = true;
        $robots['nofollow'] = true;

        return $robots;
    }

    /**
     * Whether the page being rendered declared itself noindex.
     */
    public static function currentPageIsNoindex(): bool
    {
        if (! is_singular()) {
            return false;
        }

        $post = get_post();

        if (! $post || ! is_string($post->post_content) || $post->post_content === '') {
            return false;
        }

        return PageChrome::contentHasMarker($post->post_content, self::NOINDEX_MARKER);
    }
}
