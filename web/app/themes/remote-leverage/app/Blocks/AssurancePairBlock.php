<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AssurancePairBlock extends Block
{
    public $name = 'Assurance Pair';

    public $slug = 'assurance-pair';

    public $description = 'Two reassurance cards side by side, each with its own CTA below — follows the process steps on the competitor-comparison pages.';

    public $category = 'remote-leverage';

    public $icon = 'shield';

    public $keywords = ['assurance', 'guarantee', 'fee', 'cards'];

    public $view = 'blocks.assurance-pair';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'items' => array_map(fn ($i) => [
                'title' => BlockDefaults::cleanText($field("item_{$i}_title") ?: ''),
                'text' => BlockDefaults::cleanText($field("item_{$i}_text") ?: ''),
                'ctaText' => $field("item_{$i}_cta_text") ?: '',
                'ctaUrl' => $field("item_{$i}_cta_url") ?: '#booking-footer',
            ], ['1', '2']),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('assurance_pair_block');

        foreach (['1' => 'Left', '2' => 'Right'] as $n => $side) {
            $fields
                ->addTextarea("item_{$n}_title", ['label' => "{$side} Title", 'rows' => 2])
                ->addTextarea("item_{$n}_text", ['label' => "{$side} Text", 'rows' => 3])
                ->addText("item_{$n}_cta_text", ['label' => "{$side} CTA Text"])
                ->addUrl("item_{$n}_cta_url", ['label' => "{$side} CTA URL", 'default_value' => '#booking-footer']);
        }

        return $fields->build();
    }
}
