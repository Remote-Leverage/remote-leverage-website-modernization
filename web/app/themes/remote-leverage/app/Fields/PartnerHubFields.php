<?php

declare(strict_types=1);

namespace App\Fields;

use App\Domains\PartnerHub\Services\PartnerHubTabResolver;
use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Field;

/**
 * Native ACF field group for the `rl_partner` co-branded hub CPT (WR-115+).
 *
 * Replaces the hand-rolled PartnerHubAdmin metaboxes with a single tabbed ACF
 * field group — the same admin UI pattern ACF gives every content editor
 * elsewhere in this theme, instead of a bespoke one-off form. Field names are
 * kept identical to the original `_rl_*` meta keys so no data migration or
 * (mostly) template changes were needed.
 */
class PartnerHubFields extends Field
{
    /**
     * The field group.
     */
    public function fields(): array
    {
        $fields = Builder::make('partner_hub');

        $fields->setLocation('post_type', '==', 'rl_partner');

        $fields->addTab('Branding');
        $fields->addText('_rl_partner_name', [
            'label' => 'Partner Name',
            'placeholder' => 'e.g. Oyster',
        ]);
        $fields->addText('_rl_partner_code', [
            'label' => 'Referral Code',
            'placeholder' => 'e.g. RL-OYSTER',
        ]);
        $fields->addImage('_rl_partner_logo_url', [
            'label' => 'Partner Logo',
            'return_format' => 'url',
            'preview_size' => 'medium',
        ]);
        $fields->addImage('_rl_partner_cover_url', [
            'label' => 'Hero Cover Image',
            'instructions' => 'Optional background image for the hub\'s hero banner.',
            'return_format' => 'url',
            'preview_size' => 'medium',
        ]);
        $fields->addUrl('_rl_partner_website', [
            'label' => 'Partner Official Website',
        ]);

        $fields->addTab('Terms');
        $fields->addText('_rl_partnership_type', [
            'label' => 'Partnership Type',
            'default_value' => 'Mutual Referral Partner',
        ]);
        $fields->addText('_rl_territory', [
            'label' => 'Territory Coverage',
            'default_value' => 'Worldwide',
        ]);
        $fields->addText('_rl_reporting_period', [
            'label' => 'Reporting Period',
            'default_value' => 'Quarterly',
        ]);
        $fields->addText('_rl_initial_term', [
            'label' => 'Initial Term',
            'default_value' => '12 months',
        ]);
        $fields->addText('_rl_renewal_terms', [
            'label' => 'Renewal Terms',
            'default_value' => 'Automatic 12-month renewal, subject to the partnership agreement',
        ]);

        $fields->addTab('Referral Actions');
        $fields->addMessage(
            'direction_a_heading',
            '<strong>Direction A: Partner &rarr; Remote Leverage</strong> — how this partner submits leads to us.'
        );
        $fields->addUrl('_rl_referral_form_url', [
            'label' => 'Google Form Submission URL',
        ]);
        $fields->addUrl('_rl_referral_drive_url', [
            'label' => 'Referral Tracking Sheet URL',
        ]);
        $fields->addEmail('_rl_intro_email', [
            'label' => 'Remote Leverage Direct Intro Email',
            'default_value' => 'partnerships@remoteleverage.com',
        ]);
        $fields->addTextarea('_rl_partner_to_rl_fee', [
            'label' => 'Partner Referral Fee Structure (Partner &rarr; RL)',
            'rows' => 3,
        ]);
        $fields->addMessage(
            'direction_b_heading',
            '<strong>Direction B: Remote Leverage &rarr; Partner</strong> — how we refer clients to this partner.'
        );
        $fields->addText('_rl_partner_referral_label', [
            'label' => 'Partner Referral Button Label',
            'placeholder' => 'e.g. Refer a Client to Oyster',
        ]);
        $fields->addText('_rl_partner_referral_email', [
            'label' => 'Partner Referral Contact Email',
            'instructions' => 'May be "pending to define" if not yet configured — not validated as a strict email.',
        ]);
        $fields->addUrl('_rl_partner_referral_url', [
            'label' => 'Partner Referral Form / Portal URL',
        ]);
        $fields->addTextarea('_rl_rl_to_partner_fee', [
            'label' => 'RL Referral Fee Structure (RL &rarr; Partner)',
            'rows' => 3,
        ]);

        $fields->addTab('Resources');
        $fields->addText('_rl_rl_resource_title', [
            'label' => 'Remote Leverage Resource Title',
            'default_value' => 'Remote Leverage Partner Assets',
        ]);
        $fields->addUrl('_rl_rl_resource_url', [
            'label' => 'Remote Leverage Resource Link',
        ]);
        $fields->addText('_rl_partner_resource_title', [
            'label' => 'Partner Resource Hub Title',
        ]);
        $fields->addUrl('_rl_partner_resource_url', [
            'label' => 'Partner Resource Link (Notion / Portal URL)',
        ]);
        $fields->addFile('_rl_one_pager_pdf_url', [
            'label' => 'Overview One-Pager PDF',
            'return_format' => 'url',
        ]);
        $fields->addFile('_rl_agreement_pdf_url', [
            'label' => 'Partner Agreement PDF',
            'return_format' => 'url',
        ]);

        $fields->addRepeater('_rl_section_attachments', [
            'label' => 'Per-Section PDF Attachments',
            'instructions' => 'Attach downloadable PDFs per tab. Files appear on the matching tab and in the right sidebar.',
            'layout' => 'block',
            'button_label' => 'Add Attachment',
        ])
            ->addSelect('section', [
                'label' => 'Tab Section',
                'choices' => PartnerHubTabResolver::resolveTabs(true),
                'required' => 1,
            ])
            ->addText('title', [
                'label' => 'Attachment Title',
            ])
            ->addFile('file', [
                'label' => 'File',
                'return_format' => 'array',
                'required' => 1,
            ])
            ->endRepeater();

        $fields->addTab('Co-Marketing');
        $fields->addTrueFalse('_rl_enable_comarketing', [
            'label' => 'Enable Co-Marketing Tab',
            'ui' => 1,
            'default_value' => 1,
        ]);
        $fields->addTextarea('_rl_comarketing_text', [
            'label' => 'Co-Marketing Opportunities Description',
            'rows' => 3,
        ]);
        $fields->addText('_rl_comarketing_approval_note', [
            'label' => 'Mutual Approval Requirement Notice',
        ]);

        $fields->addTab('Manager');
        $fields->addText('_rl_manager_name', [
            'label' => 'Manager Name',
            'default_value' => 'Partnerships Team',
        ]);
        $fields->addEmail('_rl_manager_email', [
            'label' => 'Manager Email',
            'default_value' => 'partnerships@remoteleverage.com',
        ]);
        $fields->addText('_rl_manager_title', [
            'label' => 'Manager Title / Role',
            'default_value' => 'Partnerships Director',
        ]);

        $fields->addTab('Overrides');
        $fields->addMessage(
            'overrides_note',
            'Leave any of these blank to automatically use the global defaults.'
        );
        $fields->addTextarea('_rl_override_welcome_text', [
            'label' => 'Custom Welcome Message (Hero Section)',
            'rows' => 3,
        ]);
        $fields->addTextarea('_rl_override_company_desc', [
            'label' => 'Custom Remote Leverage Description (Overview Section)',
            'rows' => 3,
        ]);
        $fields->addTextarea('_rl_override_referral_rules', [
            'label' => 'Custom Referral Eligibility Rules',
            'instructions' => 'One rule per line. If empty, the standard default rules are used.',
            'rows' => 5,
        ]);
        $fields->addTextarea('_rl_override_commission_terms', [
            'label' => 'Additional Commission Terms & Notes',
            'rows' => 3,
        ]);

        return $fields->build();
    }
}
