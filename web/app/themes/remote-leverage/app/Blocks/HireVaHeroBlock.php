<?php

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class HireVaHeroBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Hire VA Hero';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'hire-va-hero';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'Split hero section with value proposition, checklist, live call button, and embedded booking wizard.';

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
    public $icon = 'cover-image';

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
            'badgeText' => get_field('badge_text') ?: "2,000+ businesses we've helped hire",
            'headline' => get_field('headline') ?: "Latin American<br>Virtual Assistants<br><span class=\"text-brand-purple\">$6–$10 Per Hour</span>",
            'bookingTitle' => get_field('booking_title') ?: 'Book a Free 15-Minute Consultation',
            'bookingSubtitle' => get_field('booking_subtitle') ?: "Tell us what you need. We'll find your match in 72 hours.",
        ];
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $hero = new FieldsBuilder('hire_va_hero');

        $hero
            ->addText('badge_text', [
                'label' => 'Eyebrow Badge Text',
                'default_value' => "2,000+ businesses we've helped hire",
            ])
            ->addTextarea('headline', [
                'label' => 'Headline (HTML allowed)',
                'default_value' => "Latin American<br>Virtual Assistants<br><span class=\"text-brand-purple\">$6–$10 Per Hour</span>",
                'rows' => 3,
            ])
            ->addText('booking_title', [
                'label' => 'Booking Card Title',
                'default_value' => 'Book a Free 15-Minute Consultation',
            ])
            ->addText('booking_subtitle', [
                'label' => 'Booking Card Subtitle',
                'default_value' => "Tell us what you need. We'll find your match in 72 hours.",
            ]);

        return $hero->build();
    }
}
