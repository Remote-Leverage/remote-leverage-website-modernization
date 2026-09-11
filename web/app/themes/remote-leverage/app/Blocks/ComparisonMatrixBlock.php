<?php

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class ComparisonMatrixBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Comparison Matrix';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'comparison-matrix';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Skip the Hiring Headache comparison between hiring on your own vs Remote Leverage.';

    /**
     * The block category.
     *
     * @var string
     */
    public $category = 'remote-leverage';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Remote Leverage vs. The Alternatives',
                'is_preview' => true,
            ],
        ],
    ];

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'columns';

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

    /**
     * Data to be passed to the view before rendering.
     *
     * @return array
     */
    public function with()
    {
        $layout = get_field('layout') ?: 'stacked';
        $isSplit = $layout === 'split';

        return [
            'layout' => $layout,
            'headline' => get_field('headline') ?: 'Skip the Hiring Headache',
            'subheadline' => get_field('subheadline') ?: '70% Lower Cost, Same Quality',
            'ctaText' => get_field('cta_text') ?: 'BOOK MY FREE 15-MIN CALL',
            'ctaUrl' => get_field('cta_url') ?: '#booking-footer',
            'card1Pill' => get_field('card_1_pill') ?: 'Traditional DIY',
            'card1Title' => get_field('card_1_title') ?: 'Hiring on your own',
            'card1Line1' => get_field('card_1_line_1') ?: '4 to 8 weeks of posting, screening, and interviewing.',
            'card1Line2' => get_field('card_1_line_2') ?: ($isSplit ? '' : 'Payroll, taxes, and compliance all on you.'),
            'card2Pill' => get_field('card_2_pill') ?: 'Remote Leverage Way',
            'card2Title' => get_field('card_2_title') ?: 'Hiring with Remote Leverage',
            'card2Line1' => get_field('card_2_line_1') ?: 'Interview the top 1% in 72 hours.',
            'card2Line2' => get_field('card_2_line_2') ?: ($isSplit ? '' : 'We handle payroll, compliance, and onboarding.'),
            'card1Image' => BlockDefaults::resolveImageUrl(get_field('card_1_image')),
            'card2Image' => BlockDefaults::resolveImageUrl(get_field('card_2_image')),
        ];
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $fields = new FieldsBuilder('comparison_matrix');

        $fields
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => [
                    'stacked' => 'Stacked — centered heading above one unified card (hire-va-4)',
                    'split' => 'Split — two photo cards left, heading and CTA right (comparison pages)',
                ],
                'default_value' => 'stacked',
                'instructions' => 'Stacked is the original layout. Split mirrors the competitor-comparison pages on production.',
            ])
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Skip the Hiring Headache',
            ])
            ->addText('subheadline', [
                'label' => 'Subheadline',
                'default_value' => '70% Lower Cost, Same Quality',
            ])
            ->addText('cta_text', [
                'label' => 'CTA Button Text',
                'default_value' => 'BOOK MY FREE 15-MIN CALL',
            ])
            ->addText('cta_url', [
                'label' => 'CTA Button URL',
                'default_value' => '#booking-footer',
            ])
            ->addText('card_1_pill', [
                'label' => 'Left Card Pill Label',
                'default_value' => 'Traditional DIY',
            ])
            ->addText('card_1_title', [
                'label' => 'Left Card Title',
                'default_value' => 'Hiring on your own',
            ])
            ->addText('card_1_line_1', [
                'label' => 'Left Card Line 1',
                'default_value' => '4 to 8 weeks of posting, screening, and interviewing.',
            ])
            ->addText('card_1_line_2', [
                'label' => 'Left Card Line 2',
                'default_value' => 'Payroll, taxes, and compliance all on you.',
            ])
            ->addText('card_2_pill', [
                'label' => 'Right Card Pill Label',
                'default_value' => 'Remote Leverage Way',
            ])
            ->addText('card_2_title', [
                'label' => 'Right Card Title',
                'default_value' => 'Hiring with Remote Leverage',
            ])
            ->addText('card_2_line_1', [
                'label' => 'Right Card Line 1',
                'default_value' => 'Interview the top 1% in 72 hours.',
            ])
            ->addText('card_2_line_2', [
                'label' => 'Right Card Line 2',
                'default_value' => 'We handle payroll, compliance, and onboarding.',
            ])
            ->addImage('card_1_image', [
                'label' => 'Left Card Icon / Logo',
                'return_format' => 'url',
            ])
            ->addImage('card_2_image', [
                'label' => 'Right Card Icon / Logo',
                'return_format' => 'url',
            ]);

        return $fields->build();
    }
}
