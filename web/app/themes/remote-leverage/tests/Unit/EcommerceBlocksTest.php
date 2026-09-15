<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Blocks\FeaturedPostsBlock;
use App\Blocks\StatsBandBlock;
use App\Blocks\TalentDossierCarouselBlock;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Log1x\AcfComposer\Block;

/**
 * The three blocks introduced for the /ecommerce-virtual-assistant/ migration.
 *
 * WordPress is not booted here, so these assert what can be asserted without it:
 * the ACF field-group shape, the generated field keys (where a collision silently
 * blanks data in production), and the pure data mapping each block does before it
 * reaches its view.
 */

/**
 * ACF Composer builds a field group without touching WordPress, but Block's own
 * constructor wants the AcfComposer container binding — which we do not have.
 */
function block(string $class): Block
{
    return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
}

/** @return array<string, array<string, mixed>> name => field config, recursing into repeaters. */
function flattenFields(array $fields, string $prefix = ''): array
{
    $flat = [];

    foreach ($fields as $field) {
        $path = $prefix === '' ? $field['name'] : $prefix.'.'.$field['name'];
        $flat[$path] = $field;

        if (! empty($field['sub_fields'])) {
            $flat = array_merge($flat, flattenFields($field['sub_fields'], $path));
        }
    }

    return $flat;
}

function seedFields(array $values): void
{
    // The get_field() stub reads the post-meta store; get_field('x') with no post id
    // lands on index 0, which is what a block rendered outside the loop asks for.
    $GLOBALS['_wp_mock_post_meta'][0] = $values;
}

beforeEach(function () {
    $GLOBALS['_wp_mock_post_meta'] = [];
});

afterEach(function () {
    $GLOBALS['_wp_mock_post_meta'] = [];
});

describe('Ecommerce block registration', function () {
    test('each block registers under the expected slug, view and category', function (string $class, string $slug) {
        $block = block($class);

        expect($block)->toBeInstanceOf(Block::class)
            ->and($block->slug)->toBe($slug)
            ->and($block->view)->toBe('blocks.'.$slug)
            ->and($block->category)->toBe('remote-leverage')
            ->and($block->name)->not->toBe('')
            ->and($block->description)->not->toBe('')
            ->and($block->example)->toHaveKey('attributes')
            ->and($block->example['attributes'])->toHaveKey('data')
            ->and($block->supports['align'])->toContain('full');
    })->with([
        [TalentDossierCarouselBlock::class, 'talent-dossier-carousel'],
        [StatsBandBlock::class, 'stats-band'],
        [FeaturedPostsBlock::class, 'featured-posts'],
    ]);

    test('each block has a Blade view that opens by naming the production section', function (string $class) {
        $view = dirname(__DIR__, 2).'/resources/views/blocks/'.block($class)->slug.'.blade.php';

        expect(is_file($view))->toBeTrue("Missing view for {$class}");

        $source = (string) file_get_contents($view);

        // wp acorn blocks:inventory takes the first leading Blade comment as the
        // block's "what it renders" column, so the file must open with one.
        expect($source)->toStartWith('{{--');
        expect(preg_match('/\{\{--\s*(.+?)--\}\}/s', $source, $m))->toBe(1);
        expect(trim($m[1]))->toContain('/ecommerce-virtual-assistant/');
    })->with([
        [TalentDossierCarouselBlock::class],
        [StatsBandBlock::class],
        [FeaturedPostsBlock::class],
    ]);

    test('each view compiles to valid PHP', function (string $class) {
        // A malformed inline @if compiles to a parse error that only surfaces when the
        // page is rendered — long after the field-group assertions above have passed.
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        $compiled = $compiler->compileString((string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/blocks/'.block($class)->slug.'.blade.php'
        ));

        $tmp = tempnam(sys_get_temp_dir(), 'rl-blade').'.php';
        file_put_contents($tmp, $compiled);
        $lint = (string) shell_exec('php -l '.escapeshellarg($tmp).' 2>&1');
        unlink($tmp);

        expect($lint)->toContain('No syntax errors detected');
    })->with([
        [TalentDossierCarouselBlock::class],
        [StatsBandBlock::class],
        [FeaturedPostsBlock::class],
    ]);

    test('views stay inside the theme type and container conventions', function (string $class) {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/blocks/'.block($class)->slug.'.blade.php'
        );

        expect($source)->not->toContain('font-extrabold')
            ->and($source)->not->toContain('font-black')
            ->and($source)->toContain('max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8');
    })->with([
        [TalentDossierCarouselBlock::class],
        [StatsBandBlock::class],
        [FeaturedPostsBlock::class],
    ]);
});

