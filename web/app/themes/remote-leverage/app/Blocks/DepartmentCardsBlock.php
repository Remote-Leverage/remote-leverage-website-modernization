<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class DepartmentCardsBlock extends Block
{
    public $name = 'Department & Role Cards';

    public $slug = 'department-cards';

    public $description = 'Specialized role cards with background photography, frosted blur layer, and description.';

    public $category = 'remote-leverage';

    public $icon = 'id-alt';

    public $keywords = ['department', 'contractor', 'roles', 'staffing', 'talent'];

    public $view = 'blocks.department-cards';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Specialized Roles for High-Output Teams',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('department_cards_block');

        $fields
            ->addRepeater('cards', [
                'label' => 'Role Cards (Leave empty for default 4 specialties)',
                'layout' => 'block',
                'button_label' => 'Add Role Card',
            ])
            ->addText('title', ['label' => 'Title (supports HTML like <br>)'])
            ->addTextarea('desc', ['label' => 'Description', 'rows' => 2])
            ->addImage('img', ['label' => 'Background Image', 'return_format' => 'url'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $items = function_exists('get_field') ? get_field('cards') : null;
        $cards = (! empty($items) && is_array($items))
            ? $items
            : BlockDefaults::departmentCards();

        return array_map(function ($card) {
            $card['title'] = BlockDefaults::cleanText($card['title'] ?? '');
            $card['desc'] = BlockDefaults::cleanText($card['desc'] ?? '');
            $card['img'] = BlockDefaults::resolveImageUrl($card['img'] ?? '');

            return $card;
        }, $cards);
    }
}
