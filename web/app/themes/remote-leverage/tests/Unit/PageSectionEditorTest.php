<?php

declare(strict_types=1);

use App\Ai\Support\PageSectionEditor;

/**
 * PageSectionEditor is what stands between "clone this page with new copy" and
 * a forked, hand-edited page, so these lean on two properties above all: that
 * sections nobody asked to change come back byte-identical, and that an
 * override which cannot be applied is reported rather than dropped.
 *
 * The second one matters because ACF reads a value through its companion
 * "_field" key. Writing a field that does not already exist produces markup
 * that looks wired up and renders nothing — the exact failure CLAUDE.md warns
 * about — so "silently ignored" is the bug worth testing for.
 */
if (! function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        // 312 stands in for an attachment so the image_id classification has
        // something real to resolve; every other ID is a plain number.
        return (int) $post === 312 ? 'attachment' : 'page';
    }
}

if (! class_exists('WP_Block_Patterns_Registry')) {
    class WP_Block_Patterns_Registry
    {
        public static array $patterns = [];

        private static ?self $instance = null;

        public static function get_instance(): self
        {
            return self::$instance ??= new self;
        }

        public function get_registered(string $slug): ?array
        {
            return self::$patterns[$slug] ?? null;
        }
    }
}

function acfBlock(string $name, string $block, array $data): string
{
    $attrs = [
        'name' => $block,
        'data' => $data,
        'metadata' => ['name' => $name],
    ];

    // Only the "core/" namespace is stripped on serialization, so an ACF
    // block keeps its full name in the delimiter.
    return '<!-- wp:'.$block.' '.serialize_block_attributes($attrs).' /-->';
}

/** A hero plus a repeater-bearing section, shaped like the real patterns. */
function samplePage(): string
{
    return acfBlock('hero', 'acf/comparison-hero', [
        'headline' => 'Looking for a smarter Athena alternative?',
        '_headline' => 'field_hero_headline',
        'cta_text' => 'Book a consultation',
        '_cta_text' => 'field_hero_cta',
        'hero_image' => 312,
        '_hero_image' => 'field_hero_image',
    ]).acfBlock('costs', 'acf/cost-comparison', [
        'headline' => 'Comparing costs',
        '_headline' => 'field_costs_headline',
        'table_1_rows' => 2,
        '_table_1_rows' => 'field_costs_rows',
        'table_1_rows_0_label' => 'Upfront',
        'table_1_rows_1_label' => 'Recurring',
    ]);
}

it('round-trips content it was not asked to change', function () {
    $content = samplePage();

    $result = (new PageSectionEditor)->apply($content, []);

    expect($result['content'])->toBe($content)
        ->and($result['applied'])->toBe([]);
});

it('addresses sections by their declared name', function () {
    $sections = (new PageSectionEditor)->outline(samplePage());

    expect(array_column($sections, 'section'))->toBe(['hero', 'costs'])
        ->and($sections[0]['block'])->toBe('acf/comparison-hero');
});

it('hides ACF field-key companions from the editable field list', function () {
    $sections = (new PageSectionEditor)->outline(samplePage());

    expect(array_column($sections[0]['fields'], 'field'))
        ->toBe(['headline', 'cta_text', 'hero_image']);
});

it('classifies a repeater count apart from an image and plain copy', function () {
    $sections = (new PageSectionEditor)->outline(samplePage());

    $types = array_combine(
        array_column($sections[1]['fields'], 'field'),
        array_column($sections[1]['fields'], 'type'),
    );

    expect($types['headline'])->toBe('text')
        ->and($types['table_1_rows'])->toBe('repeater_count')
        ->and($types['table_1_rows_0_label'])->toBe('text');

    $hero = array_combine(
        array_column($sections[0]['fields'], 'field'),
        array_column($sections[0]['fields'], 'type'),
    );

    expect($hero['hero_image'])->toBe('image_id');
});

