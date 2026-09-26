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
    public $description = 'About Remote Leverage narrative section with eyebrow badge, large statement lead, and 2-column detail copy (hidden on mobile, visible from tablet/md up). The `block_type` field switches the title prefix/title/lead statement/columns between the legacy ACF text fields and InnerBlocks (edit in place).';

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

    /**
     * The supported block features.
     *
     * @var array
     */
    public $supports = [
        'align' => ['full'],
        'mode' => false,
        'jsx' => true,
    ];

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
            'blockType' => get_field('block_type') ?: 'acf',
            'titlePrefix' => BlockDefaults::cleanText(get_field('title_prefix') ?: 'About'),
            'title' => BlockDefaults::cleanText(get_field('title') ?: 'Remote Leverage'),
            'badgeText' => BlockDefaults::cleanText(get_field('badge_text') ?: 'Remote Leverage'),
            'leadStatement' => BlockDefaults::cleanText(get_field('lead_statement') ?: 'Remote Leverage is a U.S.-based company helping businesses build exceptional global teams. Our own team consists of 120+ team members spanning Latin America, Europe, and Asia, representing over 25 countries. Diversity is the foundation of how we work.'),
            'colLeft' => BlockDefaults::cleanText(get_field('col_left') ?: 'Remote Leverage helps businesses grow. We start by finding the right people: our recruiting team sources, vets, and matches top 1% remote talent to roles that fit each client\'s specific needs.'),
            'colRight' => BlockDefaults::cleanText(get_field('col_right') ?: "But our work isn't just about filling roles. We believe great talent exists everywhere, and that the right opportunity can change a career. Every placement we make is an investment in two outcomes: a business that grows with confidence, and a professional who builds a meaningful, long-term career – wherever they are in the world.\n\n<strong>That's the leverage we're after.</strong>"),
            // Already a real media-library upload in production (2026/09), not theme-bundled
            // page art — resolved via homeImg() like AboutHeroBlock's own Group-207.png, which
            // checks the same `uploads/2026/09` directory before falling back.
            'badgeSwirlIcon' => BlockDefaults::homeImg('Official-Logo-Horizontal-No-Space-1.png'),
            'contentTemplate' => wp_json_encode(self::contentTemplate()),
            'contentAllowedBlocks' => wp_json_encode(['core/paragraph', 'core/heading', 'core/columns', 'core/column']),
        ];
    }

    /**
     * Default InnerBlocks content for the title-prefix/title/lead-statement/columns region:
     * seeds a freshly inserted block and is JSON-encoded onto the
     * `<InnerBlocks template="..." />` attribute in the Blade view (same pattern as
     * AboutHeroBlock::ctaTemplate()). `supports.jsx` (above) is what makes ACF hydrate that
     * literal HTML tag into a real, template-locked InnerBlocks area instead of leaving it as
     * plain text — the attribute value itself must be `wp_json_encode()`'d PHP, not JSX/JS
     * object syntax.
     *
     * The two-column prose is seeded with the same copy as `col_left`/`col_right`'s own
     * `default_value` (the live production text) rather than placeholder copy, since switching
     * an already-published instance to `inner_blocks` mode stops reading those ACF fields
     * entirely — this is what a freshly-inserted or newly-switched block starts with.
     *
     * `core/columns` carries `hidden md:flex!` so the detail columns are hidden on mobile only
     * and visible from tablet (`md`/768px) up (matching the `acf` branch's own `hidden md:grid`
     * on the Blade view) — the trailing-bang forces the utility past `.wp-block-columns`'s own
     * `display:flex` global style at the same class-selector specificity, the same cascade trap
     * as the `.wp-block-heading` case in the acf-hero-migration skill (§7): without it, which one
     * wins depends on source order, not which "looks" more specific.
     */
    public static function contentTemplate(): array
    {
        return [
            ['core/paragraph', [
                'className' => 'font-display text-2xl sm:text-3xl font-bold text-black tracking-tight mb-1',
                'content' => 'About',
            ]],
            ['core/heading', [
                'level' => 2,
                'className' => 'font-display text-3xl sm:text-4xl lg:text-[44px] font-bold text-black tracking-[-0.03em] leading-tight',
                'content' => 'Remote Leverage',
            ]],
            ['core/paragraph', [
                'className' => 'font-display text-2xl sm:text-3xl lg:text-[32px] font-bold text-black tracking-[-0.02em] leading-snug mt-8 sm:mt-10',
                'content' => 'Remote Leverage is a U.S.-based company helping businesses build exceptional global teams. Our own team consists of 120+ team members spanning Latin America, Europe, and Asia, representing over 25 countries. Diversity is the foundation of how we work.',
            ]],
            ['core/columns', [
                'className' => 'hidden md:flex! md:gap-8! lg:gap-14! text-base sm:text-lg text-black/75 leading-relaxed mt-8',
            ], [
                ['core/column', [], [
                    ['core/paragraph', [
                        'content' => 'Remote Leverage helps businesses grow. We start by finding the right people: our recruiting team sources, vets, and matches top 1% remote talent to roles that fit each client\'s specific needs.',
                    ]],
                ]],
                ['core/column', [], [
                    ['core/paragraph', [
                        'content' => "But our work isn't just about filling roles. We believe great talent exists everywhere, and that the right opportunity can change a career. Every placement we make is an investment in two outcomes: a business that grows with confidence, and a professional who builds a meaningful, long-term career – wherever they are in the world.",
                    ]],
                    ['core/paragraph', [
                        'content' => "<strong>That's the leverage we're after.</strong>",
                    ]],
                ]],
            ]],
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('about_narrative_block');

        $fields
            ->addSelect('block_type', [
                'label' => 'Content editing mode',
                'instructions' => 'ACF Fields: use the text fields below for the title prefix, title, lead statement and the two detail columns. Inner Blocks: edit all of them in place in the canvas as native blocks instead (the columns are hidden on mobile either way, visible from tablet up). Badge text stays a plain ACF field in both modes.',
                'choices' => [
                    'acf' => 'ACF Fields (default)',
                    'inner_blocks' => 'Inner Blocks (edit in place)',
                ],
                'default_value' => 'acf',
            ])
            ->addText('title_prefix', [
                'label' => 'Title Prefix',
                'default_value' => 'About',
            ])
            ->conditional('block_type', '==', 'acf')
            ->addText('title', [
                'label' => 'Title',
                'default_value' => 'Remote Leverage',
            ])
            ->conditional('block_type', '==', 'acf')
            ->addTextarea('lead_statement', [
                'label' => 'Large Lead Statement',
                'rows' => 3,
                'default_value' => 'Remote Leverage is a U.S.-based company helping businesses build exceptional global teams. Our own team consists of 120+ team members spanning Latin America, Europe, and Asia, representing over 25 countries. Diversity is the foundation of how we work.',
            ])
            ->conditional('block_type', '==', 'acf')
            ->addText('badge_text', [
                'label' => 'Badge Text',
                'default_value' => 'Remote Leverage',
            ])
            ->addTextarea('col_left', [
                'label' => 'Left Column Content',
                'rows' => 4,
                'default_value' => 'Remote Leverage helps businesses grow. We start by finding the right people: our recruiting team sources, vets, and matches top 1% remote talent to roles that fit each client\'s specific needs.',
            ])
            ->conditional('block_type', '==', 'acf')
            ->addTextarea('col_right', [
                'label' => 'Right Column Content (HTML Allowed)',
                'rows' => 5,
                'default_value' => "But our work isn't just about filling roles. We believe great talent exists everywhere, and that the right opportunity can change a career. Every placement we make is an investment in two outcomes: a business that grows with confidence, and a professional who builds a meaningful, long-term career – wherever they are in the world.\n\n<strong>That's the leverage we're after.</strong>",
            ])
            ->conditional('block_type', '==', 'acf');

        return $fields->build();
    }
}
