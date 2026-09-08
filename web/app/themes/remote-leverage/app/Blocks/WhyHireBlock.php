<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class WhyHireBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Why Hire';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'why-hire';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Why hire through Remote Leverage section with rating proof card and 4 feature cards.';

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
    public $icon = 'awards';

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
            'headline' => get_field('headline') ?: 'Why hire through Remote Leverage?',
            'proofTitle' => get_field('proof_title') ?: "We've helped more than 2,000 businesses hire top talent across LatAm, the Caribbean and the EU.",
        ];
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $fields = new FieldsBuilder('why_hire');

        $fields
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => 'Why hire through Remote Leverage?',
            ])
            ->addTextarea('proof_title', [
                'label' => 'Proof Card Title',
                'default_value' => "We've helped more than 2,000 businesses hire top talent across LatAm, the Caribbean and the EU.",
                'rows' => 3,
            ]);

        return $fields->build();
    }
}