describe('Ecommerce block field groups', function () {
    test('talent dossier carousel exposes the dossier card shape', function () {
        $group = block(TalentDossierCarouselBlock::class)->fields();
        $flat = flattenFields($group['fields']);

        expect($group['key'])->toBe('group_talent_dossier_carousel_block');

        expect(array_keys($flat))->toBe([
            'headline',
            'layout',
            'cards',
            'cards.photo',
            'cards.country',
            'cards.flag',
            'cards.rate',
            'cards.name',
            'cards.role',
            'cards.years',
            'cards.experience',
            'cards.skills',
            'cards.previous_companies',
            'cards.tools',
            'cards.tools.src',
            'cards.tools.alt',
        ]);

        expect($flat['cards']['type'])->toBe('repeater')
            ->and($flat['cards.tools']['type'])->toBe('repeater')
            ->and($flat['cards.photo']['return_format'])->toBe('url')
            ->and($flat['cards.flag']['return_format'])->toBe('url')
            ->and($flat['cards.tools.src']['return_format'])->toBe('url')
            ->and($flat['layout']['default_value'])->toBe('carousel')
            ->and(array_keys($flat['layout']['choices']))->toBe(['carousel', 'grid']);
    });

    test('stats band exposes tone plus an eyebrow/value/label repeater', function () {
        $group = block(StatsBandBlock::class)->fields();
        $flat = flattenFields($group['fields']);

        expect($group['key'])->toBe('group_stats_band_block')
            ->and(array_keys($flat))->toBe(['tone', 'stats', 'stats.eyebrow', 'stats.value', 'stats.label'])
            ->and($flat['tone']['default_value'])->toBe('dark')
            ->and(array_keys($flat['tone']['choices']))->toBe(['dark', 'light'])
            ->and($flat['stats']['type'])->toBe('repeater');
    });

    test('featured posts exposes the source switch and its query options', function () {
        $group = block(FeaturedPostsBlock::class)->fields();
        $flat = flattenFields($group['fields']);

        expect($group['key'])->toBe('group_featured_posts_block')
            ->and(array_keys($flat))->toBe([
                'headline',
                'subheadline',
                'tone',
                'source',
                'category',
                'count',
                'layout',
                'cards',
                'cards.image',
                'cards.title',
                'cards.url',
            ])
            ->and($flat['source']['default_value'])->toBe('manual')
            ->and(array_keys($flat['source']['choices']))->toBe(['manual', 'query'])
            ->and($flat['tone']['default_value'])->toBe('dark')
            ->and($flat['layout']['default_value'])->toBe('carousel')
            ->and($flat['count']['default_value'])->toBe(4);

        // category/count are only meaningful for the query source, and the
        // conditional must point at the generated key, not the bare name.
        foreach (['category', 'count'] as $name) {
            expect($flat[$name]['conditional_logic'][0][0])
                ->toMatchArray([
                    'field' => 'field_featured_posts_block_source',
                    'operator' => '==',
                    'value' => 'query',
                ]);
        }
    });

    test('no top-level field name collides with a repeater sub-field key', function (string $class) {
        // ACF Composer derives a sub-field key as field_<group>_<repeater>_<sub>, so a
        // top-level `cards_title` would occupy the same key as `cards`/`title` and blank
        // it with no error. Assert every generated key is unique instead of eyeballing it.
        $group = block($class)->fields();
        $keys = array_map(fn (array $field): string => $field['key'], flattenFields($group['fields']));

        expect(array_values($keys))->toHaveCount(count(array_unique($keys)));
    })->with([
        [TalentDossierCarouselBlock::class],
        [StatsBandBlock::class],
        [FeaturedPostsBlock::class],
    ]);
});

describe('Talent dossier carousel data mapping', function () {
    test('cards() drops nameless rows and flattens the nested tools repeater', function () {
        $mapped = block(TalentDossierCarouselBlock::class)->cards([
            ['name' => '', 'role' => 'Ignored'],
            [
                'name' => 'Ana',
                'country' => 'Colombia',
                'flag' => ['url' => 'https://example.test/co.svg'],
                'rate' => '$8/hour',
                'role' => 'Ecommerce VA',
                'years' => '5',
                'experience' => 'Ran a Shopify storefront.',
                'skills' => 'Shopify; Klaviyo; Gorgias',
                'previous_companies' => 'Acme, Globex',
                'tools' => [
                    ['src' => 'https://example.test/shopify.svg', 'alt' => 'Shopify'],
                    ['src' => '', 'alt' => 'Dropped — no logo'],
                ],
            ],
        ]);

        expect($mapped)->toHaveCount(1);

        $card = $mapped[0];

        expect($card['name'])->toBe('Ana')
            ->and($card['country'])->toBe('Colombia')
            ->and($card['flag'])->toBe('https://example.test/co.svg')
            ->and($card['rate'])->toBe('$8/hour')
            ->and($card['years'])->toBe('5')
            ->and($card['skills'])->toBe('Shopify; Klaviyo; Gorgias')
            ->and($card['previous_companies'])->toBe('Acme, Globex')
            ->and($card['tools'])->toBe([
                ['src' => 'https://example.test/shopify.svg', 'alt' => 'Shopify'],
            ]);
    });

    test('cards() tolerates a missing or malformed tools repeater', function () {
        $mapped = block(TalentDossierCarouselBlock::class)->cards([
            ['name' => 'No tools key'],
            ['name' => 'Falsey tools', 'tools' => false],
        ]);

        expect($mapped[0]['tools'])->toBe([])
            ->and($mapped[1]['tools'])->toBe([])
            ->and($mapped[0]['photo'])->toBe('')
            ->and($mapped[0]['experience'])->toBe('');
    });

    test('with() defaults to the carousel layout and reads the repeater', function () {
        seedFields([
            'headline' => 'Meet Our Ecommerce Talent',
            'cards' => [['name' => 'Ana']],
        ]);

        $data = block(TalentDossierCarouselBlock::class)->with();

        expect($data['headline'])->toBe('Meet Our Ecommerce Talent')
            ->and($data['layout'])->toBe('carousel')
            ->and($data['cards'])->toHaveCount(1);

        seedFields(['layout' => 'grid']);

        expect(block(TalentDossierCarouselBlock::class)->with()['layout'])->toBe('grid');
    });
});

