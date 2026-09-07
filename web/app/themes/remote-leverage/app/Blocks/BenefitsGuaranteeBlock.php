<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class BenefitsGuaranteeBlock extends Block
{
    public $name = 'Benefits & Guarantee';

    public $slug = 'benefits-guarantee';

    public $description = 'Value proposition cards paired with the 6-month free replacement guarantee seal.';

    public $category = 'remote-leverage';

    public $icon = 'shield';

    public $keywords = ['benefits', 'guarantee', 'replacement', 'risk free', 'why us'];

    public $view = 'blocks.benefits-guarantee';

    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'Why Top Companies Choose Remote Leverage',
            'description' => get_field('description') ?: 'We eliminate the friction of offshore staffing by vetting for English fluency, executive professionalism, and cultural alignment.',
            'guaranteeTitle' => get_field('guarantee_title') ?: '6-Month Free Replacement Guarantee',
            'guaranteeDesc' => get_field('guarantee_desc') ?: 'If for any reason your specialist is not the right fit within the first 6 months, we match and onboard a replacement at zero additional placement cost.',
            'ctaText' => get_field('cta_text') ?: 'Book a Strategy Consultation',
            'ctaUrl' => get_field('cta_url') ?: '#booking-wizard',
            'benefits' => $this->benefits(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('benefits_guarantee_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Why Top Companies Choose Remote Leverage',
            ])
            ->addTextarea('description', [
                'label' => 'Description',
                'default_value' => 'We eliminate the friction of offshore staffing by vetting for English fluency, executive professionalism, and cultural alignment.',
                'rows' => 2,
            ])
            ->addText('guarantee_title', [
                'label' => 'Guarantee Badge Title',
                'default_value' => '6-Month Free Replacement Guarantee',
            ])
            ->addTextarea('guarantee_desc', [
                'label' => 'Guarantee Terms',
                'default_value' => 'If for any reason your specialist is not the right fit within the first 6 months, we match and onboard a replacement at zero additional placement cost.',
                'rows' => 3,
            ])
            ->addText('cta_text', [
                'label' => 'CTA Button Text',
                'default_value' => 'Book a Strategy Consultation',
            ])
            ->addUrl('cta_url', [
                'label' => 'CTA URL',
                'default_value' => '#booking-wizard',
            ])
            ->addRepeater('benefits', [
                'label' => 'Core Benefits',
                'layout' => 'block',
                'button_label' => 'Add Benefit',
            ])
            ->addText('title', [
                'label' => 'Benefit Title',
                'default_value' => 'Fluent English & Low Accent',
            ])
            ->addTextarea('description', [
                'label' => 'Benefit Description',
                'default_value' => 'Every candidate passes comprehensive C1/C2 verbal and written assessments so client calls feel effortless.',
                'rows' => 2,
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function benefits(): array
    {
        $items = get_field('benefits');

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        // Fallback matching original Elementor BenefitsSectionWidget
        return [
            [
                'title' => 'Fluent English & Cultural Nuance',
                'description' => 'Tested for C1/C2 English proficiency with little to no accent, capable of joining high-stakes customer-facing calls.',
            ],
            [
                'title' => '100% US Timezone Aligned',
                'description' => 'Located across Colombia, Mexico, and Argentina—working your exact EST, CST, or PST business hours.',
            ],
            [
                'title' => 'Pre-Trained on Modern Tech Stacks',
                'description' => 'Ready to deploy immediately in Slack, Notion, Asana, Google Workspace, HubSpot, and GoHighLevel.',
            ],
            [
                'title' => 'Zero Payroll or Health Benefit Overhead',
                'description' => 'We handle all nearshore contractor compliance, W-8BEN documentation, and international payroll routing.',
            ],
        ];
    }
}
