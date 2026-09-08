<?php

declare(strict_types=1);

namespace App\Blocks;

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

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        $imgBase = get_template_directory_uri() . '/public/images/home';

        return [
            [
                'img' => $imgBase . '/magnific_half-body-shot-of-a-young_SOmwQLyUb8-1.webp',
                'title' => 'Administrative &amp;<br>Executive Assistants',
                'desc' => 'Executive support for busy founders and teams.',
            ],
            [
                'img' => $imgBase . '/magnific_wPmw8Jk7EI-1.webp',
                'title' => 'Healthcare &amp;<br>Medical Assistants',
                'desc' => 'Healthcare professionals supporting clinics and practices.',
            ],
            [
                'img' => $imgBase . '/magnific_ubzu0aUQLD-1.webp',
                'title' => 'Sales &amp; Growth<br>Marketing Talents',
                'desc' => 'Professionals focused on growth, leads, and revenue.',
            ],
            [
                'img' => $imgBase . '/magnific_YVjYLdkWeC-1.webp',
                'title' => 'Operations &amp;<br>Finance Professionals',
                'desc' => 'Experts in finance, operations, and business support.',
            ],
        ];
    }
}
