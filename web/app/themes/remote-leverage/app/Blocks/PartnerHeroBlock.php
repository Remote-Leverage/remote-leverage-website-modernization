<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class PartnerHeroBlock extends Block
{
    public $name = 'Hero — Co-branded Partner over Globe';

    public $slug = 'partner-hero';

    public $description = 'Centred co-branded hero on a configurable brand colour over a globe graphic, with a dark pill CTA and four translucent stat cards. Pair with acf/talent-grid for the profile row.';

    public $category = 'remote-leverage';

    public $icon = 'admin-site-alt3';

    public $keywords = ['hero', 'partner', 'co-branded', 'globe', 'centred', 'stats'];

    public $view = 'blocks.partner-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['is_preview' => true],
        ],
    ];

    public $supports = [
        'align' => ['full'],
    ];

    public function with(): array
    {
        $field = fn(string $key) => function_exists('get_field') ? get_field($key) : null;

        return [
            'headline' => $field('headline') ?: 'Remote Leverage × Partner',
            'paragraphs' => $this->paragraphs(),
            'ctaText' => $field('cta_text') ?: 'Book a Strategy Sync',
            'ctaUrl' => $field('cta_url') ?: '/vacalendar',
            'brandColor' => $field('brand_color') ?: 'var(--color-brand-purple-deep)',
            'brandColorEnd' => $field('brand_color_end') ?: 'var(--color-brand-dark-violet)',
            'backdrop' => BlockDefaults::resolveImageUrl($field('backdrop_image') ?: '') ?: BlockDefaults::homeImg('Map.webp'),
            'stats' => $this->stats(),
            // The profile row is the shared acf/talent-grid block rendered inside this band,
            // so the hero stays one continuous section without duplicating card markup.
            'talentHtml' => $this->talentHtml(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('partner_hero_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addRepeater('paragraphs', [
                'label' => 'Intro Paragraphs',
                'instructions' => 'The first is rendered bold, as on production.',
                'button_label' => 'Add paragraph',
            ])
            ->addTextarea('text', ['label' => 'Paragraph', 'rows' => 2])
            ->endRepeater()
            ->addText('cta_text', ['label' => 'CTA Text', 'default_value' => 'Book a Strategy Sync'])
            ->addText('cta_url', ['label' => 'CTA URL', 'default_value' => '/vacalendar'])
            ->addText('brand_color', [
                'label' => 'Brand Colour (gradient start)',
                'instructions' => 'Any CSS colour. Prefer a theme token, e.g. var(--color-brand-purple-deep).',
                'default_value' => 'var(--color-brand-purple-deep)',
            ])
            ->addText('brand_color_end', [
                'label' => 'Brand Colour (gradient end)',
                'instructions' => 'The darker stop the band fades into. Set the same value as the start for a flat colour.',
                'default_value' => 'var(--color-brand-dark-violet)',
            ])
            ->addImage('backdrop_image', ['label' => 'Backdrop Graphic', 'instructions' => 'Defaults to the shared world map used in the homepage hero and booking footer.', 'return_format' => 'url'])
            ->addTrueFalse('show_talent', [
                'label' => 'Show Talent Row',
                'instructions' => 'Renders acf/talent-grid (row layout) between the CTA and the stats.',
                'default_value' => true,
                'ui' => true,
            ])
            ->addRepeater('stats', ['label' => 'Stat Cards (4)', 'layout' => 'table', 'button_label' => 'Add stat'])
            ->addText('value', ['label' => 'Value', 'placeholder' => '70%'])
            ->addTextarea('label', ['label' => 'Label', 'rows' => 2])
            ->addSelect('icon', [
                'label' => 'Icon',
                'choices' => ['bars' => 'Bars', 'clock' => 'Clock', 'globe' => 'Globe', 'trend' => 'Trend'],
                'default_value' => 'bars',
            ])
            ->endRepeater();

        return $fields->build();
    }

    protected function talentHtml(): string
    {
        $show = function_exists('get_field') ? get_field('show_talent') : null;

        if ($show === false) {
            return '';
        }

        $block = BlockDefaults::renderTalentGrid(
            ['layout' => 'row', '_layout' => 'field_talent_grid_block_layout'],
            BlockDefaults::partnerTalentCards(),
        );

        return function_exists('do_blocks') ? do_blocks($block) : '';
    }

    /**
     * @return array<int, string>
     */
    protected function paragraphs(): array
    {
        $custom = function_exists('get_field') ? get_field('paragraphs') : null;

        if (! is_array($custom) || $custom === []) {
            return ['Hire the right people globally.'];
        }

        return array_values(array_filter(array_map(
            static fn(array $p): string => BlockDefaults::cleanText($p['text'] ?? ''),
            array_filter($custom, 'is_array'),
        )));
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    protected function stats(): array
    {
        $custom = function_exists('get_field') ? get_field('stats') : null;

        if (! is_array($custom) || $custom === []) {
            return [];
        }

        return array_values(array_map(static fn(array $s): array => [
            'value' => BlockDefaults::cleanText($s['value'] ?? ''),
            'label' => BlockDefaults::cleanText($s['label'] ?? ''),
            'icon' => (string) ($s['icon'] ?? 'bars'),
        ], array_filter($custom, 'is_array')));
    }
}
