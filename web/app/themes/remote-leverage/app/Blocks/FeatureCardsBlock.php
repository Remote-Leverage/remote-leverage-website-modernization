<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class FeatureCardsBlock extends Block
{
    public $name = 'Feature & Benefit Cards';

    public $slug = 'feature-cards';

    public $description = 'Grid of rounded cards with top imagery, bold headings, and descriptions.';

    public $category = 'remote-leverage';

    public $icon = 'grid-view';

    public $keywords = ['features', 'cards', 'benefits', 'grid', 'metrics'];

    public $view = 'blocks.feature-cards';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'columns' => '3',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $columns = (string) ((function_exists('get_field') ? get_field('columns') : null) ?: '3');

        return [
            'columns' => $columns,
            // Production uses several card image ratios across pages; `ratio` lets a pattern
            // pick one without forking the card markup. Empty falls back to the column default.
            'ratio' => (string) ((function_exists('get_field') ? get_field('ratio') : null) ?: ''),
            // Production ships two card treatments: 'inset' (homepage — image padded inside
            // the card) and 'flush' (product/report pages — image bleeds to the card edges
            // with a larger title). Default stays 'inset' so existing callers are unchanged.
            'variant' => (string) ((function_exists('get_field') ? get_field('variant') : null) ?: 'inset'),
            // 'frosted' is the 2026 homepage's "Why Remote Leverage" band: the card is a
            // translucent white wash over a coloured ground rather than an opaque card on a
            // flat one, and it carries that band's roomier type with it. Default stays 'solid'
            // so the fifteen patterns already shipping this block are untouched.
            'surface' => (string) ((function_exists('get_field') ? get_field('surface') : null) ?: 'solid'),
            'cards' => $this->cards($columns),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('feature_cards_block');

        $fields
            ->addSelect('columns', [
                'label' => 'Columns Layout',
                'choices' => [
                    '1' => '1 Column (stacked, pairs with the horizontal variant)',
                    '2' => '2 Columns (wide cards; image optional)',
                    '3' => '3 Columns (6 Benefit Cards)',
                    '4' => '4 Columns (4 Metric Cards)',
                ],
                'default_value' => '3',
            ])
            ->addSelect('variant', [
                'label' => 'Card Treatment',
                'choices' => [
                    'inset' => 'Inset image (default)',
                    'flush' => 'Image flush to card edges',
                    'horizontal' => 'Text left, image right',
                ],
                'default_value' => 'inset',
            ])
            ->addSelect('surface', [
                'label' => 'Card Surface',
                'instructions' => 'Frosted is the homepage "Why Remote Leverage" treatment: a translucent white card '
                    .'over a coloured band, with a 28px title held to two lines and 16/25 body copy. It is one '
                    .'treatment, so the surface and the type travel together.',
                'choices' => [
                    'solid' => 'Solid white card (default)',
                    'frosted' => 'Frosted — translucent over a coloured band',
                ],
                'default_value' => 'solid',
            ])
            ->addText('ratio', [
                'label' => 'Card Image Ratio',
                'instructions' => 'Optional, as width/height (e.g. 413/152). Leave empty to use the column default.',
                'placeholder' => '413/152',
            ])
            ->addRepeater('cards', [
                'label' => 'Custom Cards (Leave empty to use preset defaults)',
                'layout' => 'block',
                'button_label' => 'Add Card',
            ])
            ->addImage('img', ['label' => 'Image', 'return_format' => 'url'])
            ->addText('title', ['label' => 'Card Title (supports HTML)'])
            ->addTextarea('desc', ['label' => 'Card Description', 'rows' => 2])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(string $columns): array
    {
        $custom = function_exists('get_field') ? get_field('cards') : null;
        $cards = (! empty($custom) && is_array($custom))
            ? $custom
            : BlockDefaults::featureCards($columns);

        return array_map(function ($card) {
            $card['title'] = BlockDefaults::cleanText($card['title'] ?? '');
            $card['desc'] = BlockDefaults::cleanText($card['desc'] ?? '');
            $card['img'] = BlockDefaults::resolveImageUrl($card['img'] ?? '');

            return $card;
        }, $cards);
    }
}
