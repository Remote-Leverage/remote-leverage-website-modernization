<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class BookingBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Booking';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'booking';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Interactive multistep qualification and live scheduling funnel.';

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
    public $icon = 'calendar-alt';

    /**
     * The block keywords.
     *
     * @var array
     */
    public $keywords = ['booking', 'calendar', 'calendly', 'scheduling', 'consultation'];

    /**
     * The block view.
     *
     * @var string
     */
    public $view = 'blocks.booking';

    /**
     * Data to be passed to the block before rendering.
     */
    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'Schedule Your Free 30-Minute Staffing Consultation',
            'subheadline' => get_field('subheadline') ?: 'Find the perfect English-fluent Latin American specialist for your operations.',
            'showTrustBadges' => get_field('show_trust_badges') ?? true,
            'defaultRole' => get_field('default_role') ?: 'Executive Assistant',
        ];
    }

    /**
     * The block field group.
     */
    public function fields(): array
    {
        $fields = Builder::make('booking_block');

        $fields
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => 'Schedule Your Free 30-Minute Staffing Consultation',
            ])
            ->addTextarea('subheadline', [
                'label' => 'Section Subheadline',
                'default_value' => 'Find the perfect English-fluent Latin American specialist for your operations.',
                'rows' => 2,
            ])
            ->addTrueFalse('show_trust_badges', [
                'label' => 'Show Trust Badges',
                'default_value' => true,
                'ui' => 1,
            ])
            ->addSelect('default_role', [
                'label' => 'Pre-selected Role',
                'choices' => [
                    'Executive Assistant' => 'Executive Assistant',
                    'Real Estate Assistant' => 'Real Estate Assistant',
                    'E-commerce Manager' => 'E-commerce Manager',
                    'Marketing Specialist' => 'Marketing Specialist',
                ],
                'default_value' => 'Executive Assistant',
            ]);

        return $fields->build();
    }
}
