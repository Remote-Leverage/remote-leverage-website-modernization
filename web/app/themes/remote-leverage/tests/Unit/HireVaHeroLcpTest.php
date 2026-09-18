<?php

declare(strict_types=1);

use App\Support\PageChrome;

/**
 * /hire-va-4/ LCP is the hero background. PSI mobile on 2026-09-18 spent 5.7s
 * (staging) / 13.1s (production) waiting on hire-va-bg.webp because it had no
 * fetchpriority, no preload, and no mobile crop. Role pages already mark their
 * LCP img high; this file pins the same treatment on acf/hire-va-hero.
 */
describe('the hire-va hero is discoverable as LCP', function () {
    test('the view uses a picture element with fetchpriority high and a 750px source', function () {
        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/hire-va-hero.blade.php');

        expect($blade)
            ->toContain('<picture>')
            ->toContain('fetchpriority="high"')
            ->toContain('decoding="async"')
            ->toContain('hireVaHeroBackground()')
            ->toContain('(max-width: 1023px)');

        preg_match('/<picture>.*?<\/picture>/s', $blade, $match);

        expect($match[0] ?? '')
            ->not->toBe('')
            ->not->toContain('loading="lazy"');
    });

    test('theme-images emits the 750px sibling from the 1366px original', function () {
        $plugin = (string) file_get_contents(dirname(__DIR__, 2).'/vite/theme-images.js');

        expect($plugin)
            ->toContain("'hire-va-4/hire-va-bg.webp'")
            ->toContain('width: 750')
            ->toContain('writeResponsiveVariants');
    });

    test('the hire-va-4 hero pattern is what PageChrome looks for', function () {
        $pattern = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/hire-va-4-hero.php');

        expect(PageChrome::contentHasBlock($pattern, 'acf/hire-va-hero'))->toBeTrue()
            ->and(PageChrome::contentHasBlock($pattern, 'acf/consult-landing-hero'))->toBeFalse()
            ->and(PageChrome::contentHasBlock('<!-- wp:paragraph -->', 'acf/hire-va-hero'))->toBeFalse();
    });
});
