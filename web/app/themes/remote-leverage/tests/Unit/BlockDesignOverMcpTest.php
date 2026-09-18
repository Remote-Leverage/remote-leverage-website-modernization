<?php

declare(strict_types=1);

use App\Ai\Abilities\DescribePageAbility;
use App\Ai\Abilities\UpdatePageSectionsAbility;
use App\Ai\Support\LandingPageComposer;
use App\Ai\Support\PageSectionEditor;
use App\Support\BlockDesign;

/**
 * The design controls were reachable in the editor and unreachable over MCP, and
 * the gap was invisible from both sides: a pattern-built block carries none of
 * these keys, so describe-page listed nothing and update-page-sections skipped
 * every override naming one. An agent reading that concluded the site had no
 * spacing or CSS controls at all and told the user to change the theme source.
 *
 * Each test below pins one link in that chain.
 */
it('maps every design field to the ACF key that makes it resolvable', function () {
    $keys = BlockDesign::fieldKeys();

    // A value written without its companion _field key is inert: ACF resolves
    // the value through that key, so a wrong or missing one saves markup that
    // looks correct and renders nothing.
    expect($keys)->toHaveKey(BlockDesign::PREFIX.'css');
    expect($keys[BlockDesign::PREFIX.'css'])->toBe('field_rl_design_css');
    expect($keys[BlockDesign::PREFIX.'space_top'])->toBe('field_rl_design_space_top');
});

it('excludes tabs from the field map', function () {
    // Tabs are presentation only and carry an empty name; writing one as a field
    // would put a junk key in the block's attribute bag.
    foreach (BlockDesign::fieldKeys() as $name => $key) {
        expect($name)->not->toBe('');
    }
});

it('recognises its own fields and nothing else', function (string $field, bool $isDesign) {
    expect(BlockDesign::isDesignField($field))->toBe($isDesign);
})->with([
    'spacing' => [BlockDesign::PREFIX.'space_top', true],
    'custom css' => [BlockDesign::PREFIX.'css', true],
    'hide mobile' => [BlockDesign::PREFIX.'hide_mobile', true],
    // A block's own field must never be creatable — its ACF key is unknowable
    // from outside, so inventing one writes markup that renders nothing.
    'block field' => ['headline', false],
    'near miss' => [BlockDesign::PREFIX.'not_a_real_control', false],
]);

/**
 * describe-page returns this verbatim, and it is the only thing telling an agent
 * these controls exist — the values themselves are empty on an unstyled block.
 */
it('documents what every control accepts', function () {
    $reference = BlockDesign::controlReference();

    expect(array_keys($reference))->toEqual(array_keys(BlockDesign::fieldKeys()));

    foreach ($reference as $field => $entry) {
        expect($entry['label'])->not->toBeEmpty();
        expect($entry['accepts'])->not->toBeEmpty();
    }
});

it('names the spacing scale in what the spacing controls accept', function () {
    $accepts = BlockDesign::controlReference()[BlockDesign::PREFIX.'space_top']['accepts'];

    foreach (['none', 'xs', 'sm', 'md', 'lg', 'xl'] as $step) {
        expect($accepts)->toContain($step);
    }
});

it('tells an agent the custom CSS is scoped and how to target the block', function () {
    $accepts = BlockDesign::controlReference()[BlockDesign::PREFIX.'css']['accepts'];

    // Without "selector" an agent cannot target the block itself, and without
    // "scoped" it will assume it has to write defensively-unique class names.
    expect($accepts)->toContain('selector');
    expect($accepts)->toContain('scoped');
});

/**
 * The crash that made /hire-va-4/ unreadable. The adapter validates the output
 * schema before returning, so one array-valued ACF field failed the entire call
 * and took every other section on the page with it.
 */
it('permits array and object values in the describe-page output schema', function () {
    $schema = (new DescribePageAbility(new PageSectionEditor))->outputSchema();
    $value = $schema['properties']['sections']['items']['properties']['fields']['items']['properties']['value'];

    expect($value['type'])->toContain('array');
    expect($value['type'])->toContain('object');
});

it('declares the design metadata describe-page attaches to each control', function () {
    $schema = (new DescribePageAbility(new PageSectionEditor))->outputSchema();
    $properties = $schema['properties']['sections']['items']['properties']['fields']['items']['properties'];

    expect($properties)->toHaveKeys(['label', 'accepts']);
});

/**
 * The descriptions are the whole interface for a model. An agent that reads
 * "copy" and nothing about design will decline design work and send the user to
 * a developer — which is exactly what happened.
 */
it('tells an agent through update-page-sections that design is editable', function () {
    $description = (new UpdatePageSectionsAbility(new PageSectionEditor, new LandingPageComposer))->description();

    expect($description)->toContain('rl_design_space_top');
    expect($description)->toContain('rl_design_css');
    expect($description)->toContain('none|xs|sm|md|lg|xl');
    expect($description)->toContain('scoped');
});

it('tells an agent through describe-page that design fields are listed when unset', function () {
    $description = (new DescribePageAbility(new PageSectionEditor))->description();

    expect($description)->toContain('design');
    expect($description)->toContain('accepts');
    // The specific wrong conclusion this text exists to prevent.
    expect(strtolower($description))->toContain('even when never set');
});
