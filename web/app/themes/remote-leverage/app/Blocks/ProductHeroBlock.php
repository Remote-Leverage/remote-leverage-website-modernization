<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ProductHeroBlock extends Block
{
    public $name = 'Product Hero';

    public $slug = 'product-hero';

    public $description = 'Hero section for product/service pages with dark violet background, value props, CTAs, and stat tiles.';

    public $category = 'remote-leverage';

    public $icon = 'superhero-alt';

    public $keywords = ['hero', 'product', 'contractor', 'management', 'payments'];

    public $view = 'blocks.product-hero';

    public $supports = [
        'align' => ['full', 'wide'],
    ];

    public function with(): array
    {
        return [
            'badge' => (function_exists('get_field') ? get_field('badge') : null) ?: '',
            'headline' => (function_exists('get_field') ? get_field('headline') : null) ?: 'Contractor Management for Remote Teams',
            'subtitle' => (function_exists('get_field') ? get_field('subtitle') : null) ?: 'Onboard, pay, and manage your contractors anywhere in the world. All in one service.',
            'cta_primary_text' => (function_exists('get_field') ? get_field('cta_primary_text') : null) ?: 'Book a Demo',
            'cta_primary_url' => (function_exists('get_field') ? get_field('cta_primary_url') : null) ?: '#booking-footer',
            'cta_secondary_text' => (function_exists('get_field') ? get_field('cta_secondary_text') : null) ?: 'Explore Platform',
            'cta_secondary_url' => (function_exists('get_field') ? get_field('cta_secondary_url') : null) ?: '#features',
            'image_file' => (function_exists('get_field') ? get_field('image_file') : null) ?: '',
            'stats' => $this->stats(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('product_hero_block');

        $fields
            ->addText('badge', [
                'label' => 'Eyebrow Badge',
                'placeholder' => 'e.g. Contractor of Record & Workforce Management',
            ])
            ->addText('headline', [
                'label' => 'Hero Headline',
                'default_value' => 'Contractor Management for Remote Teams',
            ])
            ->addTextarea('subtitle', [
                'label' => 'Hero Subtitle',
                'rows' => 3,
                'default_value' => 'Onboard, pay, and manage your contractors anywhere in the world. All in one service.',
            ])
            ->addText('cta_primary_text', ['label' => 'Primary CTA Text', 'default_value' => 'Book a Demo'])
            ->addText('cta_primary_url', ['label' => 'Primary CTA URL', 'default_value' => '#booking-footer'])
            ->addText('cta_secondary_text', ['label' => 'Secondary CTA Text', 'default_value' => 'Explore Platform'])
            ->addText('cta_secondary_url', ['label' => 'Secondary CTA URL', 'default_value' => '#features'])
            ->addRepeater('stats', [
                'label' => 'Stat Metric Tiles (3 items)',
                'layout' => 'table',
                'button_label' => 'Add Stat',
            ])
            ->addText('value', ['label' => 'Value', 'placeholder' => '2,000+'])
            ->addText('label', ['label' => 'Label', 'placeholder' => 'Contractors managed'])
            ->endRepeater();

        return $fields->build();
    }

    public function stats(): array
    {
        $custom = function_exists('get_field') ? get_field('stats') : null;
        if (! empty($custom) && is_array($custom)) {
            return $custom;
        }

        return [
            ['value' => '2,000+', 'label' => 'Contractors active'],
            ['value' => '25+', 'label' => 'Countries covered'],
            ['value' => '24 hrs', 'label' => 'Average onboarding'],
        ];
    }
}
