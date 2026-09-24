<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Blocks\ChecklistGridBlock;
use App\Blocks\FeatureCardsBlock;
use App\Blocks\ImageCardGridBlock;
use App\Blocks\MediaCopyBlock;
use App\Blocks\ProcessStepsBlock;
use App\Support\BlockDefaults;

/**
 * /become-a-partner/ (WR-278), built from the Partner LP Figma file.
 *
 * Every section is an existing block, so nine blocks grew an option and one new block
 * (acf/checklist-grid) took over markup that was locked inside acf/partner-hero. The rule the
 * options have to keep is the same one RolePagesTest guards: a page that sets nothing renders
 * what it rendered before. WordPress is not booted here; block() and flattenFields() come
 * from EcommerceBlocksTest.
 */
$theme = dirname(__DIR__, 2);

describe('the partner options default to what every other page renders', function () {
    test('each new choice is appended, so the defaults and the shipped order hold', function () {
        $feature = flattenFields(block(FeatureCardsBlock::class)->fields()['fields']);
        $grid = flattenFields(block(ImageCardGridBlock::class)->fields()['fields']);
        $media = flattenFields(block(MediaCopyBlock::class)->fields()['fields']);
        $process = flattenFields(block(ProcessStepsBlock::class)->fields()['fields']);

        expect($feature['variant']['default_value'])->toBe('inset')
            ->and(array_keys($feature['variant']['choices']))->toBe(['inset', 'flush', 'horizontal', 'icon'])
            ->and($feature)->toHaveKey('cards.icon')
            ->and($grid['card_title_size']['default_value'])->toBe('small')
            ->and($grid['card_cta_style']['default_value'])->toBe('button')
            ->and($grid)->toHaveKeys(['image_ratio', 'cards.tags', 'cards.tag_tone'])
            ->and($media['vertical_align']['default_value'])->toBe('center')
            ->and($media['padding']['default_value'])->toBe('default')
            ->and($media['image_rounded']['default_value'])->toBe(0)
            ->and($process['treatment']['default_value'])->toBe('default');
    });

    test('media-copy names its repeater timeline, never steps', function () {
        // BlockDefaults::filterLoadValue() fills an empty repeater named `steps` with the
        // process-steps presets whatever block it belongs to, so a `steps` repeater here would
        // have put three hiring steps under every media-copy on the site.
        $media = flattenFields(block(MediaCopyBlock::class)->fields()['fields']);

        expect($media)->toHaveKey('timeline')
            ->and($media)->not->toHaveKey('steps');
    });

    test('the small pill is a separate class list and the default pill is untouched', function () {
        $default = BlockDefaults::ctaPillClasses();
        $small = BlockDefaults::ctaPillClasses('', 'small');

        expect($default)->toContain('py-[19px]')
            ->and($default)->toContain('outline-offset-4')
            ->and($default)->toContain('sm:text-[19px]')
            ->and($small)->toContain('py-4')
            ->and($small)->toContain('text-[15px]')
            // The comp draws no ring around it.
            ->and($small)->not->toContain('outline');
    });
});

describe('acf/checklist-grid', function () use ($theme) {
    test('defaults to acf/partner-hero\'s pill badges and is registered for renderEcom', function () {
        $flat = flattenFields(block(ChecklistGridBlock::class)->fields()['fields']);

        expect($flat['style']['default_value'])->toBe('pill')
            ->and($flat['icon']['default_value'])->toBe('check')
            ->and(BlockDefaults::REPEATER_KEYS['checklist-grid'])->toBe(['items', 'field_checklist_grid_block_items']);
    });

    test('drops empty rows and non-array rows', function () {
        expect(block(ChecklistGridBlock::class)->items([['text' => 'One'], ['text' => ''], 'junk', ['text' => 'Two']]))
            ->toBe(['One', 'Two'])
            ->and(block(ChecklistGridBlock::class)->items(null))->toBe([]);
    });

    test('shares one partial with acf/partner-hero, so the badges cannot drift', function () use ($theme) {
        $hero = (string) file_get_contents($theme.'/resources/views/blocks/partner-hero.blade.php');
        $block = (string) file_get_contents($theme.'/resources/views/blocks/checklist-grid.blade.php');

        expect($hero)->toContain("@include('blocks.partials.checklist-grid'")
            ->and($block)->toContain("@include('blocks.partials.checklist-grid'")
            ->and($hero)->not->toContain('rounded-pill border border-[#92B4F4]/30');
    });
});

describe('the /become-a-partner/ pattern', function () use ($theme) {
    test('routes every CTA to the closing form and mounts the prospect form, not the wizard', function () use ($theme) {
        $src = (string) file_get_contents($theme.'/patterns/become-a-partner.php');

        expect($src)->toContain("\$cta = '#booking-footer'")
            ->and($src)->toContain("'form' => 'partnership'")
            ->and($src)->toContain("'layout' => 'stacked'")
            // The comp misspells it GLOBALY; the page ships it the way every other page does.
            ->and($src)->toContain('>TRUSTED BY SCALING TEAMS GLOBALLY</p>');
    });
});
