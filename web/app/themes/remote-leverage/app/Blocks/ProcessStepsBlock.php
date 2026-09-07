<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class ProcessStepsBlock extends Block
{
    public $name = 'Process & Hiring Steps';

    public $slug = 'process-steps';

    public $description = 'Numbered hiring timeline and onboarding steps with connecting SVG paths.';

    public $category = 'remote-leverage';

    public $icon = 'networking';

    public $keywords = ['process', 'steps', 'timeline', 'hiring', 'onboarding'];

    public $view = 'blocks.process-steps';

    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'How Remote Leverage Works',
            'subheadline' => get_field('subheadline') ?: 'From initial strategy session to a dedicated full-time specialist in your Slack within 7 days.',
            'steps' => $this->steps(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('process_steps_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'How Remote Leverage Works',
            ])
            ->addTextarea('subheadline', [
                'label' => 'Subheadline',
                'default_value' => 'From initial strategy session to a dedicated full-time specialist in your Slack within 7 days.',
                'rows' => 2,
            ])
            ->addRepeater('steps', [
                'label' => 'Steps Timeline',
                'layout' => 'block',
                'button_label' => 'Add Step',
            ])
            ->addText('step_number', [
                'label' => 'Step Number (e.g. 01)',
                'default_value' => '01',
            ])
            ->addText('title', [
                'label' => 'Step Title',
                'default_value' => 'Solve the Hiring Bottleneck',
            ])
            ->addTextarea('description', [
                'label' => 'Step Description',
                'default_value' => 'Tell us about your operational bottlenecks and required toolstack during a quick 30-minute discovery consultation.',
                'rows' => 3,
            ])
            ->addText('cta_label', [
                'label' => 'CTA Button Label (Optional)',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA Link URL (Optional)',
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function steps(): array
    {
        $items = get_field('steps');

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        // Realistic fallback matching original Elementor ProcessStepsWidget
        return [
            [
                'step_number' => '01',
                'title' => 'Discovery & Role Scoping',
                'description' => 'We define your required technical competencies, software stack, and weekly deliverables to calibrate the ideal candidate profile.',
                'cta_label' => 'Book Discovery Call',
                'cta_url' => '#booking-wizard',
            ],
            [
                'step_number' => '02',
                'title' => 'Vetting & Top 1% Matching',
                'description' => 'Our proprietary assessment tests English fluency, cognitive reasoning, and role-specific skills. You receive the top 2-3 matched candidates.',
                'cta_label' => null,
                'cta_url' => null,
            ],
            [
                'step_number' => '03',
                'title' => 'Live Interviews & Selection',
                'description' => 'Interview your top candidates directly. Choose the exact person you want on your team with zero upfront placement fees.',
                'cta_label' => null,
                'cta_url' => null,
            ],
            [
                'step_number' => '04',
                'title' => 'Seamless Onboarding & Guarantee',
                'description' => 'Your assistant joins your Slack, Notion, and email workspace backed by our 6-month free replacement guarantee.',
                'cta_label' => null,
                'cta_url' => null,
            ],
        ];
    }
}
