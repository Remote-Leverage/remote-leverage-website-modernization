<?php

declare(strict_types=1);

use App\Support\BlockDesign;

/**
 * Guards the per-instance design controls.
 *
 * Two of these properties are the whole reason the feature is safe to hand to a non-technical
 * editor, and both fail silently if broken:
 *
 *  1. **Scoping.** Custom CSS that escapes its own block forks the design system page by page,
 *     and the damage shows up on a different page from the edit that caused it.
 *  2. **Sanitisation.** A pasted snippet that closes the `<style>` element turns a CSS field
 *     into an HTML field, and therefore into a script field.
 *
 * The rest pins the attribute merging, because a block whose wrapper already carries fifty
 * Tailwind classes must keep all of them.
 */
it('scopes the selector keyword to the generated class', function () {
    $css = BlockDesign::sanitiseCss('selector .card { border-radius: 24px; }', 'rl-d-abc12345');

    expect($css)->toBe('.rl-d-abc12345 .card { border-radius: 24px; }');
});

it('does not rewrite selector inside a longer identifier', function () {
    $css = BlockDesign::sanitiseCss('.my-selector { color: red; } selector { color: blue; }', 'rl-d-abc12345');

    expect($css)->toContain('.my-selector { color: red; }')
        ->and($css)->toContain('.rl-d-abc12345 { color: blue; }');
});

it('strips anything that would break out of the style element', function () {
    $css = BlockDesign::sanitiseCss(
        'selector { color: red } </style><script>alert(1)</script>',
        'rl-d-abc12345'
    );

    expect($css)->not->toContain('</style')
        ->and($css)->not->toContain('</ style');
});

it('strips remote fetches and script-in-css', function (string $input, string $forbidden) {
    expect(strtolower(BlockDesign::sanitiseCss($input, 'rl-d-abc12345')))
        ->not->toContain($forbidden);
})->with([
    ['@import url("https://evil.test/x.css"); selector { color: red }', '@import'],
    ['@charset "utf-8"; selector { color: red }', '@charset'],
    ['selector { width: expression(alert(1)) }', 'expression('],
    ['selector { background: url(javascript:alert(1)) }', 'javascript:'],
    ['selector { behavior: url(evil.htc) }', 'behavior:'],
    ['selector { -moz-binding: url(evil.xml) }', '-moz-binding:'],
]);

it('hides a comment used to smuggle a closing style tag', function () {
    $css = BlockDesign::sanitiseCss('selector{color:red}/* </style> */', 'rl-d-abc12345');

    expect($css)->not->toContain('</style');
});

it('caps runaway css', function () {
    $css = BlockDesign::sanitiseCss(str_repeat('selector{color:red}', 5000), 'rl-d-abc12345');

    expect(strlen($css))->toBeLessThanOrEqual(BlockDesign::MAX_CSS_BYTES);
});

it('compiles spacing, background and visibility into scoped rules', function () {
    $css = BlockDesign::compile([
        'space_top' => 'lg',
        'space_bottom' => 'none',
        'bg' => 'navy',
        'hide_mobile' => true,
    ], 'rl-d-abc12345');

    expect($css)->toContain('padding-top:80px !important')
        ->and($css)->toContain('padding-bottom:0px !important')
        ->and($css)->toContain('background-color:#342567 !important')
        ->and($css)->toContain('@media (max-width:1023.98px)')
        ->and($css)->toContain('display:none !important');
});

it('emits nothing for a design panel left untouched', function () {
    expect(BlockDesign::compile([], 'rl-d-abc12345'))->toBe('');
});

it('only accepts a custom background that is a hex colour', function () {
    expect(BlockDesign::compile(['bg' => 'custom', 'bg_custom' => '#abc'], 'rl-d-x'))
        ->toContain('background-color:#abc')
        ->and(BlockDesign::compile(['bg' => 'custom', 'bg_custom' => 'red; }'], 'rl-d-x'))
        ->toBe('');
});

it('drops unset controls so an opened-and-closed panel changes nothing', function () {
    $design = BlockDesign::extract([
        'rl_design_space_top' => '',
        'rl_design_hide_mobile' => false,
        'rl_design_bg' => 'navy',
        'headline' => 'Not a design field',
    ]);

    expect($design)->toBe(['bg' => 'navy']);
});

it('merges into an existing class attribute rather than replacing it', function () {
    $html = BlockDesign::applyAttributes(
        '<section class="w-full py-14 lg:py-20"><p>Hi</p></section>',
        'rl-d-abc12345 promo',
        'pricing'
    );

    expect($html)->toContain('class="w-full py-14 lg:py-20 rl-d-abc12345 promo"')
        ->and($html)->toContain('id="pricing"')
        ->and($html)->toContain('<p>Hi</p>');
});

it('adds a class attribute when the wrapper has none', function () {
    $html = BlockDesign::applyAttributes('<section data-x="1">Hi</section>', 'rl-d-abc12345', null);

    expect($html)->toContain('<section data-x="1" class="rl-d-abc12345">');
});

it('leaves an id the block already rendered alone', function () {
    $html = BlockDesign::applyAttributes('<section id="existing">Hi</section>', 'rl-d-x', 'new');

    expect($html)->toContain('id="existing"')
        ->and($html)->not->toContain('id="new"');
});

it('skips leading comments to find the real wrapper', function () {
    $html = BlockDesign::applyAttributes('<!-- note --><section>Hi</section>', 'rl-d-x', null);

    expect($html)->toStartWith('<!-- note --><section class="rl-d-x">');
});

it('wraps content that has no element to attach to', function () {
    $html = BlockDesign::applyAttributes('Just text', 'rl-d-x', null);

    expect($html)->toBe('<div class="rl-d-x">Just text</div>');
});

it('does not confuse a > inside an attribute value for the end of the tag', function () {
    $html = BlockDesign::applyAttributes('<section data-label="a > b">Hi</section>', 'rl-d-x', null);

    expect($html)->toContain('data-label="a > b"')
        ->and($html)->toContain('class="rl-d-x"');
});

it('reduces an anchor to something safe for an id attribute', function (string $input, ?string $expected) {
    expect(BlockDesign::sanitiseAnchor($input))->toBe($expected);
})->with([
    ['Pricing', 'pricing'],
    ['  our pricing  ', 'our-pricing'],
    ['a"onload=alert(1)', 'a-onload-alert-1'],
    ['---', null],
    ['', null],
]);

it('gives identically configured blocks the same scope class', function () {
    $a = BlockDesign::extract(['rl_design_bg' => 'navy']);
    $b = BlockDesign::extract(['rl_design_bg' => 'navy', 'rl_design_space_top' => '']);

    expect(md5(serialize($a)))->toBe(md5(serialize($b)));
});
