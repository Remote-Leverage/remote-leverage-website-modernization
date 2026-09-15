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
    public $description = "Shared landing-page hero in two skins. 'Consultation' (the default) is production's consultation landing hero (/1monthonus-flp/, /hire-va-email/, the isolated-form-fields variants and /hire-real-estate-virtual-assistants-flp/): a compact black-to-magenta banner band carrying the headline, a short intro, a single-column tick list, and a translucent glass card holding the booking wizard with its email field isolated as step one. 'VA roles' is the older /1monthonus/, /hire-va-isolated-form/ and /hire-va/ family: the same shape on a 780px violet band with a gold-gradient headline line, a two-column tick list and a solid white booking card.";

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
        $variant = self::resolveVariant(get_field('variant'));
        $isVaRoles = $variant === 'va-roles';
        $backgroundImage = BlockDefaults::resolveImageUrl(get_field('background_image'));

        return [
            'variant' => $variant,
            'headline' => get_field('headline') ?: 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
            'headlineGradient' => get_field('headline_gradient') ?: '',
            'headlineSize' => get_field('headline_size') === '80' ? '80' : '64',
            'intro' => get_field('intro') ?: '',
            'checklist' => self::resolveChecklist(get_field('checklist'), $isVaRoles),
            'tickColor' => get_field('tick_color') ?: '#10B981',

            // The consultation skin always paints the shared banner; the va-roles band is a bare
            // gradient unless the page supplies art, so it gets no fallback.
            'backgroundImage' => $isVaRoles
                ? $backgroundImage
                : ($backgroundImage ?: BlockDefaults::pageImg('consultation-landing', 'banner-02.jpg')),
            'backgroundImageClass' => (string) (get_field('background_image_class') ?: ''),
            'splitAt' => get_field('split_at') === '2xl' ? '2xl' : 'xl',
            'ctaText' => get_field('cta_text') ?: '',
            'ctaUrl' => get_field('cta_url') ?: '#booking-footer',

            // Every consultation page carries the booking card, so it keeps its default title.
            // On the va-roles skin an empty title means the page has no hero card at all, which
            // is how /1monthonus/ and /hire-va/ render — so no fallback there either.
            'bookingTitle' => $isVaRoles
                ? (get_field('booking_title') ?: '')
                : (get_field('booking_title') ?: 'Book a Free Consultation'),
            'formButtonText' => get_field('form_button_text') ?: 'Find me an Assistant',
            'isolatedSteps' => self::resolveIsolatedSteps(get_field('isolated_steps')),
        ];
    }

    /**
     * Which skin to render.
     *
     * Anything unset or unrecognised is the consultation skin, so the six consultation pages —
     * none of which sets the field — render exactly as they did before the skin existed.
     */
    public static function resolveVariant(mixed $value): string
    {
        return $value === 'va-roles' ? 'va-roles' : 'consultation';
    }

    /**
     * The reassurance tick list.
     *
     * An empty repeater keeps production's standard four items; /hire-real-estate-
     * virtual-assistants-flp/ differs only in promising a 48-hour match. The va-roles skin
     * passes $skipDefaults, because its pages always supply their own ticks and the
     * consultation copy would be wrong there.
     *
     * @return array<int, string>
     */
    public static function resolveChecklist(mixed $rows, bool $skipDefaults = false): array
    {
        $items = is_array($rows)
            ? array_values(array_filter(array_map(
                static fn ($row) => trim((string) ($row['item'] ?? '')),
                $rows,
            )))
            : [];

        if ($items !== [] || $skipDefaults) {
            return $items;
        }

        return [
            'No contracts or recurring fees',
            'Get matched within 72 hours',
            'Fluent English + U.S. time zones',
            '12-month replacement guarantee',
        ];
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
            ->addSelect('variant', [
                'label' => 'Skin',
                'instructions' => 'Consultation is the black-to-magenta band with the glass booking '
                    .'card. VA roles is the older violet band with the gold-gradient headline line, '
                    .'a two-column tick list and a solid white card.',
                'choices' => [
                    'consultation' => 'Consultation — #060218 band, glass card',
                    'va-roles' => 'VA roles — violet band, white card',
                ],
                'default_value' => 'consultation',
                'return_format' => 'value',
            ])
            ->addTextarea('headline', [
                'label' => 'Headline (HTML allowed)',
                'default_value' => 'Latin American<br>Virtual Assistants<br>$6-$10 Per Hour',
                'rows' => 3,
            ])
            ->addTextarea('headline_gradient', [
                'label' => 'Headline Gold-Gradient Line (VA roles skin, HTML allowed)',
                'instructions' => 'Rendered under the headline as a gold gradient span. Ignored by '
                    .'the consultation skin.',
                'rows' => 2,
            ])
            ->addSelect('headline_size', [
                'label' => 'Headline Scale (VA roles skin)',
                'instructions' => '80/88 for a three-line headline, 64/70.4 for a four-line one.',
                'choices' => ['64' => '64 / 70.4', '80' => '80 / 88'],
                'default_value' => '64',
                'return_format' => 'value',
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
            ->addText('background_image_class', [
                'label' => 'Banner Background Extra Classes (VA roles skin)',
                'instructions' => 'Full Tailwind literals only — e.g. "hidden min-[1280px]:block" to '
                    .'hide the art below 1280px. A class assembled by concatenation is never '
                    .'emitted, because Tailwind scans source text.',
            ])
            ->addSelect('split_at', [
                'label' => 'Copy/Card Split Breakpoint (VA roles skin)',
                'instructions' => 'Width at which the centred single column becomes the left-aligned '
                    .'copy/card row. A page with a hero booking card needs xl to keep the card '
                    .'on-screen at 1440.',
                'choices' => ['xl' => 'xl — 1280px', '2xl' => '2xl — 1536px'],
                'default_value' => 'xl',
                'return_format' => 'value',
            ])
            ->addText('cta_text', [
                'label' => 'Hero CTA Button Text (VA roles skin)',
                'instructions' => 'Leave empty for no CTA button.',
            ])
            ->addText('cta_url', [
                'label' => 'Hero CTA / Card Target URL',
                'default_value' => '#booking-footer',
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
