<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class CtaBannerBlock extends Block
{
    public $name = 'CTA Banner (Globe)';

    public $slug = 'cta-banner';

    public $description = 'High-impact conversion banner featuring the brand globe illustration and direct booking actions.';

    public $category = 'remote-leverage';

    public $icon = 'megaphone';

    public $keywords = ['cta', 'banner', 'globe', 'contact', 'call to action'];

    public $view = 'blocks.cta-banner';

    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'Streamline Your Global Operations and Start Scaling',
            'subheadline' => get_field('subheadline') ?: 'Book a 15-minute alignment call with our senior operations director to build your nearshore team.',
            'globeImage' => get_field('globe_image') ?: null,
            'ctaText' => get_field('cta_text') ?: 'Book Your 15-Minute Strategy Call',
            'ctaUrl' => get_field('cta_url') ?: '#booking-wizard',
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('cta_banner_block');

        $fields
            ->addTextarea('headline', [
                'label' => 'Headline',
                'default_value' => 'Streamline Your Global Operations and Start Scaling',
                'rows' => 2,
            ])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'default_value' => 'Book a 15-minute alignment call with our senior operations director to build your nearshore team.',
                'rows' => 2,
            ])
            ->addImage('globe_image', [
                'label' => 'Globe Illustration (PNG/SVG)',
                'return_format' => 'url',
            ])
            ->addText('cta_text', [
                'label' => 'CTA Button Text',
                'default_value' => 'Book Your 15-Minute Strategy Call',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA Link URL',
                'default_value' => '#booking-wizard',
            ]);

        return $fields->build();
    }
}
