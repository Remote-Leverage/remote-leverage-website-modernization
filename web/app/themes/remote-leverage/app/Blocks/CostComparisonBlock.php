<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class CostComparisonBlock extends Block
{
    public $name = 'Cost Comparison';

    public $slug = 'cost-comparison';

    public $description = 'Two side-by-side cost tables with coloured headers — the "Comparing costs" band on the competitor-comparison pages.';

    public $category = 'remote-leverage';

    public $icon = 'editor-table';

    public $keywords = ['cost', 'comparison', 'pricing', 'table', 'versus'];

    public $view = 'blocks.cost-comparison';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Comparing costs',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $table = fn (string $n) => [
            'title' => $field("table_{$n}_title") ?: '',
            'theme' => $field("table_{$n}_theme") ?: ($n === '1' ? 'competitor' : 'leverage'),
            'rows' => array_values(array_filter(
                (array) ($field("table_{$n}_rows") ?: []),
                fn ($r) => ! empty($r['label']) || ! empty($r['value'])
            )),
        ];

        return [
            'eyebrow' => BlockDefaults::cleanText($field('eyebrow') ?: ''),
            'headline' => BlockDefaults::cleanText($field('headline') ?: 'Comparing costs'),
            'description' => BlockDefaults::cleanText($field('description') ?: ''),
            'ctaText' => $field('cta_text') ?: '',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            'tables' => [$table('1'), $table('2')],
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('cost_comparison_block');

        $themes = [
            'competitor' => 'Competitor (orange)',
            'leverage' => 'Remote Leverage (green)',
        ];

        $fields
            ->addText('eyebrow', ['label' => 'Eyebrow', 'default_value' => 'The bottom line:'])
            ->addText('headline', ['label' => 'Headline', 'default_value' => 'Comparing costs'])
            ->addTextarea('description', ['label' => 'Description', 'rows' => 3]);

        foreach (['1' => 'Left', '2' => 'Right'] as $n => $side) {
            $fields
                ->addText("table_{$n}_title", ['label' => "{$side} Table Title"])
                ->addSelect("table_{$n}_theme", [
                    'label' => "{$side} Table Header Colour",
                    'choices' => $themes,
                    'default_value' => $n === '1' ? 'competitor' : 'leverage',
                ])
                ->addRepeater("table_{$n}_rows", [
                    'label' => "{$side} Table Rows",
                    'layout' => 'table',
                    'button_label' => 'Add row',
                ])
                ->addText('label', ['label' => 'Label'])
                ->addTextarea('value', ['label' => 'Value', 'rows' => 2])
                ->endRepeater();
        }

        $fields
            ->addText('cta_text', [
                'label' => 'Footer CTA Text',
                'instructions' => 'Optional full-width button below the tables. Leave blank to hide.',
            ])
            ->addUrl('cta_url', ['label' => 'Footer CTA URL', 'default_value' => '#booking-footer']);

        return $fields->build();
    }
}
