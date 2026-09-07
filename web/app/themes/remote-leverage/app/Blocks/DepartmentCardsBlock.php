<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class DepartmentCardsBlock extends Block
{
    public $name = 'Department & Role Cards';

    public $slug = 'department-cards';

    public $description = 'Glassmorphism talent cards featuring vetted LatAm roles, verified badges, and worked-at company logos.';

    public $category = 'remote-leverage';

    public $icon = 'id-alt';

    public $keywords = ['department', 'contractor', 'roles', 'staffing', 'talent'];

    public $view = 'blocks.department-cards';

    public function with(): array
    {
        return [
            'headline' => get_field('headline') ?: 'Specialized Remote Roles Ready to Deploy',
            'subheadline' => get_field('subheadline') ?: 'Top 1% pre-vetted specialists working in your exact timezone for 70% less than domestic hires.',
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('department_cards_block');

        $fields
            ->addText('headline', [
                'label' => 'Section Headline',
                'default_value' => 'Specialized Remote Roles Ready to Deploy',
            ])
            ->addTextarea('subheadline', [
                'label' => 'Section Subheadline',
                'default_value' => 'Top 1% pre-vetted specialists working in your exact timezone for 70% less than domestic hires.',
                'rows' => 2,
            ])
            ->addRepeater('cards', [
                'label' => 'Department Cards',
                'layout' => 'block',
                'button_label' => 'Add Department Card',
            ])
            ->addText('title', [
                'label' => 'Department / Category',
                'default_value' => 'Executive & Admin',
            ])
            ->addText('subtitle', [
                'label' => 'Role Title',
                'default_value' => 'Executive Assistant',
            ])
            ->addImage('image', [
                'label' => 'Card Background Image',
                'return_format' => 'url',
            ])
            ->addTextarea('description', [
                'label' => 'Role Description',
                'default_value' => 'Calendar triage, inbox zero, meeting briefs, travel coordination, and executive project management.',
                'rows' => 2,
            ])
            ->addText('hourly_rate', [
                'label' => 'Rate Range (e.g. $8 - $12/hr)',
                'default_value' => '$8 - $12/hr',
            ])
            ->addText('skills_list', [
                'label' => 'Skills / Tools (comma-separated)',
                'default_value' => 'Google Workspace, Notion, Slack, Calendly, Zoom',
            ])
            ->addTrueFalse('enable_verified_badge', [
                'label' => 'Enable Verified 1% Badge',
                'default_value' => true,
                'ui' => 1,
            ])
            ->addTrueFalse('enable_worked_at', [
                'label' => 'Enable "Worked At" Past Company',
                'default_value' => true,
                'ui' => 1,
            ])
            ->addText('worked_at_text', [
                'label' => 'Worked At Text',
                'default_value' => 'Trained with alumni from',
            ])
            ->addImage('worked_at_logo', [
                'label' => 'Company Logo',
                'return_format' => 'url',
            ])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $items = get_field('cards');

        if (! empty($items) && is_array($items)) {
            return $items;
        }

        // Realistic fallback matching original Elementor DepartmentCardWidget
        return [
            [
                'title' => 'Executive & Admin',
                'subtitle' => 'Executive Assistant',
                'image' => null,
                'description' => 'Master your schedule, clear incoming correspondence, and manage day-to-day operations with autonomous English-fluent talent.',
                'hourly_rate' => '$8 - $12/hr',
                'skills_list' => 'Inbox Triage, Calendar Management, Travel Ops, Asana, Notion',
                'enable_verified_badge' => true,
                'enable_worked_at' => true,
                'worked_at_text' => 'Alumni from',
                'worked_at_logo' => null,
            ],
            [
                'title' => 'Sales & Growth',
                'subtitle' => 'Outbound BDR / SDR',
                'image' => null,
                'description' => 'Fill your calendar with qualified prospective buyer appointments through multichannel cold email and LinkedIn outreach.',
                'hourly_rate' => '$9 - $14/hr',
                'skills_list' => 'Apollo.io, Instantly, HubSpot, LinkedIn Sales Nav, Loom',
                'enable_verified_badge' => true,
                'enable_worked_at' => true,
                'worked_at_text' => 'Alumni from',
                'worked_at_logo' => null,
            ],
            [
                'title' => 'Real Estate Ops',
                'subtitle' => 'Transaction Coordinator',
                'image' => null,
                'description' => 'Ensure contract-to-close compliance, coordinate escrow timelines, inspect disclosures, and update seller CRMs seamlessly.',
                'hourly_rate' => '$10 - $15/hr',
                'skills_list' => 'DocuSign, Dotloop, Follow Up Boss, MLS, KVCore',
                'enable_verified_badge' => true,
                'enable_worked_at' => true,
                'worked_at_text' => 'Alumni from',
                'worked_at_logo' => null,
            ],
        ];
    }
}
