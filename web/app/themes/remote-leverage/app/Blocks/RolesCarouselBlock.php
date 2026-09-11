<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class RolesCarouselBlock extends Block
{
    public $name = 'Roles Carousel';

    public $slug = 'roles-carousel';

    public $description = 'Dark band with a horizontally scrolling carousel of purple role/industry cards.';

    public $category = 'remote-leverage';

    public $icon = 'slides';

    public $keywords = ['roles', 'industries', 'carousel', 'slider'];

    public $view = 'blocks.roles-carousel';

    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['headline' => 'Roles and Industries Remote Leverage Supports', 'is_preview' => true],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        $cards = array_values(array_filter(
            (array) ($field('cards') ?: []),
            fn ($c) => ! empty($c['title'])
        ));

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: ''),
            'cards' => array_map(fn ($c) => [
                'icon' => BlockDefaults::resolveImageUrl($c['icon'] ?? ''),
                'title' => BlockDefaults::cleanText($c['title'] ?? ''),
                'text' => $c['text'] ?? '',
            ], $cards),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('roles_carousel_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addRepeater('cards', ['label' => 'Cards', 'button_label' => 'Add card', 'min' => 1])
            ->addImage('icon', ['label' => 'Icon', 'return_format' => 'url'])
            ->addTextarea('title', ['label' => 'Title', 'rows' => 2, 'instructions' => 'Inline <br> allowed.'])
            ->addTextarea('text', ['label' => 'Text', 'rows' => 3])
            ->endRepeater();

        return $fields->build();
    }
}
