<?php

declare(strict_types=1);

namespace App\Blocks;

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

    public function with(): array
    {
        $columns = (string) ((function_exists('get_field') ? get_field('columns') : null) ?: '3');

        return [
            'columns' => $columns,
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
                    '3' => '3 Columns (6 Benefit Cards)',
                    '4' => '4 Columns (4 Metric Cards)',
                ],
                'default_value' => '3',
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

        if (! empty($custom) && is_array($custom)) {
            return $custom;
        }

        return \App\Support\BlockDefaults::featureCards($columns);
    }
}
