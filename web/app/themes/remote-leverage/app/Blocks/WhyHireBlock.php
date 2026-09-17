<?php

namespace App\Blocks;

use App\Support\BlockDefaults;
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

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Why Fast-Growing Companies Choose Us',
                'is_preview' => true,
            ],
        ],
    ];

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
            'headingAlign' => get_field('heading_align') ?: 'left',
            'proofChrome' => get_field('proof_chrome') ?: 'full',
            'proofBackground' => get_field('proof_background') ?: 'midnight',
            'layout' => get_field('layout') ?: 'split',
            'iconSet' => get_field('icon_set') ?: 'classic',
            'proofImage' => get_field('proof_image') ?: '',
            'proofBadgeCount' => BlockDefaults::cleanText(get_field('proof_badge_count')),
            'proofBadgeLabel' => BlockDefaults::cleanText(get_field('proof_badge_label')),
            'proofCtaText' => BlockDefaults::cleanText(get_field('proof_cta_text')),
            'proofCtaUrl' => get_field('proof_cta_url') ?: '#booking-footer',
            'cards' => $this->cards(),
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
            ->addSelect('layout', [
                'label' => 'Arrangement',
                'instructions' => 'Split is production and the 2026 homepage: the proof card in a 5/7 column '
                    .'beside a vertical stack of four cards. Banner is the role pages '
                    .'(/admin-virtual-assistants/ and its siblings): the proof card runs full width with the '
                    .'globe in its own half, and the four cards sit under it in a 2x2 grid.',
                'choices' => ['split' => 'Proof card beside the cards (default)', 'banner' => 'Proof banner over a 2x2 grid'],
                'default_value' => 'split',
                'return_format' => 'value',
            ])
            ->addSelect('heading_align', [
                'label' => 'Heading Alignment',
                'choices' => ['left' => 'Left (default)', 'center' => 'Centred'],
                'default_value' => 'left',
                'return_format' => 'value',
            ])
            ->addSelect('proof_chrome', [
                'label' => 'Proof Card Detail',
                'instructions' => 'Full is production: a "5.0 Star Rating" pill above the quote and a closing '
                    .'paragraph below it. Bare is the 2026 homepage — gold stars and the quote, nothing else.',
                'choices' => ['full' => 'Rating pill and closing paragraph (default)', 'bare' => 'Stars and quote only'],
                'default_value' => 'full',
                'return_format' => 'value',
            ])
            ->addSelect('proof_background', [
                'label' => 'Proof Card Ground',
                'instructions' => 'Midnight is the flat #250D4A production paints. Violet is the 2026 homepage: '
                    .'#6410A6 with a #7616B6 radial behind the globe. Violet-deep is the role pages: a vertical '
                    .'#5920AE to #290F51 ramp, sampled off the comp.',
                'choices' => [
                    'midnight' => 'Flat midnight #250D4A (default)',
                    'violet' => 'Violet gradient (2026 homepage)',
                    'violet-deep' => 'Violet to midnight ramp (role pages)',
                ],
                'default_value' => 'midnight',
                'return_format' => 'value',
            ])
            ->addTextarea('proof_title', [
                'label' => 'Proof Card Title',
                'default_value' => "We've helped more than 2,000 businesses hire top talent across LatAm, the Caribbean and the EU.",
                'rows' => 3,
            ])
            ->addImage('proof_image', [
                'label' => 'Proof Card Globe',
                'instructions' => 'Leave empty for the shared globe. The role pages supply their own export, '
                    .'which carries the orbit arcs and the candidate portraits already composited in.',
                'return_format' => 'url',
            ])
            ->addText('proof_badge_count', [
                'label' => 'Proof Badge Figure',
                'instructions' => 'Banner arrangement only. Sits beside the stacked portraits, e.g. "2.5K+". '
                    .'Leave empty to fall back to the five gold stars the split arrangement shows.',
            ])
            ->addText('proof_badge_label', [
                'label' => 'Proof Badge Label',
                'instructions' => 'The second line of the badge, e.g. "pre-vetted candidates".',
            ])
            ->addText('proof_cta_text', [
                'label' => 'Proof Card CTA Text',
                'instructions' => 'Banner arrangement only. Leave empty for no button.',
            ])
            ->addText('proof_cta_url', [
                'label' => 'Proof Card CTA Target URL',
                'default_value' => '#booking-footer',
            ])
            ->addSelect('icon_set', [
                'label' => 'Card Icons',
                'instructions' => 'Classic is the set every page shipping this block already renders: dollar '
                    .'sign, speech bubble, check circle, document. Descriptive is the role comps\' set, which '
                    .'depicts each card rather than decorating it: barred dollar, EN speech bubble, percent, '
                    .'barred document.',
                'choices' => ['classic' => 'Generic icons (default)', 'descriptive' => 'Icons matching each card'],
                'default_value' => 'classic',
                'return_format' => 'value',
            ])
            ->addRepeater('cards', [
                'label' => 'Feature Cards (Leave empty for default 4 cards)',
                'layout' => 'block',
                'button_label' => 'Add Card',
            ])
            ->addText('title', [
                'label' => 'Card Title',
            ])
            ->addTextarea('desc', [
                'label' => 'Card Description',
                'rows' => 2,
            ])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * The default 4 feature cards, used when no per-instance override is set.
     */
    public function defaultCards(): array
    {
        return [
            [
                'title' => 'No Recurring Fees - Hire Direct',
                'desc' => 'Pay once when you hire. No monthly markups, no hidden costs, no contracts keeping you tied down.',
            ],
            [
                'title' => 'Fluent English',
                'desc' => 'Every candidate is vetted for professional English proficiency, clear communication, and seamless timezone overlap.',
            ],
            [
                'title' => '30% Discount on Future Hires',
                'desc' => 'Scaling your team? Enjoy an automatic 30% discount on placement fees for every subsequent hire.',
            ],
            [
                'title' => 'No Contracts',
                'desc' => 'You hold all the leverage. You hire directly onto your own payroll or contractor setup with zero lock-ins.',
            ],
        ];
    }

    /**
     * Resolve the feature cards, falling back to the site-wide defaults.
     */
    public function cards(): array
    {
        $custom = get_field('cards');
        $cards = (! empty($custom) && is_array($custom))
            ? $custom
            : $this->defaultCards();

        return array_map(function ($card) {
            $card['title'] = BlockDefaults::cleanText($card['title'] ?? '');
            $card['desc'] = BlockDefaults::cleanText($card['desc'] ?? '');

            return $card;
        }, $cards);
    }
}
