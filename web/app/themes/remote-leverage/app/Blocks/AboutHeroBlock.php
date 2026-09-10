<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AboutHeroBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'About Hero';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'about-hero';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Dark midnight hero with dotted 3D globe graphic, talent avatar pins, trust badge, and scaling teams logo marquee.';

    /**
     * The block category.
     *
     * @var string
     */
    public $category = 'remote-leverage';

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'globe';

    /**
     * The default block mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * The default block alignment.
     *
     * @var string
     */
    public $align = 'full';

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

    public $view = 'blocks.about-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'The world leader in staffing solutions',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        $globeImage = get_field('globe_image');
        $resolvedGlobe = ! empty($globeImage)
            ? BlockDefaults::resolveImageUrl($globeImage)
            : BlockDefaults::resolveImageUrl(BlockDefaults::getAttachmentId('globo-1-2.png'));

        return [
            'headline' => BlockDefaults::cleanText(get_field('headline') ?: 'The world leader in staffing solutions'),
            'subtitle' => BlockDefaults::cleanText(get_field('subtitle') ?: 'Great talent changes everything. <strong>Remote Leverage makes global hiring easier.</strong> We find top 1% global talent, you hire direct.'),
            'buttonText' => BlockDefaults::cleanText(get_field('button_text') ?: 'BOOK A CONSULTATION'),
            'buttonUrl' => BlockDefaults::cleanText(get_field('button_url') ?: '#booking-footer'),
            'badgeText' => BlockDefaults::cleanText(get_field('badge_text') ?: '2.5K+ pre-vetted candidates'),
            'globeImage' => $resolvedGlobe,
            'trustedTitle' => BlockDefaults::cleanText(get_field('trusted_title') ?: 'TRUSTED BY SCALING TEAMS GLOBALLY'),
            'logos' => $this->logos(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('about_hero_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'The world leader in staffing solutions',
            ])
            ->addTextarea('subtitle', [
                'label' => 'Subtitle (HTML Allowed for strong tags)',
                'default_value' => 'Great talent changes everything. <strong>Remote Leverage makes global hiring easier.</strong> We find top 1% global talent, you hire direct.',
                'rows' => 3,
            ])
            ->addText('button_text', [
                'label' => 'Button Text',
                'default_value' => 'BOOK A CONSULTATION',
            ])
            ->addText('button_url', [
                'label' => 'Button URL',
                'default_value' => '#booking-footer',
            ])
            ->addText('badge_text', [
                'label' => 'Badge Text',
                'default_value' => '2.5K+ pre-vetted candidates',
            ])
            ->addImage('globe_image', [
                'label' => 'Globe Graphic',
                'return_format' => 'url',
            ])
            ->addText('trusted_title', [
                'label' => 'Trusted Logos Title',
                'default_value' => 'TRUSTED BY SCALING TEAMS GLOBALLY',
            ]);

        return $fields->build();
    }

    public function logos(): array
    {
        return BlockDefaults::logos();
    }
}
