<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides which stylesheets a request needs, and in what order.
 *
 * `resources/css/blog.css` is production's rl-elementor-blocks stylesheet, ported verbatim so
 * the blog index and article pages match. It used to be `@import`ed into app.css, which put
 * 21.6 KB minified — 4.5 KB gzipped, about a tenth of the render-blocking stylesheet — on every
 * marketing page for markup those pages never render.
 *
 * Its reach is narrower than it looks. Only three views use the classes it defines, and all
 * three are blog templates:
 *
 *   - `partials/blog-index.blade.php`    — from index.blade.php and archive.blade.php
 *   - `partials/content-single.blade.php` — from single.blade.php, for `post` only
 *   - `partials/talent-carousel.blade.php` — included by content-single, in the article sidebar
 *
 * `acf/talent-carousel` looks like a fourth but is not: it is styled from app.css
 * (`.rl-profile-card`) and only shares a class *prefix* with the article partial.
 * `case_study` and `rl_partner` have their own single and archive templates and use none of
 * this. tests/Unit/BlogStylesTest.php pins that list, so a view that starts using blog.css
 * classes fails the build rather than shipping unstyled.
 */
class BlogStyles
{
    /**
     * Views that use classes defined in blog.css, relative to `resources/views/`.
     *
     * Read only by the test, which recomputes the set from the stylesheet and the views on
     * disk and asserts it still matches. If that test fails, a view has started depending on
     * blog.css and isNeeded() below probably has to grow a case.
     */
    public const BLOG_STYLED_VIEWS = [
        'partials/blog-index.blade.php',
        'partials/content-single.blade.php',
        'partials/talent-carousel.blade.php',
    ];

    /**
     * Whether this request renders anything blog.css styles.
     *
     * The archive clause excludes post-type archives because `case_study` and `rl_partner`
     * ship their own `archive-<type>` templates; every other archive — category, tag, author,
     * date, and any taxonomy added later — falls through to `archive.blade.php`, which
     * includes `partials.blog-index`.
     */
    public static function isNeeded(): bool
    {
        return is_home()
            || is_singular('post')
            || (is_archive() && ! is_post_type_archive());
    }

    /**
     * The Vite entry points for this request.
     *
     * blog.css comes first, and that is load-bearing rather than cosmetic. It declares no
     * `@layer`, so its rules beat every Tailwind layer wherever they collide — and app.css's
     * own hand-written rules are unlayered too, so against those the winner of an
     * equal-specificity collision is decided by source order alone. As an `@import` at the top
     * of app.css it sat above them and lost those ties; emitting it first keeps that.
     *
     * @return array<int, string>
     */
    public static function entryPoints(): array
    {
        return array_merge(
            self::isNeeded() ? ['resources/css/blog.css'] : [],
            ['resources/css/app.css', 'resources/js/app.js'],
        );
    }
}
