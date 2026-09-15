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

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => '100% Risk-Free Replacement Guarantee',
                'is_preview' => true,
            ],
        ],
    ];

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
            'background' => get_field('background') ?: 'radial-purple',
            // Unset means "show them" — only an explicit off hides the trio.
            'showReassuranceItems' => get_field('show_reassurance_items') === null
                ? true
                : (bool) get_field('show_reassurance_items'),
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
            ->addSelect('background', [
                'label' => 'Background',
                'choices' => [
                    'radial-purple' => 'Radial purple wash (hire-va pages)',
                    'flat-midnight' => 'Flat #250D4A (ecommerce page)',
                ],
                'default_value' => 'radial-purple',
            ])
            ->addTrueFalse('show_reassurance_items', [
                'label' => 'Show the three reassurance items',
                'instructions' => 'Off renders the guarantee badge alone, as production does on /ecommerce-virtual-assistant/.',
                'ui' => 1,
                'default_value' => 1,
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
