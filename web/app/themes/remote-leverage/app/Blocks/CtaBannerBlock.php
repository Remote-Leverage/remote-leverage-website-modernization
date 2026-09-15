<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
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

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Ready to Save 70% on Top Talent?',
                'button_text' => 'Schedule Free Consultation',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'Streamline Your Global Operations and Start Scaling',
            'subheadline' => get_field('subheadline') ?: 'Book a 15-minute alignment call with our senior operations director to build your nearshore team.',
            'globeImage' => get_field('globe_image') ?: null,
            'ctaText' => get_field('cta_text') ?: 'Book Your 15-Minute Strategy Call',
            'ctaUrl' => get_field('cta_url') ?: '#booking-wizard',
            // 'band' is production's flat centred CTA strip; 'card' is the gradient panel.
            'variant' => get_field('variant') ?: 'card',
            // Optional artwork behind the 'band' variant. /referral-program/ closes on a wave
            // illustration rather than the flat purple gradient; empty keeps the gradient.
            'backgroundImage' => BlockDefaults::resolveImageUrl(get_field('background_image')) ?: '',
            // 'light' swaps the purple band for #F4F6FC with black copy and a purple pill.
            'tone' => get_field('tone') ?: 'purple-gradient',
            // Overridable gradient stops — production's reviews strip runs #250D4A → #581FB0.
            'gradientStart' => get_field('gradient_start') ?: '',
            'gradientEnd' => get_field('gradient_end') ?: '',
            // 'split' puts the heading left and the CTA right instead of centring both.
            'align' => get_field('align') ?: 'center',
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
            ->addSelect('variant', [
                'label' => 'Style',
                'choices' => ['card' => 'Gradient card (default)', 'band' => 'Flat centred band'],
                'default_value' => 'card',
            ])
            ->addSelect('tone', [
                'label' => 'Band tone',
                'choices' => [
                    'purple-gradient' => 'Purple gradient, white copy (default)',
                    'light' => 'Light #F4F6FC, black copy, purple pill',
                ],
                'default_value' => 'purple-gradient',
            ])
            ->addText('gradient_start', [
                'label' => 'Gradient start colour',
                'instructions' => 'Optional hex. Blank uses #8A2BE2.',
            ])
            ->addText('gradient_end', [
                'label' => 'Gradient end colour',
                'instructions' => 'Optional hex. Blank uses #6200A4.',
            ])
            ->addSelect('align', [
                'label' => 'Band alignment',
                'choices' => ['center' => 'Centred (default)', 'split' => 'Heading left, CTA right'],
                'default_value' => 'center',
            ])
            ->addImage('background_image', [
                'label' => 'Band background image',
                'instructions' => 'Optional artwork behind the flat band. Leave empty for the purple gradient.',
                'return_format' => 'url',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA Link URL',
                'default_value' => '#booking-wizard',
            ]);

        return $fields->build();
    }
}
