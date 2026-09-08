<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class GuaranteeCardBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Guarantee Card';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'guarantee-card';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = '12-Month Replacement Guarantee card with radial gradient and 3D badge illustration.';

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
    public $icon = 'shield';

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
            'headline' => get_field('headline') ?: '12-Month Replacement Guarantee',
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
        $fields = new FieldsBuilder('guarantee_card');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => '12-Month Replacement Guarantee',
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