describe('Stats band data', function () {
    test('production\'s three figures are reproduced verbatim, malformed value included', function () {
        // Production ships "41,920,00" and "Economic Impact Create". The migration
        // copies live copy as-is; correcting either here would break parity.
        expect(StatsBandBlock::defaultStats())->toBe([
            ['eyebrow' => '', 'value' => '+2500', 'label' => 'Contractors paid'],
            ['eyebrow' => '', 'value' => '+50', 'label' => 'Countries covered'],
            ['eyebrow' => 'USD', 'value' => '41,920,00', 'label' => 'Economic Impact Create'],
        ]);
    });

    test('stats() falls back to the defaults for empty, missing and valueless input', function (mixed $input) {
        expect(block(StatsBandBlock::class)->stats($input))->toBe(StatsBandBlock::defaultStats());
    })->with([
        [null],
        [[]],
        ['not an array'],
        [[['eyebrow' => 'USD', 'value' => '', 'label' => 'No value']]],
    ]);

    test('stats() keeps authored rows and normalises their missing keys', function () {
        $stats = block(StatsBandBlock::class)->stats([
            ['value' => '+900', 'label' => 'Hires'],
            ['eyebrow' => 'USD', 'value' => '1,000'],
        ]);

        expect($stats)->toBe([
            ['eyebrow' => '', 'value' => '+900', 'label' => 'Hires'],
            ['eyebrow' => 'USD', 'value' => '1,000', 'label' => ''],
        ]);
    });

    test('with() defaults the surface to dark', function () {
        seedFields([]);
        expect(block(StatsBandBlock::class)->with()['tone'])->toBe('dark');

        seedFields(['tone' => 'light']);
        expect(block(StatsBandBlock::class)->with()['tone'])->toBe('light');
    });
});

describe('Featured posts data', function () {
    test('normaliseCards() drops titleless rows and normalises the card shape', function () {
        $cards = block(FeaturedPostsBlock::class)->normaliseCards([
            ['image' => 'https://example.test/a.svg', 'title' => '', 'url' => '/dropped/'],
            ['image' => ['url' => 'https://example.test/b.svg'], 'title' => 'Kept', 'url' => '/kept/'],
            ['title' => 'No image'],
        ]);

        expect($cards)->toBe([
            ['image' => 'https://example.test/b.svg', 'title' => 'Kept', 'url' => '/kept/'],
            ['image' => '', 'title' => 'No image', 'url' => ''],
        ]);
    });

    test('queryCards() degrades to an empty list when WordPress is absent', function () {
        // Guarded rather than fatal: the block is also rendered by the pattern test
        // harness and the editor preview, neither of which has WP_Query.
        expect(class_exists(\WP_Query::class))->toBeFalse()
            ->and(block(FeaturedPostsBlock::class)->queryCards('ecommerce', 4))->toBe([]);
    });

    test('with() uses the repeater for the manual source', function () {
        seedFields([
            'headline' => 'Featured Content',
            'subheadline' => 'Latest Posts',
            'source' => 'manual',
            'cards' => [['title' => 'A post', 'url' => '/a-post/']],
        ]);

        $data = block(FeaturedPostsBlock::class)->with();

        expect($data['headline'])->toBe('Featured Content')
            ->and($data['subheadline'])->toBe('Latest Posts')
            ->and($data['tone'])->toBe('dark')
            ->and($data['layout'])->toBe('carousel')
            ->and($data['cards'])->toBe([['image' => '', 'title' => 'A post', 'url' => '/a-post/']]);
    });

    test('with() falls back to the repeater when the query source returns nothing', function () {
        seedFields([
            'source' => 'query',
            'category' => 'ecommerce',
            'count' => 4,
            'cards' => [['title' => 'Hand-picked fallback', 'url' => '/fallback/']],
        ]);

        expect(block(FeaturedPostsBlock::class)->with()['cards'])
            ->toBe([['image' => '', 'title' => 'Hand-picked fallback', 'url' => '/fallback/']]);
    });
});
