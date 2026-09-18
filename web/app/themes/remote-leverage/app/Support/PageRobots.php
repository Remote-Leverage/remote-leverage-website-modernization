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
 * A page opts in by emitting a marker anywhere in its pattern, the same self-describing way
 * `PageChrome` picks a header. Page content is a single pattern reference, so the marker is
 * found by walking the pattern registry (see PageChrome::contentHasMarker).
 *
 * Two markers, because production uses both postures and they are not interchangeable:
 * `rl:noindex` for `noindex, nofollow`, and `rl:noindex-follow` for `noindex, follow` — the
 * latter still passes link equity through, which is what production serves on the client
 * onboarding guide and the recruiter checklists. Keeping both in the pattern means the
 * posture lives in git rather than in Yoast postmeta, which a database refresh would drop.
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
     * Emitted by a pattern that must not be indexed but whose links should still be followed.
     *
     * Checked before NOINDEX_MARKER, because `rl:noindex` is a prefix of this string and a
     * plain substring test would otherwise match it first and wrongly add `nofollow`.
     */
    public const NOINDEX_FOLLOW_MARKER = 'rl:noindex-follow';

    /**
     * Filter callback for `wp_robots`.
     *
     * @param  array<string, mixed>  $robots
     * @return array<string, mixed>
     */
    public static function filter(array $robots): array
    {
        $posture = self::$forced ?? self::currentPagePosture();

        if ($posture === null) {
            return $robots;
        }

        // Drop the positive directives core adds by default so the two cannot contradict
        // each other, then mirror production's posture exactly.
        unset($robots['index'], $robots['follow'], $robots['max-image-preview']);

        $robots['noindex'] = true;

        if ($posture === self::NOINDEX_MARKER) {
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    /**
     * A posture set by a route rather than discovered from page content.
     *
     * Routes have no post and no pattern, so `currentPagePosture()` — which starts with an
     * `is_singular()` check — can never speak for one. Without this a route is silently
     * indexable however it is written, which is the wrong default for the internal tools that
     * live on routes.
     */
    private static ?string $forced = null;

    /**
     * Keep the current route out of search results.
     *
     * Called from the route itself, before the view renders and therefore before `wp_head()`
     * fires. `$follow` mirrors the two markers: false gives `noindex, nofollow`, true gives
     * `noindex, follow` for a page whose links should still pass equity.
     */
    public static function forceNoindex(bool $follow = false): void
    {
        self::$forced = $follow ? self::NOINDEX_FOLLOW_MARKER : self::NOINDEX_MARKER;
    }

    /**
     * Drop a forced posture, so one test cannot decide the next.
     */
    public static function clearForced(): void
    {
        self::$forced = null;
    }

    /**
     * Which marker the page being rendered declared, or null for neither.
     */
    public static function currentPagePosture(): ?string
    {
        if (! is_singular()) {
            return null;
        }

        $post = get_post();

        if (! $post || ! is_string($post->post_content) || $post->post_content === '') {
            return null;
        }

        // Longest marker first: `rl:noindex` is a prefix of `rl:noindex-follow`.
        foreach ([self::NOINDEX_FOLLOW_MARKER, self::NOINDEX_MARKER] as $marker) {
            if (PageChrome::contentHasMarker($post->post_content, $marker)) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * Whether the page being rendered declared itself noindex.
     */
    public static function currentPageIsNoindex(): bool
    {
        return (self::$forced ?? self::currentPagePosture()) !== null;
    }
}
