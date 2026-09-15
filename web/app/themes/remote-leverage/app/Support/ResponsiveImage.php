<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Responsive <img> attributes for a **media-library** attachment.
 *
 * This is the uploads/EFS pipeline, not the `resources/images/pages` -> `public/images`
 * one in vite/theme-images.js. Nothing here writes to `public/`, and no filename is
 * renamed or hashed: WordPress already generated the intermediate sizes at upload time
 * and `BlockDefaults::preferWebp()` already makes a WebP sibling on demand. This only
 * wires the two together.
 *
 * The blog templates used to hand-build a single `src` — `medium_large` on the listing,
 * `full` on the article header, the untouched original in the talent carousel — with no
 * dimensions and no `srcset`. A 400 px phone therefore downloaded a 1024 px PNG for a
 * 220 px-wide card: 3.06 MB of images on one post and a 16.1 s LCP.
 *
 * Every candidate URL goes through `preferWebp()`, including the srcset ones. Rewriting
 * only `src` would be a no-op for any image that has a srcset, because a matching srcset
 * candidate always outranks `src`.
 */
class ResponsiveImage
{
    /**
     * Widths offered to the browser when the caller does not name its own. Entries that
     * resolve to the same pixel width collapse, so `large` and `full` produce one
     * candidate for the many blog images whose original is already 1024 px wide.
     *
     * @var list<string>
     */
    public const DEFAULT_CANDIDATES = ['medium', 'medium_large', 'large', 'full'];

    /**
     * @param  list<string>  $candidates
     * @return array{src: string, srcset: string, width: int, height: int}|null
     */
    public static function source(
        int $attachmentId,
        string $size = 'large',
        array $candidates = self::DEFAULT_CANDIDATES,
    ): ?array {
        if ($attachmentId <= 0 || ! function_exists('wp_get_attachment_image_src')) {
            return null;
        }

        $primary = wp_get_attachment_image_src($attachmentId, $size);

        if (! is_array($primary) || empty($primary[0])) {
            return null;
        }

        /** @var array<int, string> $sources keyed by intrinsic width */
        $sources = [];

        foreach (array_unique([...$candidates, $size]) as $candidate) {
            $entry = wp_get_attachment_image_src($attachmentId, $candidate);

            if (! is_array($entry) || empty($entry[0]) || empty($entry[1])) {
                continue;
            }

            $sources[(int) $entry[1]] = self::webp((string) $entry[0]);
        }

        ksort($sources);

        $srcset = [];

        foreach ($sources as $width => $url) {
            $srcset[] = $url.' '.$width.'w';
        }

        return [
            'src' => self::webp((string) $primary[0]),
            // One candidate is not a choice; emitting it would only repeat `src`.
            'srcset' => count($srcset) > 1 ? implode(', ', $srcset) : '',
            'width' => (int) $primary[1],
            'height' => (int) $primary[2],
        ];
    }

    /**
     * Ready-to-echo `src`/`srcset`/`sizes`/`width`/`height` for a Blade `<img>`.
     *
     * Falls back to a bare `src` for `$fallback` when the attachment cannot be resolved,
     * so a template never renders an `<img>` with no source at all.
     *
     * @param  list<string>  $candidates
     */
    public static function attributes(
        ?int $attachmentId,
        string $size,
        string $sizes,
        string $fallback = '',
        array $candidates = self::DEFAULT_CANDIDATES,
    ): string {
        $source = $attachmentId ? self::source($attachmentId, $size, $candidates) : null;

        if ($source === null) {
            return $fallback === '' ? '' : 'src="'.esc_url(self::https($fallback)).'"';
        }

        $attributes = ['src="'.esc_url($source['src']).'"'];

        if ($source['srcset'] !== '') {
            $attributes[] = 'srcset="'.esc_attr($source['srcset']).'"';
            $attributes[] = 'sizes="'.esc_attr($sizes).'"';
        }

        if ($source['width'] > 0 && $source['height'] > 0) {
            $attributes[] = 'width="'.$source['width'].'"';
            $attributes[] = 'height="'.$source['height'].'"';
        }

        return implode(' ', $attributes);
    }

    /**
     * Resolve a stored URL back to its attachment id.
     *
     * Author avatars live in user meta as a raw URL rather than an id, and some were
     * stored with an `http://` scheme — reported as mixed content by Lighthouse, and the
     * reason Best Practices sits at 79 on a single post. `attachment_url_to_postid()`
     * normalises the scheme itself, so the http form still resolves.
     */
    public static function idFromUrl(string $url): ?int
    {
        if ($url === '' || ! function_exists('attachment_url_to_postid')) {
            return null;
        }

        $id = (int) attachment_url_to_postid($url);

        return $id > 0 ? $id : null;
    }

    /**
     * Force a local URL onto the site's own scheme, so a stored `http://` upload URL does
     * not trip mixed content when we could not resolve it to an attachment.
     */
    public static function https(string $url): string
    {
        if ($url === '' || ! function_exists('set_url_scheme') || ! function_exists('home_url')) {
            return $url;
        }

        return (string) set_url_scheme($url, (string) parse_url(home_url(), PHP_URL_SCHEME) ?: 'https');
    }

    private static function webp(string $url): string
    {
        return BlockDefaults::preferWebp(self::https($url));
    }
}
