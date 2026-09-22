<?php

declare(strict_types=1);

/**
 * `/` LCP on mobile is the Inter Display <h1>, not the talent-card fan.
 *
 * The fan is `hidden lg:block`. Chrome still fetches `loading=eager` images
 * inside `display:none`, and WordPress then stamps `fetchpriority=high` on the
 * first one — which is what a 2026-09-22 staging PSI run (Moto G / Slow 4G)
 * measured as FCP 1.6 s / LCP 6.5 s: ~520 KB of portraits on a path that does
 * not paint them.
 */
describe('the homepage hero keeps desktop portraits off the mobile LCP path', function () {
    test('talent-card portraits are lazy and never fetchpriority=high', function () {
        $partial = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/blocks/partials/home-hero-card.blade.php'
        );

        preg_match_all('/<img\b[^>]*>/', $partial, $matches);
        $imgs = $matches[0];

        expect($imgs)->not->toBeEmpty();

        foreach ($imgs as $img) {
            expect($img)->not->toContain('fetchpriority');
        }

        $portrait = end($imgs);

        expect($portrait)
            ->toContain('$card[\'photo\']')
            ->toContain('loading="lazy"')
            ->not->toContain('loading="eager"');
    });

    test('the role-page composite stays the LCP image and does not decode async', function () {
        $blade = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/blocks/home-hero.blade.php'
        );

        preg_match('/@if \(\$isImage && \$heroImage\).*?<img\b[^>]*>/s', $blade, $match);
        $img = $match[0] ?? '';

        expect($img)
            ->not->toBe('')
            ->toContain('fetchpriority="high"')
            ->not->toContain('loading="lazy"')
            ->not->toContain('decoding="async"');
    });
});
