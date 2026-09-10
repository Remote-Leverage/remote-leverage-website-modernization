<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class AboutNarrativeBlock extends Block
{
    /**
     * The block name.
     *
     * @var string
     */
    public $name = 'About Narrative';

    /**
     * The block slug.
     *
     * @var string
     */
    public $slug = 'about-narrative';

    /**
     * The block description.
     *
     * @var string
     */
    public $description = 'About Remote Leverage narrative section with eyebrow badge, large statement lead, and 2-column detail copy.';

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
    public $icon = 'id-alt';

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

    public $view = 'blocks.about-narrative';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'title_prefix' => 'About',
                'title' => 'Remote Leverage',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'titlePrefix' => BlockDefaults::cleanText(get_field('title_prefix') ?: 'About'),
            'title' => BlockDefaults::cleanText(get_field('title') ?: 'Remote Leverage'),
            'badgeText' => BlockDefaults::cleanText(get_field('badge_text') ?: 'Remote Leverage'),
            'leadStatement' => BlockDefaults::cleanText(get_field('lead_statement') ?: 'Remote Leverage is a U.S.-based company helping businesses build exceptional global teams. Our own team consists of 120+ team members spanning Latin America, Europe, and Asia, representing over 25 countries. Diversity is the foundation of how we work.'),
            'colLeft' => BlockDefaults::cleanText(get_field('col_left') ?: 'Remote Leverage helps businesses grow. We start by finding the right people: our recruiting team sources, vets, and matches top 1% remote talent to roles that fit each client\'s specific needs.'),
            'colRight' => BlockDefaults::cleanText(get_field('col_right') ?: "But our work isn't just about filling roles. We believe great talent exists everywhere, and that the right opportunity can change a career. Every placement we make is an investment in two outcomes: a business that grows with confidence, and a professional who builds a meaningful, long-term career – wherever they are in the world.\n\n<strong>That's the leverage we're after.</strong>"),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('about_narrative_block');

        $fields
            ->addText('title_prefix', [
                'label' => 'Title Prefix',
                'default_value' => 'About',
            ])
            ->addText('title', [
                'label' => 'Title',
                'default_value' => 'Remote Leverage',
            ])
            ->addText('badge_text', [
                'label' => 'Badge Text',
                'default_value' => 'Remote Leverage',
            ])
            ->addTextarea('lead_statement', [
                'label' => 'Large Lead Statement',
                'rows' => 3,
                'default_value' => 'Remote Leverage is a U.S.-based company helping businesses build exceptional global teams. Our own team consists of 120+ team members spanning Latin America, Europe, and Asia, representing over 25 countries. Diversity is the foundation of how we work.',
            ])
            ->addTextarea('col_left', [
                'label' => 'Left Column Content',
                'rows' => 4,
                'default_value' => 'Remote Leverage helps businesses grow. We start by finding the right people: our recruiting team sources, vets, and matches top 1% remote talent to roles that fit each client\'s specific needs.',
            ])
            ->addTextarea('col_right', [
                'label' => 'Right Column Content (HTML Allowed)',
                'rows' => 5,
                'default_value' => "But our work isn't just about filling roles. We believe great talent exists everywhere, and that the right opportunity can change a career. Every placement we make is an investment in two outcomes: a business that grows with confidence, and a professional who builds a meaningful, long-term career – wherever they are in the world.\n\n<strong>That's the leverage we're after.</strong>",
            ]);

        return $fields->build();
    }
}
