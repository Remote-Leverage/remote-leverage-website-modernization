<?php

namespace App\Blocks;

use App\Support\BlockDefaults;
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
            // The 2026 homepage draws a violet gradient and the progressive white-card form
            // here; every other page the world map and the dark glass one.
            'skin' => ($this->block->data['skin'] ?? null)
                ?: ((function_exists('get_field') ? get_field('skin') : null) ?: 'glass'),
            'background' => ($this->block->data['background'] ?? null)
                ?: ((function_exists('get_field') ? get_field('background') : null) ?: 'map'),
            'formTitle' => ($this->block->data['form_title'] ?? null)
                ?: ((function_exists('get_field') ? get_field('form_title') : null) ?: 'Your Contact Information'),
            'formButtonText' => ($this->block->data['form_button_text'] ?? null)
                ?: ((function_exists('get_field') ? get_field('form_button_text') : null) ?: 'Book a Consultation'),
            'mapImage' => BlockDefaults::resolveImageUrl(
                (function_exists('get_field') ? get_field('map_image') : null) ?: 506
            ),
            'description' => (function_exists('get_field') ? get_field('description') : null)
                ?: 'During this meeting we will go over the role you’re planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.',
            // Off unless a page asks, so every page already shipping this block is untouched.
            // The 2026 role comps put a Google rating pill and the six hero checkpoints under
            // the description; the homepage comp has neither.
            'showTrust' => (bool) (($this->block->data['show_trust'] ?? null)
                ?: ((function_exists('get_field') ? get_field('show_trust') : null) ?: false)),
            'ratingLogo' => BlockDefaults::homeImg('google-logo.png'),
            'ratingScore' => BlockDefaults::cleanText(
                (function_exists('get_field') ? get_field('rating_score') : null)
            ) ?: '4.8',
            'checklist' => $this->checklist(),
        ];
    }

    /**
     * The trust checklist, empty unless a page supplies one.
     *
     * @return array<int, string>
     */
    public function checklist(): array
    {
        $rows = function_exists('get_field') ? get_field('checklist') : null;

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($row) => BlockDefaults::cleanText($row['item'] ?? ''),
            $rows
        )));
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
            ])
            ->addTrueFalse('show_trust', [
                'label' => 'Show the rating pill and checklist',
                'instructions' => 'Off is production and the 2026 homepage. On is the role pages, whose comps '
                    .'put a Google rating pill and the six hire checkpoints under the description.',
                'default_value' => 0,
                'ui' => 1,
            ])
            ->addText('rating_score', [
                'label' => 'Google Rating Score',
                'default_value' => '4.8',
            ])
            ->addRepeater('checklist', [
                'label' => 'Trust Checklist',
                'instructions' => 'Only rendered when the toggle above is on. One column, in the order given.',
                'layout' => 'table',
                'button_label' => 'Add Item',
            ])
            ->addText('item', ['label' => 'Item'])
            ->endRepeater();

        return $fields->build();
    }
}
