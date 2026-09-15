<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Reproduces production's `/payment/` and `/vaonboardingform/` pages, which are each a single
 * Elementor HTML widget holding one JotForm embed script and nothing else.
 *
 * No existing block embeds a third-party form: acf/booking renders the in-house Livewire
 * scheduler, acf/impact-report-hero posts a name/email capture to our own endpoint, and
 * acf/vacalendar-hero wraps the Calendly skin. JotForm hosts and stores these submissions,
 * so the form itself cannot be rebuilt in the design system without moving the data.
 */
class JotformEmbedBlock extends Block
{
    public $name = 'JotForm Embed';

    public $slug = 'jotform-embed';

    public $description = 'Embeds a hosted JotForm by form ID, on an optional coloured band.';

    public $category = 'remote-leverage';

    public $icon = 'feedback';

    public $keywords = ['jotform', 'form', 'embed', 'payment', 'onboarding'];

    public $view = 'blocks.jotform-embed';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'form_id' => '242638425990061',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            // JotForm IDs are numeric; anything else would inject an arbitrary script src.
            'formId' => preg_replace('/\D/', '', (string) (get_field('form_id') ?: '')),
            'title' => BlockDefaults::cleanText(get_field('title') ?: ''),
            'background' => get_field('background') ?: 'light',
            'minHeight' => (int) (get_field('min_height') ?: 640),
            'heading' => BlockDefaults::cleanText(get_field('heading') ?: ''),
            'intro' => BlockDefaults::cleanText(get_field('intro') ?: ''),
            'card' => get_field('card') === null ? true : (bool) get_field('card'),
            'maxWidth' => (int) (get_field('max_width') ?: 900),
            'isPreview' => (bool) get_field('is_preview'),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('jotform_embed_block');

        $fields
            ->addText('form_id', [
                'label' => 'JotForm Form ID',
                'instructions' => 'Numeric ID only — the last path segment of the JotForm URL.',
            ])
            ->addText('title', [
                'label' => 'Accessible title',
                'instructions' => 'Screen-reader label for the embedded form.',
                'default_value' => 'Form',
            ])
            ->addText('heading', [
                'label' => 'Page heading',
                'instructions' => 'Shown above the form. Leave blank to hide.',
            ])
            ->addTextarea('intro', [
                'label' => 'Intro line',
                'rows' => 2,
                'instructions' => 'Short line under the heading. Leave blank to hide.',
            ])
            ->addTrueFalse('card', [
                'label' => 'Wrap the form in a card',
                'instructions' => 'Puts the embed on a white rounded panel, matching the rest of the site.',
                'default_value' => 1,
                'ui' => 1,
            ])
            ->addNumber('max_width', [
                'label' => 'Form max width (px)',
                'default_value' => 900,
                'instructions' => 'Keeps a narrow form from floating in a full-width band.',
            ])
            ->addSelect('background', [
                'label' => 'Band background',
                'choices' => [
                    'transparent' => 'None',
                    'light' => 'Pale (default)',
                    'lavender' => 'Lavender tint',
                    'dark-violet' => 'Dark violet',
                ],
                'default_value' => 'light',
            ])
            ->addNumber('min_height', [
                'label' => 'Minimum height (px)',
                'default_value' => 640,
                'instructions' => 'Reserves vertical space so the page does not jump as the form loads.',
            ]);

        return $fields->build();
    }
}
