<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Blocks\BookingFooterBlock;
use App\Blocks\HomeHeroBlock;
use App\Blocks\WhyHireBlock;
use App\Support\BlockDefaults;

/**
 * /admin-virtual-assistants/, the first of the fourteen 2026 role pages.
 *
 * The page reuses acf/home-hero and acf/why-hire rather than adding two near-duplicate blocks,
 * so both grew an option. The rule those options have to keep is that every page which sets
 * nothing renders exactly what it rendered before — which is what most of this file guards.
 *
 * WordPress is not booted here, so these assert the field-group shape, the view's branches and
 * what the patterns ask for. block() and flattenFields() come from EcommerceBlocksTest.
 */
describe('the role page options default to the homepage behaviour', function () {
    test('acf/home-hero keeps the talent-card fan, magenta ticks and a purple accent by default', function () {
        $flat = flattenFields(block(HomeHeroBlock::class)->fields()['fields']);

        expect($flat['media']['default_value'])->toBe('cards')
            ->and(array_keys($flat['media']['choices']))->toBe(['cards', 'image'])
            ->and($flat['tick_tone']['default_value'])->toBe('magenta')
            ->and(array_keys($flat['tick_tone']['choices']))->toBe(['magenta', 'emerald'])
            ->and($flat['headline_accent_tone']['default_value'])->toBe('purple')
            ->and(array_keys($flat['headline_accent_tone']['choices']))->toBe(['purple', 'inherit'])
            ->and($flat)->toHaveKey('hero_image');
    });

    test('acf/why-hire keeps the split arrangement and the generic icons by default', function () {
        $flat = flattenFields(block(WhyHireBlock::class)->fields()['fields']);

        expect($flat['layout']['default_value'])->toBe('split')
            ->and(array_keys($flat['layout']['choices']))->toBe(['split', 'banner'])
            ->and($flat['icon_set']['default_value'])->toBe('classic')
            ->and(array_keys($flat['icon_set']['choices']))->toBe(['classic', 'descriptive'])
            // The two grounds that already shipped must keep their keys and their order.
            ->and(array_keys($flat['proof_background']['choices']))->toBe(['midnight', 'violet', 'violet-deep'])
            ->and($flat['proof_background']['default_value'])->toBe('midnight');
    });

    test('a new field name never collides with a generated repeater sub-key', function () {
        // ACF Composer derives sub-field keys as field_<group>_<repeater>_<sub>. A top-level
        // field whose name matches one silently blanks the other in the editor.
        foreach ([HomeHeroBlock::class, WhyHireBlock::class] as $class) {
            $flat = flattenFields(block($class)->fields()['fields']);
            $top = array_values(array_filter(array_keys($flat), fn ($k) => ! str_contains($k, '.')));
            $generated = array_map(
                fn ($k) => str_replace('.', '_', $k),
                array_filter(array_keys($flat), fn ($k) => str_contains($k, '.'))
            );

            expect(array_intersect($top, $generated))->toBe([], "Key collision in {$class}");
        }
    });

    test('the homepage patterns still ask for none of the new options', function () {
        foreach (['homepage-hero', 'homepage-why-hire'] as $slug) {
            $src = (string) file_get_contents(dirname(__DIR__, 2)."/patterns/{$slug}.php");

            expect($src)->not->toContain("'media' =>")
                ->and($src)->not->toContain("'tick_tone' =>")
                ->and($src)->not->toContain("'headline_accent_tone' =>")
                ->and($src)->not->toContain("'layout' =>")
                ->and($src)->not->toContain("'icon_set' =>");
        }
    });
});

describe('the views branch on the new options', function () {
    test('home-hero swaps the card fan for the composite and flows the checklist by column', function () {
        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/home-hero.blade.php');

        expect($blade)->toContain("\$isImage = (\$media ?? 'cards') === 'image'")
            // The fan is suppressed rather than merely hidden, so the image variant emits no
            // markup for three cards it never shows.
            ->and($blade)->toContain('@if ($cards && ! $isImage)')
            ->and($blade)->toContain('lg:grid-flow-col lg:grid-rows-3')
            ->and($blade)->toContain('fetchpriority="high"')
            // Measured off the comp; see the view for the three samples behind it.
            ->and($blade)->toContain('bg-[#10B981]');
    });

    test('why-hire renders the banner arrangement and the measured ground', function () {
        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/why-hire.blade.php');

        expect($blade)->toContain("\$isBanner = (\$layout ?? 'split') === 'banner'")
            ->and($blade)->toContain('linear-gradient(180deg, #5A1DAF 0%, #25104A 100%)')
            // Both arrangements draw their cards from one partial, so the two cannot drift.
            ->and(substr_count($blade, "@include('blocks.partials.why-hire-cards')"))->toBe(2);

        $partial = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/partials/why-hire-cards.blade.php');

        expect($partial)->toContain('bg-lavender-surface')
            ->and($partial)->toContain('bg-brand-purple/10');
    });

    test('both icon sets carry one glyph per card', function () {
        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/why-hire.blade.php');

        expect(substr_count($blade, "'classic' => ["))->toBe(1)
            ->and(substr_count($blade, "'descriptive' => ["))->toBe(1)
            ->and(count(BlockDefaults::whyHire2026Cards()))->toBe(4);
    });
});

