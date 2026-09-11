<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ComparisonHeroBlock extends Block
{
    public $name = 'Comparison Hero';

    public $slug = 'comparison-hero';

    public $description = 'Dark full-bleed hero for the competitor-comparison pages: headline, subheadline, CTA pill, and a right-hand hero graphic.';

    public $category = 'remote-leverage';

    public $icon = 'align-left';

    public $keywords = ['comparison', 'hero', 'competitor', 'alternative', 'versus'];

    public $view = 'blocks.comparison-hero';

    /**
     * The supported block features.
     *
     * @var array
     */
    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => false,
    ];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Looking for a smarter Wing Assistant alternative?',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $hasGetField = function_exists('get_field');
        $field = fn (string $key) => $hasGetField ? get_field($key) : null;

        return [
            'headline' => BlockDefaults::cleanText($field('headline') ?: 'Looking for a smarter<br>Wing Assistant alternative?'),
            'subheadline' => BlockDefaults::cleanText($field('subheadline') ?: 'Discover how bypassing the managed agency model allows you to hire elite, vetted professionals directly and permanently.'),
            'ctaText' => $field('cta_text') ?: 'Book a consultation',
            'ctaUrl' => $field('cta_url') ?: '#booking-footer',
            'heroImage' => BlockDefaults::resolveImageUrl($field('hero_image') ?: ''),
            'backgroundImage' => BlockDefaults::resolveImageUrl($field('background_image') ?: ''),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('comparison_hero_block');

        $fields
            ->addTextarea('headline', [
                'label' => 'Headline',
                'default_value' => 'Looking for a smarter Wing Assistant alternative?',
                'instructions' => 'Inline HTML allowed (e.g. <br> to control the line break).',
                'rows' => 2,
            ])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'default_value' => 'Discover how bypassing the managed agency model allows you to hire elite, vetted professionals directly and permanently.',
                'rows' => 3,
            ])
            ->addText('cta_text', [
                'label' => 'CTA Text',
                'default_value' => 'Book a consultation',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA URL',
                'default_value' => '#booking-footer',
            ])
            ->addImage('hero_image', [
                'label' => 'Hero Graphic (right column)',
                'return_format' => 'url',
            ])
            ->addImage('background_image', [
                'label' => 'Background Image',
                'return_format' => 'url',
                'instructions' => 'Full-bleed dark background behind the hero.',
            ]);

        return $fields->build();
    }
}
