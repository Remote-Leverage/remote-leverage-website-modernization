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
            'columns' => (int) ($field('columns') ?: 4),
            'ctaText' => $field('cta_text') ?: '',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            'titleSize' => $field('card_title_size') ?: 'small',
            'cards' => array_map(fn ($c) => [
                'image' => BlockDefaults::resolveImageUrl($c['image'] ?? ''),
                'eyebrow' => $c['eyebrow'] ?? '',
                'title' => BlockDefaults::cleanText($c['title'] ?? ''),
                'text' => $c['text'] ?? '',
            ], $cards),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('image_card_grid_block');

        $fields
            ->addText('headline', ['label' => 'Headline'])
            ->addTextarea('subheadline', ['label' => 'Subheadline', 'rows' => 3])
            ->addSelect('columns', [
                'label' => 'Columns',
                'choices' => [4 => '4 across', 3 => '3 across'],
                'default_value' => 4,
            ])
            ->addSelect('card_title_size', [
                'label' => 'Card Title Size',
                'choices' => ['small' => 'Small (15px)', 'large' => 'Large (27px)'],
                'default_value' => 'small',
            ])
            ->addRepeater('cards', [
                'label' => 'Cards',
                'button_label' => 'Add card',
                'min' => 1,
            ])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'url'])
            ->addText('eyebrow', ['label' => 'Eyebrow', 'instructions' => 'Optional small label above the title (e.g. a step number).'])
            ->addTextarea('title', ['label' => 'Title', 'rows' => 2, 'instructions' => 'Inline <br> allowed.'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 3])
            ->endRepeater()
            ->addText('cta_text', [
                'label' => 'Footer CTA Text',
                'instructions' => 'Optional full-width button below the grid. Leave blank to hide.',
            ])
            ->addUrl('cta_url', ['label' => 'Footer CTA URL', 'default_value' => '#booking-footer']);

        return $fields->build();
    }
}
