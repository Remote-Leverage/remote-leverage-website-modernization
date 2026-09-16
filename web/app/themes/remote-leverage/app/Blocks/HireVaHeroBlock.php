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
            // An explicitly empty badge means "no eyebrow pill" (production's /spanish/
            // shows none); only an unset field falls back to the default.
            'badgeText' => self::resolveBadgeText(get_field('badge_text')),
            'headline' => get_field('headline') ?: 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
            'bookingTitle' => get_field('booking_title') ?: 'Book a Free 15-Minute Consultation',
            'bookingSubtitle' => get_field('booking_subtitle') ?? '',
            'enableIsolatedFields' => $enableIsolatedFields,
            'isolatedSteps' => $isolatedSteps,
            'hideProfileHeader' => $hideProfileHeader,
            'hideProgressBar' => $hideProgressBar,
            'formButtonText' => get_field('form_button_text') ?: 'Find me an Assistant',
            'checklist' => self::resolveChecklist(get_field('checklist')),
            'mobileOrder' => get_field('mobile_order') === 'form-first' ? 'form-first' : 'checklist-first',
        ];
    }

    /**
     * Resolve the eyebrow pill text.
     *
     * A blank string is a deliberate "render no pill", which is why this cannot
     * be a plain `?:` fallback — /spanish/ passes an empty badge on purpose.
     */
    public static function resolveBadgeText(mixed $value): string
    {
        if ($value === null || $value === false) {
            return "2,000+ businesses we've helped hire";
        }

        return trim((string) $value);
    }

    /**
     * The hero's reassurance checklist.
     *
     * Was hardcoded in the Blade view until 2026-09-15; /spanish/ runs the same
     * hero with four Spanish items, so it became a field. An empty repeater
     * keeps the original six, leaving /hire-va-4/ and /hire-for-less/ unchanged.
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

        return $items === [] ? self::defaultChecklist() : $items;
    }

    /**
     * @return array<int, string>
     */
    public static function defaultChecklist(): array
    {
        return [
            'Interview Before You Hire',
            'Hire Direct - No Middleman',
            'No contracts',
            'Hire Within 72 Hours',
            'Fluent English',
            '30% Discount on Future Hires',
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
            ->addRepeater('checklist', [
                'label' => 'Reassurance Checklist',
                'instructions' => 'Leave empty to use the standard six English items. Adding rows replaces the whole list.',
                'layout' => 'table',
                'button_label' => 'Add Item',
            ])
            ->addText('item', [
                'label' => 'Item',
            ])
            ->endRepeater()
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
            ->addSelect('mobile_order', [
                'label' => 'Mobile Stacking Order',
                'instructions' => 'Which comes first once the hero stacks into one column. Production is not consistent: /hire-va-4/ and /hire-va-6/ put the booking card above the checklist, /sales-talents/ puts the checklist first. Desktop is unaffected either way.',
                'choices' => [
                    'checklist-first' => 'Checklist, then booking card',
                    'form-first' => 'Booking card, then checklist',
                ],
                'default_value' => 'checklist-first',
                'return_format' => 'value',
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
