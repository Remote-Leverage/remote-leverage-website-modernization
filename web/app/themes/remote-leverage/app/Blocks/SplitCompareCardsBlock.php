<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class SplitCompareCardsBlock extends Block
{
    public $name = 'Split Compare Cards';

    public $slug = 'split-compare-cards';

    public $description = 'A heading over two large photo-topped narrative cards — the "Which is more affordable" band on the competitor-comparison pages.';

    public $category = 'remote-leverage';

    public $icon = 'align-pull-left';

    public $keywords = ['comparison', 'cards', 'affordable', 'versus'];

    public $view = 'blocks.split-compare-cards';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['headline' => 'Which is more affordable?', 'is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $card = fn (string $n) => [
            'image' => BlockDefaults::resolveImageUrl($field("card_{$n}_image") ?: ''),
            'title' => $field("card_{$n}_title") ?: '',
            'body' => $field("card_{$n}_body") ?: '',
        ];

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'cards' => [$card('1'), $card('2')],
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('split_compare_cards_block');

        $fields->addTextarea('headline', ['label' => 'Headline', 'rows' => 2]);

        foreach (['1' => 'Left', '2' => 'Right'] as $n => $side) {
            $fields
                ->addImage("card_{$n}_image", ['label' => "{$side} Card Image", 'return_format' => 'url'])
                ->addText("card_{$n}_title", ['label' => "{$side} Card Title"])
                ->addWysiwyg("card_{$n}_body", [
                    'label' => "{$side} Card Body",
                    'tabs' => 'visual',
                    'media_upload' => 0,
                ]);
        }

        return $fields->build();
    }
}
