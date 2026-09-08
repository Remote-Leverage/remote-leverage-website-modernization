<?php

namespace App\Blocks;

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
        return [
            'headline' => get_field('headline') ?: 'Skip the Hiring Headache',
            'subheadline' => get_field('subheadline') ?: '70% Lower Cost, Same Quality',
            'ctaText' => get_field('cta_text') ?: 'BOOK MY FREE 15-MIN CALL',
            'ctaUrl' => get_field('cta_url') ?: '#booking-footer',
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
            ]);

        return $fields->build();
    }
}
