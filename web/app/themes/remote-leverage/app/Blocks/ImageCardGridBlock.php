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
            // Body text alone is enough to keep a card: Athena's "hiring easy" steps
            // are untitled and imageless, and would otherwise be trimmed as empty rows.
            fn ($c) => ! empty($c['title']) || ! empty($c['image']) || ! empty($c['icon']) || ! empty($c['text'])
        ));

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'subheadline' => BlockDefaults::cleanText($field('subheadline') ?: ''),
            'columns' => (int) ($field('columns') ?: 4),
            'ctaText' => $field('cta_text') ?: '',
            'align' => $field('align') ?: 'left',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            'titleSize' => $field('card_title_size') ?: 'small',
            // Every per-card field has to be listed here. A field added to fields() but not
            // mapped never reaches the view, and nothing errors — the card just renders
            // without it.
            'cards' => array_map(fn ($c) => [
                'image' => BlockDefaults::resolveImageUrl($c['image'] ?? ''),
                'icon' => BlockDefaults::resolveImageUrl($c['icon'] ?? ''),
                'icon_width' => $c['icon_width'] ?? '88',
                'eyebrow' => $c['eyebrow'] ?? '',
                'title' => BlockDefaults::cleanText($c['title'] ?? ''),
                'text' => $c['text'] ?? '',
                'cta_text' => $c['cta_text'] ?? '',
                'cta_url' => $c['cta_url'] ?? '',
                'emphasis' => ! empty($c['emphasis']) && $c['emphasis'] !== '0',
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
            ->addSelect('align', [
                'label' => 'Header alignment',
                'choices' => ['left' => 'Left (default)', 'center' => 'Centred'],
                'default_value' => 'left',
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
            ->addText('cta_text', [
                'label' => 'Card CTA Label',
                'instructions' => 'Optional. Renders a black, 5px-radius button at the foot of this card.',
            ])
            ->addText('cta_url', [
                'label' => 'Card CTA URL',
                'default_value' => '#booking-footer',
            ])
            ->addTrueFalse('emphasis', [
                'label' => 'Featured card (black outline)',
                'ui' => 1,
                'default_value' => 0,
            ])
            ->addImage('icon', [
                'label' => 'Card Icon',
                'instructions' => 'Shown at its natural size when the card has no full-bleed image.',
                'return_format' => 'url',
            ])
            ->addText('icon_width', ['label' => 'Icon width (px)', 'default_value' => '88'])
            ->endRepeater()
            ->addText('cta_text', [
                'label' => 'Footer CTA Text',
                'instructions' => 'Optional full-width button below the grid. Leave blank to hide.',
            ])
            ->addUrl('cta_url', ['label' => 'Footer CTA URL', 'default_value' => '#booking-footer']);

        return $fields->build();
    }
}
