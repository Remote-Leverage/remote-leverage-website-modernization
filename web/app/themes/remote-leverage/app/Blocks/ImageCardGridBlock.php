<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ImageCardGridBlock extends Block
{
    public $name = 'Image Card Grid';

    public $slug = 'image-card-grid';

    public $description = 'A heading with a row of photo-topped cards — the "Built for Control, Speed, and Scale" band on the competitor-comparison pages.';

    public $category = 'remote-leverage';

    public $icon = 'grid-view';

    public $keywords = ['cards', 'grid', 'features', 'photo', 'comparison'];

    public $view = 'blocks.image-card-grid';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Built for Control, Speed, and Scale',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $cards = array_values(array_filter(
            (array) ($field('cards') ?: []),
            fn ($c) => ! empty($c['title']) || ! empty($c['image'])
        ));

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'subheadline' => BlockDefaults::cleanText($field('subheadline') ?: ''),
            'cards' => array_map(fn ($c) => [
                'image' => BlockDefaults::resolveImageUrl($c['image'] ?? ''),
                'title' => $c['title'] ?? '',
                'text' => $c['text'] ?? '',
            ], $cards),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('image_card_grid_block');

        $fields
            ->addText('headline', ['label' => 'Headline'])
            ->addTextarea('subheadline', ['label' => 'Subheadline', 'rows' => 2])
            ->addRepeater('cards', [
                'label' => 'Cards',
                'button_label' => 'Add card',
                'min' => 1,
            ])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'url'])
            ->addText('title', ['label' => 'Title'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 3])
            ->endRepeater();

        return $fields->build();
    }
}