it('applies an override and leaves every other section untouched', function () {
    $result = (new PageSectionEditor)->apply(samplePage(), [
        ['section' => 'hero', 'fields' => ['headline' => 'Looking for a smarter Belay alternative?']],
    ]);

    expect($result['applied'])->toBe(['hero.headline'])
        ->and($result['skipped'])->toBe([])
        ->and($result['content'])->toContain('Belay')
        // The untouched section survives verbatim, delimiters and all.
        ->and($result['content'])->toContain(acfBlock('costs', 'acf/cost-comparison', [
            'headline' => 'Comparing costs',
            '_headline' => 'field_costs_headline',
            'table_1_rows' => 2,
            '_table_1_rows' => 'field_costs_rows',
            'table_1_rows_0_label' => 'Upfront',
            'table_1_rows_1_label' => 'Recurring',
        ]));
});

it('reports an unknown section rather than dropping the override', function () {
    $result = (new PageSectionEditor)->apply(samplePage(), [
        ['section' => 'nope', 'fields' => ['headline' => 'x']],
    ]);

    expect($result['applied'])->toBe([])
        ->and($result['skipped'])->toBe(['section "nope" does not exist on this page']);
});

it('refuses to invent a field ACF has no key for', function () {
    $result = (new PageSectionEditor)->apply(samplePage(), [
        ['section' => 'hero', 'fields' => ['made_up_field' => 'x']],
    ]);

    expect($result['applied'])->toBe([])
        ->and($result['skipped'])->toBe(['field "made_up_field" does not exist on section "hero"'])
        ->and($result['content'])->not->toContain('made_up_field');
});

it('expands a pattern reference so a 70-byte page is still describable', function () {
    WP_Block_Patterns_Registry::$patterns['remote-leverage/athena-full'] = ['content' => samplePage()];

    $page = '<!-- wp:pattern {"slug":"remote-leverage/athena-full"} /-->';

    $sections = (new PageSectionEditor)->outline($page);

    expect(array_column($sections, 'section'))->toBe(['hero', 'costs']);
});

it('edits copy in a pattern-referencing page without a theme deploy', function () {
    WP_Block_Patterns_Registry::$patterns['remote-leverage/athena-full'] = ['content' => samplePage()];

    $result = (new PageSectionEditor)->apply(
        '<!-- wp:pattern {"slug":"remote-leverage/athena-full"} /-->',
        [['section' => 'hero', 'fields' => ['cta_text' => 'Talk to us']]],
    );

    expect($result['applied'])->toBe(['hero.cta_text'])
        ->and($result['content'])->toContain('Talk to us')
        ->and($result['content'])->not->toContain('wp:pattern');
});

it('addresses an unnamed core block positionally', function () {
    $content = '<!-- wp:heading --><h2>Both services at-a-glance</h2><!-- /wp:heading -->';

    $sections = (new PageSectionEditor)->outline($content);

    expect($sections[0]['section'])->toBe('#0')
        ->and($sections[0]['fields'][0]['field'])->toBe('text')
        ->and($sections[0]['fields'][0]['value'])->toBe('Both services at-a-glance');
});

/**
 * serialize_blocks() reads innerContent, not innerHTML. Updating only the
 * latter leaves the saved markup unchanged while every in-memory assertion
 * still passes, so this asserts against the serialized output.
 */
it('writes core block text through to the serialized markup', function () {
    $content = '<!-- wp:heading --><h2 class="x">Old heading</h2><!-- /wp:heading -->';

    $result = (new PageSectionEditor)->apply($content, [
        ['section' => '#0', 'fields' => ['text' => 'New heading']],
    ]);

    expect($result['applied'])->toBe(['#0.text'])
        ->and($result['content'])->toBe('<!-- wp:heading --><h2 class="x">New heading</h2><!-- /wp:heading -->');
});

it('does not corrupt a replacement containing a dollar sign', function () {
    $content = '<!-- wp:paragraph --><p>old</p><!-- /wp:paragraph -->';

    $result = (new PageSectionEditor)->apply($content, [
        ['section' => '#0', 'fields' => ['text' => 'Save $100 today']],
    ]);

    expect($result['content'])->toContain('Save $100 today');
});
