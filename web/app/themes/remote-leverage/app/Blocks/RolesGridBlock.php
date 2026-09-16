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
            'adminTint' => $this->adminTint(),
            'ctaStyle' => (function_exists('get_field') ? get_field('cta_style') : null) ?: 'production',
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
            ->addSelect('cta_style', [
                'label' => 'CTA Button Style',
                'instructions' => 'Production is the plain-arrow pill measured on /hire-va-4/, /hire-va-6/ and '
                    .'/hire-va-1st-month-free/. Pill is the 2026 homepage button — larger label, circled chevron, '
                    .'outline ring — shared with the other CTAs down that page.',
                'choices' => [
                    'production' => 'Production arrow pill (default)',
                    'pill' => '2026 homepage pill',
                ],
                'default_value' => 'production',
                'return_format' => 'value',
            ])
            ->addSelect('admin_tint', [
                'label' => 'Administrative Card Tint',
                'instructions' => 'Dark is production\'s treatment: /hire-va-4/, /hire-va-6/ and '
                    .'/hire-va-1st-month-free/ all render this card #6341A2 with white text '
                    .'(measured 2026-09-15). Lavender makes it read as one of the light cards.',
                'choices' => [
                    'dark' => 'Dark — brand purple, white text (production)',
                    'lavender' => 'Light lavender — dark text',
                ],
                'default_value' => 'dark',
                'return_format' => 'value',
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

    /**
     * Administrative card tint.
     *
     * Defaults to 'dark', which is what every production page shipping this block renders,
     * so an unset field leaves those pages untouched.
     */
    public function adminTint(): string
    {
        $tint = function_exists('get_field') ? get_field('admin_tint') : null;

        return $tint === 'lavender' ? 'lavender' : 'dark';
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