describe('the booking footer trust strip', function () {
    test('it is off unless a page asks, so the shared footer is untouched', function () {
        $flat = flattenFields(block(BookingFooterBlock::class)->fields()['fields']);

        expect($flat['show_trust']['default_value'])->toBe(0)
            ->and($flat)->toHaveKey('checklist.item');

        // The homepage keeps the plain footer.
        $home = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/homepage-booking-footer.php');
        expect($home)->not->toContain('show_trust');
    });

    test('the six checkpoints sit beside the form on desktop and in a black strip on mobile', function () {
        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/booking-footer.blade.php');

        // In-column copy: desktop only.
        expect($blade)->toContain('mt-6 hidden grid-cols-1 gap-y-[14px] lg:grid')
            // Strip: mobile only, black, and the last thing in the section so it meets the
            // site footer — also bg-black — with no seam.
            ->and($blade)->toContain('<div class="bg-black lg:hidden">')
            ->and(substr_count($blade, 'bg-[#10B981]'))->toBe(2);

        $strip = substr($blade, strpos($blade, '<div class="bg-black lg:hidden">'));
        expect(trim(substr($strip, strrpos($strip, '</div>') + 6)))->toBe('@endif'.PHP_EOL.'</section>');
    });

    test('the role page turns it on and the section padding still wraps the purple band', function () {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/admin-virtual-assistants-booking-footer.php');
        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/blocks/booking-footer.blade.php');

        expect($src)->toContain("'show_trust' => 1")
            ->and($src)->toContain('field_booking_footer_checklist')
            // The padding moved off <section> so the strip can bleed; if it moved back, the
            // strip would sit inside the purple band's padding and stop meeting the footer.
            ->and($blade)->toContain('<div class="py-16 sm:py-20 lg:py-24">')
            ->and($blade)->not->toContain("'w-full text-white py-16 sm:py-20 lg:py-24',");
    });
});

describe('the page is assembled from its own sections and the homepage shared ones', function () {
    test('the hero asks for the composite, emerald ticks and an uncoloured accent line', function () {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/admin-virtual-assistants-hero.php');

        expect($src)->toContain("'media' => 'image'")
            ->and($src)->toContain("'tick_tone' => 'emerald'")
            ->and($src)->toContain("'headline_accent_tone' => 'inherit'")
            ->and($src)->toContain("pageImg('admin-virtual-assistants', 'hero.webp')")
            // The checklist is passed, not inherited, and in the comp's column-major order:
            // down the left desktop column, then down the right, which is also its mobile order.
            ->and($src)->toContain('field_home_hero_checklist');

        preg_match('/\$checklist = array_map.*?\[(.*?)\]\);/s', $src, $m);

        expect($m[1] ?? '')->toContain("'No Contracts, No Ongoing Fees',\n    '12-Month Replacement Guarantee',\n    'Hire Direct, No Middleman',");
    });

    test('the why-hire section asks for the banner, the measured ground and the role globe', function () {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/admin-virtual-assistants-why-hire.php');

        expect($src)->toContain("'layout' => 'banner'")
            ->and($src)->toContain("'proof_background' => 'violet-deep'")
            ->and($src)->toContain("'icon_set' => 'descriptive'")
            ->and($src)->toContain("pageImg('admin-virtual-assistants', 'globe.png')")
            ->and($src)->toContain('whyHire2026Cards');
    });

    test('the four reasons are shared with the homepage rather than restated', function () {
        $role = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/admin-virtual-assistants-why-hire.php');
        $home = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/homepage-why-hire.php');

        // Fourteen role pages carry this copy verbatim; one helper, not fifteen literals.
        expect($role)->toContain('BlockDefaults::whyHire2026Cards()')
            ->and($home)->toContain('BlockDefaults::whyHire2026Cards()')
            ->and($home)->not->toContain('No Recurring Fees - Hire Direct');
    });

    test('the full pattern owns three sections and reuses the homepage for the rest', function () {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/patterns/admin-virtual-assistants-full.php');

        foreach (['hero', 'talent', 'why-hire', 'booking-footer'] as $own) {
            expect($src)->toContain("remote-leverage/admin-virtual-assistants-{$own}");
        }

        foreach ([
            'comparison-table', 'process', 'headache', 'guarantee', 'reviews', 'faq',
        ] as $shared) {
            expect($src)->toContain("remote-leverage/homepage-{$shared}");
        }

        // The shared footer must stay off for everyone else.
        expect($src)->not->toContain('remote-leverage/homepage-booking-footer');
    });

    test('every section the page references exists on disk', function () {
        $dir = dirname(__DIR__, 2).'/patterns';
        $src = (string) file_get_contents($dir.'/admin-virtual-assistants-full.php');

        preg_match_all('#remote-leverage/([a-z0-9-]+)#', $src, $m);

        expect($m[1])->not->toBeEmpty();

        foreach (array_unique($m[1]) as $slug) {
            expect(is_file($dir.'/'.$slug.'.php'))->toBeTrue("Missing pattern for {$slug}");
        }
    });

    test('the page art is tracked source, not a stray file in public/', function () {
        $src = dirname(__DIR__, 2).'/resources/images/pages/admin-virtual-assistants';

        expect(is_file($src.'/hero.webp'))->toBeTrue()
            ->and(is_file($src.'/globe.png'))->toBeTrue();
    });
});
