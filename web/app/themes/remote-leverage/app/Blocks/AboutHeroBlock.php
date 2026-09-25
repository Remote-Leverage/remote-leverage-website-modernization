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
    public $description = 'Dark midnight hero with dotted 3D globe graphic, trust badge, and scaling teams logo marquee. The `block_type` field switches the headline/subtitle/CTA between InnerBlocks (edit in place) and the legacy ACF text fields.';

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
        'jsx' => true,
    ];

    public $view = 'blocks.about-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
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
            'blockType' => get_field('block_type') ?: 'acf',
            'headline' => BlockDefaults::cleanText(get_field('headline') ?: 'The world leader in staffing solutions'),
            'subtitle' => BlockDefaults::cleanText(get_field('subtitle') ?: 'Great talent changes everything. <strong>Remote Leverage makes global hiring easier.</strong> We find top 1% global talent, you hire direct.'),
            'buttonText' => BlockDefaults::cleanText(get_field('button_text') ?: 'BOOK A CONSULTATION'),
            'buttonUrl' => BlockDefaults::cleanText(get_field('button_url') ?: '#booking-footer'),
            'badgeText' => BlockDefaults::cleanText(get_field('badge_text') ?: '2.5K+ pre-vetted candidates'),
            'globeImage' => $resolvedGlobe,
            'trustedTitle' => BlockDefaults::cleanText(get_field('trusted_title') ?: 'TRUSTED BY SCALING TEAMS GLOBALLY'),
            'logos' => $this->logos(),
            'ctaTemplate' => wp_json_encode(self::ctaTemplate()),
            'ctaAllowedBlocks' => wp_json_encode(['core/heading', 'core/paragraph', 'core/buttons', 'core/button']),
        ];
    }

    /**
     * Default InnerBlocks content for the headline/subtitle/CTA column: seeds a freshly
     * inserted block and is JSON-encoded onto the `<InnerBlocks template="..." />` attribute
     * in the Blade view. `supports.jsx` (above) is what makes ACF hydrate that literal HTML
     * tag into a real, template-locked InnerBlocks area instead of leaving it as plain text —
     * the attribute value itself must be `wp_json_encode()`'d PHP, not JSX/JS object syntax.
     */
    public static function ctaTemplate(): array
    {
        return [
            ['core/heading', [
                'level' => 1,
                // `text-white!` (Tailwind v4 trailing-bang important), not plain `text-white`:
                // core/heading always carries WordPress's own `.wp-block-heading` class, whose
                // global-styles text color ties our utility class on specificity and wins on
                // source order on the front end (the editor iframe doesn't load that same
                // global style, which is why this looked fine there and only broke live).
                'className' => 'font-display text-4xl sm:text-5xl lg:text-[56px] font-bold text-white! tracking-[-0.03em] leading-[1.1] mb-6',
                'content' => 'The world leader in staffing solutions',
            ]],
            ['core/paragraph', [
                'className' => 'text-base sm:text-lg text-white/80 leading-relaxed mb-8 max-w-xl',
                'content' => 'Great talent changes everything. <strong>Remote Leverage makes global hiring easier.</strong> We find top 1% global talent, you hire direct.',
            ]],
            ['core/buttons', [], [
                ['core/button', [
                    'text' => 'BOOK A CONSULTATION',
                    'url' => '#booking-footer',
                    'className' => 'is-style-pill-purple',
                ]],
            ]],
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('about_hero_block');

        $fields
            ->addSelect('block_type', [
                'label' => 'Content editing mode',
                'instructions' => 'ACF Fields: use the text fields below for the headline, subtitle and CTA button. Inner Blocks: edit them in place in the canvas as native blocks instead.',
                'choices' => [
                    'acf' => 'ACF Fields (default)',
                    'inner_blocks' => 'Inner Blocks (edit in place)',
                ],
                'default_value' => 'acf',
            ])
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'The world leader in staffing solutions',
            ])
            ->conditional('block_type', '==', 'acf')
            ->addTextarea('subtitle', [
                'label' => 'Subtitle (HTML Allowed for strong tags)',
                'default_value' => 'Great talent changes everything. <strong>Remote Leverage makes global hiring easier.</strong> We find top 1% global talent, you hire direct.',
                'rows' => 3,
            ])
            ->conditional('block_type', '==', 'acf')
            ->addText('button_text', [
                'label' => 'Button Text',
                'default_value' => 'BOOK A CONSULTATION',
            ])
            ->conditional('block_type', '==', 'acf')
            ->addText('button_url', [
                'label' => 'Button URL',
                'default_value' => '#booking-footer',
            ])
            ->conditional('block_type', '==', 'acf')
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
