<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class BookingFooterBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Booking Footer';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'booking-footer';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Full-bleed booking funnel footer with 3 numbered steps and embedded scheduling wizard.';

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
                'title' => 'Ready to Find Your Next Assistant?',
                'is_preview' => true,
            ],
        ],
    ];

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'calendar-alt';

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
        // get_field() can fail to resolve the block's own data override when this
        // block is deep in a page with many preceding ACF blocks (ACF's block-id
        // hashing/meta-store lookup misses it), so read the raw block data first.
        $headline = $this->block->data['headline'] ?? get_field('headline');

        return [
            'headline' => $headline ?: 'Book a free consultation',
            'mapImage' => \App\Support\BlockDefaults::resolveImageUrl(
                (function_exists('get_field') ? get_field('map_image') : null) ?: 506
            ),
            'description' => (function_exists('get_field') ? get_field('description') : null)
                ?: 'During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.',
        ];
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $fields = new FieldsBuilder('booking_footer');

        $fields
            ->addImage('map_image', [
                'label' => 'Background Map',
                'return_format' => 'url',
                'instructions' => 'Sits over the dark band, as production does. Leave blank for the default world map.',
            ])
            ->addTextarea('description', [
                'label' => 'Description',
                'default_value' => 'During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.',
                'rows' => 3,
            ])
            ->addTextarea('headline', [
                'label' => 'Headline',
                'default_value' => 'Book a free consultation',
                'rows' => 2,
            ]);

        return $fields->build();
    }
}
