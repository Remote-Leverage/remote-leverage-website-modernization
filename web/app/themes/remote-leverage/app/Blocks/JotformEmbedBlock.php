<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Reproduces production's `/payment/`, `/vaonboardingform/` and `/contractoragreement/` pages,
 * which are each a single Elementor HTML widget holding one JotForm embed and nothing else.
 * `/contractoragreement/` is a JotForm *Sign* document rather than a classic form, which is a
 * different host and URL shape — see the `product` field.
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

    public $description = 'Embeds a hosted JotForm — a classic form or a Sign e-signature document — by form ID, on an optional coloured band.';

    public $category = 'remote-leverage';

    public $icon = 'feedback';

    public $keywords = ['jotform', 'form', 'embed', 'payment', 'onboarding', 'sign', 'esign', 'agreement'];

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
        // JotForm IDs are numeric; anything else would inject an arbitrary script src.
        $formId = preg_replace('/\D/', '', (string) (get_field('form_id') ?: ''));

        // JotForm Sign documents are NOT served from form.jotform.com — that host answers
        // "Form is missing" for a signable document. They live at
        // www.jotform.com/sign/<id>/invite/<token>, and ?signEmbed=1 is what strips JotForm's
        // own page chrome so the document sits flush inside the iframe. The invite token is
        // the one JotForm mints for the public embed and is part of production's markup on
        // /contractoragreement/, so it is content, not a secret.
        $product = get_field('product') ?: 'form';
        $invite = preg_replace('/[^A-Za-z0-9]/', '', (string) (get_field('sign_invite') ?: ''));
        $isSign = $product === 'sign' && $invite !== '';

        return [
            'formId' => $formId,
            'embedSrc' => $isSign
                ? 'https://www.jotform.com/sign/'.$formId.'/invite/'.$invite.'?signEmbed=1'
                : 'https://form.jotform.com/'.$formId,
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
            ->addSelect('product', [
                'label' => 'JotForm product',
                'instructions' => 'A classic form is served from form.jotform.com. A Sign document is not — it needs the invite token below.',
                'choices' => [
                    'form' => 'Form (default)',
                    'sign' => 'Sign document (e-signature)',
                ],
                'default_value' => 'form',
            ])
            ->addText('sign_invite', [
                'label' => 'Sign invite token',
                'instructions' => 'Sign documents only: the token after /invite/ in the public embed URL.',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_jotform_embed_block_product',
                            'operator' => '==',
                            'value' => 'sign',
                        ],
                    ],
                ],
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
