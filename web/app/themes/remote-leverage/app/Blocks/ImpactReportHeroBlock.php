<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ImpactReportHeroBlock extends Block
{
    public $name = 'Hero — Report with Gated Download Form';

    public $slug = 'impact-report-hero';

    public $description = 'Lavender hero for a downloadable report. Title and intro left, purple gradient card with name/email capture right, full-width publication mockup beneath.';

    public $category = 'remote-leverage';

    public $icon = 'media-document';

    public $keywords = ['hero', 'report', 'download', 'lead form', 'gated', 'impact'];

    public $view = 'blocks.impact-report-hero';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => ['is_preview' => true],
        ],
    ];

    public $supports = [
        'align' => ['full'],
    ];

    public function with(): array
    {
        $field = fn (string $key) => function_exists('get_field') ? get_field($key) : null;

        return [
            'headline' => $field('headline') ?: 'The Remote Leverage 2026 Impact Report',
            'intro' => $field('intro') ?: 'A data-driven look at how connecting US businesses with skilled professionals across Latin America and the Caribbean creates real, measurable impact on both sides – drawn from over 2,000 placements since 2024.',
            'formTitle' => $field('form_title') ?: 'Download the free report',
            'submitText' => $field('submit_text') ?: 'Download Now',
            'formAction' => $field('form_action') ?: '',
            'mockup' => BlockDefaults::resolveImageUrl($field('mockup_image') ?: '') ?: BlockDefaults::pageImg('impact-report-2026', 'book-2.webp'),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('impact_report_hero_block');

        $fields
            ->addTextarea('headline', ['label' => 'Headline', 'rows' => 2])
            ->addTextarea('intro', ['label' => 'Intro Paragraph', 'rows' => 4])
            ->addText('form_title', ['label' => 'Form Card Title', 'default_value' => 'Download the free report'])
            ->addText('submit_text', ['label' => 'Submit Button Text', 'default_value' => 'Download Now'])
            ->addText('form_action', [
                'label' => 'Form Action URL',
                'instructions' => 'Where the capture posts. Leave empty until the Lead domain endpoint exists.',
            ])
            ->addImage('mockup_image', ['label' => 'Publication Mockup', 'return_format' => 'url']);

        return $fields->build();
    }
}
