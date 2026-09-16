<?php

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use StoutLogic\AcfBuilder\FieldsBuilder;

class HomeHeroBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'Home Hero';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'home-hero';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'The 2026 homepage hero: Google rating, centred headline, checklist card, pink CTA and floating talent cards.';

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
    public $icon = 'align-center';

    public $keywords = ['hero', 'homepage', 'headline', 'talent', 'rating'];

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Latin American Virtual Assistants',
                'is_preview' => true,
            ],
        ],
    ];

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
            'ratingLogo' => BlockDefaults::homeImg('google-logo.png'),
            'ratingScore' => BlockDefaults::cleanText($this->field('rating_score')) ?: '4.8',
            // Explicitly false-aware: an ACF true/false stores 0, and `0 !== false` is true,
            // so a plain !== check would have kept showing the row when it was switched off.
            'showRating' => (bool) ($this->field('show_rating') ?? true),
            'headline' => BlockDefaults::cleanText($this->field('headline')) ?: "Latin American\nVirtual Assistants",
            'headlineAccent' => BlockDefaults::cleanText($this->field('headline_accent')) ?: '$6-$10 Per Hour',
            'subtitle' => $this->field('subtitle') ?: 'Recruiting agency helping businesses hire English speaking Virtual Assistants from Latin America for <strong>70% less than U.S. Employees.</strong>',
            'checklist' => $this->checklist(),
            'ctaText' => BlockDefaults::cleanText($this->field('cta_text')) ?: 'BOOK A CONSULTATION',
            'ctaUrl' => $this->field('cta_url') ?: '#booking-footer',
            'cards' => $this->cards(),
        ];
    }

    /**
     * Read an ACF field, tolerating the field functions being absent (tests, CLI).
     */
    protected function field(string $name)
    {
        return function_exists('get_field') ? get_field($name) : null;
    }

    /**
     * The six hero checklist items.
     *
     * Order matters and is not cosmetic: the desktop card is a two-column row-flow grid, so
     * items 1/3/5 land in the left column and 2/4/6 in the right, which is exactly the single
     * column order the mobile design shows. One list drives both.
     *
     * @return array<int, string>
     */
    public function checklist(): array
    {
        $custom = $this->field('checklist');

        if (is_array($custom) && $custom !== []) {
            $items = array_values(array_filter(array_map(
                fn ($row) => BlockDefaults::cleanText($row['item'] ?? ''),
                $custom
            )));

            if ($items !== []) {
                return $items;
            }
        }

        return BlockDefaults::homeHeroChecklist();
    }

    /**
     * The four floating talent cards, two per side.
     *
     * @return array<int, array<string, string>>
     */
    public function cards(): array
    {
        $custom = $this->field('cards');

        if (is_array($custom) && $custom !== []) {
            return array_map(fn ($card) => [
                'name' => BlockDefaults::cleanText($card['name'] ?? ''),
                'role' => BlockDefaults::cleanText($card['role'] ?? ''),
                'rate' => BlockDefaults::cleanText($card['rate'] ?? ''),
                'flag' => $card['flag'] ?? '',
                'photo' => $card['photo'] ?? '',
                'side' => ($card['side'] ?? 'left') === 'right' ? 'right' : 'left',
            ], $custom);
        }

        return BlockDefaults::homeHeroCards();
    }

    /**
     * The block field group.
     *
     * @return array
     */
    public function fields()
    {
        $fields = new FieldsBuilder('home_hero');

        $fields
            ->addTrueFalse('show_rating', [
                'label' => 'Show the Google rating row',
                'instructions' => 'Desktop only — the mobile design omits it either way. Off since 2026-09-16.',
                'default_value' => 0,
                'ui' => 1,
            ])
            ->addText('rating_score', [
                'label' => 'Google Rating Score',
                'default_value' => '4.8',
            ])
            ->addTextarea('headline', [
                'label' => 'Headline',
                'instructions' => 'Line breaks are kept. The design sets this on two lines at every width.',
                'rows' => 2,
                'new_lines' => '',
                'default_value' => "Latin American\nVirtual Assistants",
            ])
            ->addText('headline_accent', [
                'label' => 'Headline Accent Line',
                'instructions' => 'Rendered on its own third line, in brand purple.',
                'default_value' => '$6-$10 Per Hour',
            ])
            ->addTextarea('subtitle', [
                'label' => 'Subtitle (supports basic HTML)',
                'rows' => 3,
                'default_value' => 'Recruiting agency helping businesses hire English speaking Virtual Assistants from Latin America for <strong>70% less than U.S. Employees.</strong>',
            ])
            ->addRepeater('checklist', [
                'label' => 'Checklist Items (leave empty for the preset six)',
                'instructions' => 'Reads down the left column then the right on desktop, and straight down on mobile.',
                'layout' => 'table',
                'button_label' => 'Add Item',
            ])
            ->addText('item', ['label' => 'Item'])
            ->endRepeater()
            ->addText('cta_text', [
                'label' => 'CTA Button Text',
                'default_value' => 'BOOK A CONSULTATION',
            ])
            ->addText('cta_url', [
                'label' => 'CTA Button Target URL',
                'default_value' => '#booking-footer',
            ])
            ->addRepeater('cards', [
                'label' => 'Floating Talent Cards (leave empty for the preset four)',
                'instructions' => 'Desktop only — the mobile design has no floating cards. Two per side, in back-to-front order.',
                'layout' => 'block',
                'button_label' => 'Add Card',
            ])
            ->addText('name', ['label' => 'Name'])
            ->addText('role', ['label' => 'Role'])
            ->addText('rate', ['label' => 'Hourly Rate Pill', 'instructions' => 'e.g. $6/hr. Leave empty to omit the pill.'])
            ->addImage('flag', ['label' => 'Flag', 'return_format' => 'url'])
            ->addImage('photo', ['label' => 'Cutout Portrait', 'return_format' => 'url', 'instructions' => 'Transparent PNG. Leave empty for a card with no portrait.'])
            ->addSelect('side', [
                'label' => 'Side',
                'choices' => ['left' => 'Left of the headline', 'right' => 'Right of the headline'],
                'default_value' => 'left',
                'return_format' => 'value',
            ])
            ->endRepeater();

        return $fields->build();
    }
}
