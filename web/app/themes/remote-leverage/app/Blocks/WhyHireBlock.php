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
            ->addTextarea('proof_title', [
                'label' => 'Proof Card Title',
                'default_value' => "We've helped more than 2,000 businesses hire top talent across LatAm, the Caribbean and the EU.",
                'rows' => 3,
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
