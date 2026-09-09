<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class RolesGridBlock extends Block
{
    public $name = 'Roles Grid';

    public $slug = 'roles-grid';

    public $description = 'Showcase of 8 specialized roles that buy back your time.';

    public $category = 'remote-leverage';

    public $icon = 'groups';

    public $keywords = ['roles', 'departments', 'virtual assistant', 'specialties', 'hiring'];

    public $view = 'blocks.roles-grid';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Pre-Vetted Roles Ready to Deploy',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'eyebrow' => BlockDefaults::cleanText((function_exists('get_field') ? get_field('eyebrow') : null) ?: '2.5K+ pre-vetted candidates'),
            'headline' => BlockDefaults::cleanText((function_exists('get_field') ? get_field('headline') : null) ?: 'The Roles That Buy Back Your Time'),
            'ctaText' => BlockDefaults::cleanText((function_exists('get_field') ? get_field('cta_text') : null) ?: 'BOOK A FREE CONSULTATION'),
            'ctaUrl' => (function_exists('get_field') ? get_field('cta_url') : null) ?: '#booking-footer',
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('roles_grid_block');

        $fields
            ->addText('eyebrow', [
                'label' => 'Eyebrow / Badge Text',
                'default_value' => '2.5K+ pre-vetted candidates',
            ])
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => 'The Roles That Buy Back Your Time',
            ])
            ->addText('cta_text', [
                'label' => 'CTA Button Text',
                'default_value' => 'BOOK A FREE CONSULTATION',
            ])
            ->addText('cta_url', [
                'label' => 'CTA Button Target URL',
                'default_value' => '#booking-footer',
            ])
            ->addRepeater('cards', [
                'label' => 'Role Cards (Leave empty to use preset defaults)',
                'layout' => 'block',
                'button_label' => 'Add Role Card',
            ])
            ->addText('title', ['label' => 'Role Title'])
            ->addTextarea('desc', ['label' => 'Role Description', 'rows' => 2])
            ->addImage('img', ['label' => 'Role Visual / Photo', 'return_format' => 'url'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $custom = function_exists('get_field') ? get_field('cards') : null;
        $cards = (! empty($custom) && is_array($custom))
            ? $custom
            : BlockDefaults::rolesGridCards();

        return array_map(function ($card) {
            $card['title'] = BlockDefaults::cleanText($card['title'] ?? '');
            $card['desc'] = BlockDefaults::cleanText($card['desc'] ?? '');
            $card['img'] = BlockDefaults::resolveImageUrl($card['img'] ?? '');

            return $card;
        }, $cards);
    }
}
