<?php

declare(strict_types=1);

/**
 * Guards the `photo-split` card shape added for /reviews/ on 2026-09-16.
 *
 * Stakeholder QA (transfer-list row 28) reported the blocks below the review
 * videos "looking like the homepage". They were: /reviews/ called
 * renderRolesPricingGrid() with no arguments and got the default 4-up
 * photo-on-top grid, where production runs a 2-up card with the photo on the
 * left and a dark pill CTA.
 */
describe('roles-pricing-grid card shapes', function () {
    test('scalar overrides carry their ACF field key', function () {
        // Without the _key the value rides on the block data array alone, which is how
        // an override gets silently ignored elsewhere in this file. renderRolesPricingGrid()
        // itself cannot run here (it needs WP_CONTENT_DIR), so this pins the wiring.
        $src = file_get_contents(__DIR__.'/../../app/Support/BlockDefaults.php');

        expect($src)->toContain("foreach (['variant', 'columns'] as \$key)")
            ->and($src)->toContain("\$merged['_'.\$key] = 'field_roles_pricing_grid_block_'.\$key;");
    });

    test('the template branches on all three shapes', function () {
        $blade = file_get_contents(__DIR__.'/../../resources/views/blocks/roles-pricing-grid.blade.php');

        // The production /reviews/ card: 2-up, photo left, dark pill CTA, 28px title
        // (measured off production with getComputedStyle, not estimated).
        expect($blade)->toContain("\$isPhotoSplit = \$shape === 'photo-split'")
            ->and($blade)->toContain('rounded-pill bg-black')
            ->and($blade)->toContain('text-[28px]')
            ->and($blade)->toContain("'text-center' => ! \$isPhotoSplit");
    });

    test('/reviews/ asks for the production shape', function () {
        $pattern = file_get_contents(__DIR__.'/../../patterns/reviews-roles-pricing.php');

        expect($pattern)->toContain("'variant' => 'photo-split'")
            ->and($pattern)->toContain("'columns' => '2'");
    });

    test('/reviews/ closes with production\'s consultation heading', function () {
        $full = file_get_contents(__DIR__.'/../../patterns/reviews-full.php');
        $footer = file_get_contents(__DIR__.'/../../patterns/reviews-booking-footer.php');

        expect($full)->toContain('remote-leverage/reviews-booking-footer')
            ->and($full)->not->toContain('remote-leverage/booking-footer"')
            ->and($footer)->toContain('Book a free consultation');
    });
});
