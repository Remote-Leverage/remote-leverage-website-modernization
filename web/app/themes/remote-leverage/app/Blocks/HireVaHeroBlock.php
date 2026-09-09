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

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Latin American Virtual Assistants',
                'badge_text' => '2,000+ Businesses Helped',
                'is_preview' => true,
            ],
        ],
    ];

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
        $rawIsolatedSteps = get_field('isolated_steps');
        $isolatedSteps = [];
        if (! empty($rawIsolatedSteps) && is_array($rawIsolatedSteps)) {
            foreach ($rawIsolatedSteps as $step) {
                if (! empty($step['step_fields'])) {
                    $isolatedSteps[] = [
                        'step_label' => $step['step_label'] ?? 'Step',
                        'step_fields' => is_array($step['step_fields']) ? array_values($step['step_fields']) : [$step['step_fields']],
                    ];
                }
            }
        }

        if (empty($isolatedSteps)) {
            $isolatedSteps = [
                [
                    'step_label' => 'Email',
                    'step_fields' => ['email'],
                ],
                [
                    'step_label' => 'Complete First Step',
                    'step_fields' => ['monthly_revenue', 'name', 'phone', 'consent'],
                ],
            ];
        }

        $enableIsolated = get_field('enable_isolated_fields');
        $enableIsolatedFields = ! empty($rawIsolatedSteps) || ($enableIsolated === null || $enableIsolated === '' ? true : (bool) $enableIsolated);

        $hideHeader = get_field('hide_profile_header');
        $hideProfileHeader = $hideHeader === null ? true : (bool) $hideHeader;

        $hideProgress = get_field('hide_progress_bar');
        $hideProgressBar = $hideProgress === null ? true : (bool) $hideProgress;

        return [
            'badgeText' => get_field('badge_text') ?: "2,000+ businesses we've helped hire",
            'headline' => get_field('headline') ?: 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
            'bookingTitle' => get_field('booking_title') ?: 'Book a Free 15-Minute Consultation',
            'bookingSubtitle' => get_field('booking_subtitle') ?? '',
            'enableIsolatedFields' => $enableIsolatedFields,
            'isolatedSteps' => $isolatedSteps,
            'hideProfileHeader' => $hideProfileHeader,
            'hideProgressBar' => $hideProgressBar,
            'formButtonText' => get_field('form_button_text') ?: 'Find me an Assistant',
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
            ->addTab('hero_content', [
                'label' => 'Hero Content',
            ])
            ->addText('badge_text', [
                'label' => 'Eyebrow Badge Text',
                'default_value' => "2,000+ businesses we've helped hire",
            ])
            ->addTextarea('headline', [
                'label' => 'Headline (HTML allowed)',
                'default_value' => 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
                'rows' => 3,
            ])
            ->addText('booking_title', [
                'label' => 'Booking Card Title',
                'default_value' => 'Book a Free 15-Minute Consultation',
            ])
            ->addText('booking_subtitle', [
                'label' => 'Booking Card Subtitle',
                'default_value' => '',
            ])
            ->addTab('form_isolated_fields', [
                'label' => 'Form & Isolated Fields',
            ])
            ->addTrueFalse('enable_isolated_fields', [
                'label' => 'Enable Progressive Isolated Fields?',
                'instructions' => 'When enabled, fields in Step 1 are revealed sequentially in sub-steps (matching rl-elementor-blocks).',
                'default_value' => 1,
                'ui' => 1,
            ])
            ->addTrueFalse('hide_profile_header', [
                'label' => 'Hide Profile Header',
                'instructions' => 'Hide host profile information bar inside the booking card.',
                'default_value' => 1,
                'ui' => 1,
            ])
            ->addTrueFalse('hide_progress_bar', [
                'label' => 'Hide Progress Bar',
                'instructions' => 'Hide 1-2-3-4 step progress indicators inside the booking card.',
                'default_value' => 1,
                'ui' => 1,
            ])
            ->addText('form_button_text', [
                'label' => 'Step 1 Button Text',
                'default_value' => 'Find me an Assistant',
            ])
            ->addRepeater('isolated_steps', [
                'label' => 'Isolated Sub-Steps Sequence',
                'instructions' => 'Configure which fields appear in each sub-step. Each sub-step is revealed sequentially as previous fields are filled.',
                'layout' => 'block',
                'button_label' => 'Add Sub-Step',
            ])
            ->addText('step_label', [
                'label' => 'Sub-Step Label',
                'default_value' => 'Step',
            ])
            ->addSelect('step_fields', [
                'label' => 'Fields in this Sub-Step',
                'choices' => [
                    'email' => 'Work / Business Email',
                    'monthly_revenue' => 'Monthly Revenue',
                    'name' => 'Name (First & Last)',
                    'phone' => 'Phone Number',
                    'consent' => 'Terms & SMS Consent Checkbox',
                ],
                'multiple' => 1,
                'ui' => 1,
                'return_format' => 'value',
            ])
            ->endRepeater();

        return $hero->build();
    }
}
