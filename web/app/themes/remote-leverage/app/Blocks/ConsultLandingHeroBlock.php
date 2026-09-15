<?php

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class ConsultLandingHeroBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Consultation Landing Hero';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'consult-landing-hero';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = "Production's consultation landing-page hero (/1monthonus-flp/, /hire-va-email/, the isolated-form-fields variants and /hire-real-estate-virtual-assistants-flp/): a compact black-to-magenta banner band carrying the headline, a short intro, a single-column tick list, and a translucent glass card holding the booking wizard with its email field isolated as step one.";

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
                'headline' => 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
                'booking_title' => 'Book a Free Consultation',
                'is_preview' => true,
            ],
        ],
    ];

    /**
     * The block icon.
     *
     * @var string|array
     */
    public $icon = 'megaphone';

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
            'headline' => get_field('headline') ?: 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
            'intro' => get_field('intro') ?: '',
            'checklist' => self::resolveChecklist(get_field('checklist')),
            'tickColor' => get_field('tick_color') ?: '#10B981',
            'backgroundImage' => BlockDefaults::resolveImageUrl(get_field('background_image'))
                ?: BlockDefaults::pageImg('consultation-landing', 'banner-02.jpg'),
            'bookingTitle' => get_field('booking_title') ?: 'Book a Free Consultation',
            'formButtonText' => get_field('form_button_text') ?: 'Find me an Assistant',
            'isolatedSteps' => self::resolveIsolatedSteps(get_field('isolated_steps')),
        ];
    }

    /**
     * The reassurance tick list.
     *
     * An empty repeater keeps production's standard four items; /hire-real-estate-
     * virtual-assistants-flp/ differs only in promising a 48-hour match.
     *
     * @return array<int, string>
     */
    public static function resolveChecklist(mixed $rows): array
    {
        $items = is_array($rows)
            ? array_values(array_filter(array_map(
                static fn ($row) => trim((string) ($row['item'] ?? '')),
                $rows,
            )))
            : [];

        return $items === [] ? [
            'No contracts or recurring fees',
            'Get matched within 72 hours',
            'Fluent English + U.S. time zones',
            '12-month replacement guarantee',
        ] : $items;
    }

    /**
     * The booking wizard's progressive sub-steps.
     *
     * Every page in this family asks for the business email alone before revealing
     * the rest, which is what the "isolated form fields" slugs refer to.
     *
     * @return array<int, array{step_label: string, step_fields: array<int, string>}>
     */
    public static function resolveIsolatedSteps(mixed $rows): array
    {
        $steps = [];

        if (is_array($rows)) {
            foreach ($rows as $step) {
                if (empty($step['step_fields'])) {
                    continue;
                }

                $steps[] = [
                    'step_label' => $step['step_label'] ?? 'Step',
                    'step_fields' => is_array($step['step_fields'])
                        ? array_values($step['step_fields'])
                        : [$step['step_fields']],
                ];
            }
        }

        return $steps === [] ? [
            ['step_label' => 'Email', 'step_fields' => ['email']],
            ['step_label' => 'Complete First Step', 'step_fields' => ['monthly_revenue', 'name', 'phone', 'consent']],
        ] : $steps;
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $fields = new FieldsBuilder('consult_landing_hero');

        $fields
            ->addTextarea('headline', [
                'label' => 'Headline (HTML allowed)',
                'default_value' => 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
                'rows' => 3,
            ])
            ->addTextarea('intro', [
                'label' => 'Intro Paragraph (HTML allowed)',
                'instructions' => 'Sits between the headline and the tick list.',
                'rows' => 3,
            ])
            ->addRepeater('checklist', [
                'label' => 'Reassurance Tick List',
                'instructions' => 'Leave empty to use production\'s standard four items.',
                'layout' => 'table',
                'button_label' => 'Add Item',
            ])
            ->addText('item', ['label' => 'Item'])
            ->endRepeater()
            ->addText('tick_color', [
                'label' => 'Tick Colour',
                'instructions' => 'Production runs emerald #10B981 on some pages and pink #F50084 on others.',
                'default_value' => '#10B981',
            ])
            ->addImage('background_image', [
                'label' => 'Banner Background',
                'return_format' => 'url',
                'instructions' => 'Defaults to the shared black-to-magenta banner.',
            ])
            ->addText('booking_title', [
                'label' => 'Booking Card Title',
                'default_value' => 'Book a Free Consultation',
            ])
            ->addText('form_button_text', [
                'label' => 'Step 1 Button Text',
                'default_value' => 'Find me an Assistant',
            ])
            ->addRepeater('isolated_steps', [
                'label' => 'Isolated Sub-Steps Sequence',
                'instructions' => 'Leave empty to reveal the business email first, then the remaining fields.',
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
                    'name' => 'Name',
                    'phone' => 'Phone',
                    'monthly_revenue' => 'Monthly Revenue',
                    'consent' => 'Consent',
                ],
                'multiple' => 1,
                'ui' => 1,
            ])
            ->endRepeater();

        return $fields->build();
    }
}
