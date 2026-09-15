<?php

declare(strict_types=1);

namespace App\Fields;

use App\Domains\PartnerHub\Services\PartnerHubGlobalData;
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

        $fields->addTab('Directory');
        $fields->addMessage(
            'directory_note',
            'How this partner appears on the <code>/partners/</code> directory grid. The card links through to this partner\'s co-branded hub.'
        );
        $fields->addSelect('_rl_directory_category', [
            'label' => 'Directory Category',
            'instructions' => 'Determines which filter pill shows this partner.',
            'choices' => array_combine(
                PartnerHubGlobalData::getDirectoryCategories(),
                PartnerHubGlobalData::getDirectoryCategories(),
            ),
            'default_value' => 'Staffing & HR',
        ]);
        $fields->addTextarea('_rl_directory_description', [
            'label' => 'Directory Card Description',
            'instructions' => 'Shown on the card and matched by the directory search. Around 160 characters reads best — the card clamps to three lines.',
            'rows' => 3,
        ]);
        $fields->addText('_rl_directory_perk', [
            'label' => 'Exclusive Perk / Benefit',
            'instructions' => 'Optional. Rendered as the highlighted strip on the card; clamped to one line.',
        ]);
        $fields->addTrueFalse('_rl_directory_featured', [
            'label' => 'Featured Partner',
            'instructions' => 'Adds the Featured badge and includes this partner in the "Featured Only" filter.',
            'ui' => 1,
            'default_value' => 0,
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

        $fields->addTab('Services');
        $fields->addMessage(
            'services_note',
            'Leave both blank to show the standard Remote Leverage service catalogue on the Services Overview tab.'
        );
        $fields->addTextarea('_rl_services_desc', [
            'label' => 'Section Subtitle / Lead Description',
            'placeholder' => PartnerHubGlobalData::getServicesDesc(),
            'rows' => 2,
            // Explicit key. ACF Composer derives a repeater sub-field's key as
            // field_<group>_<repeater>_<sub>, so this top-level field would otherwise
            // collide with the `desc` sub-field of the `_rl_services` repeater below —
            // both resolve to field_partner_hub__rl_services_desc, and ACF silently
            // renames the sub-field, blanking every custom service description.
            // Keeping the meta key `_rl_services_desc` matters (it is the legacy key),
            // so the key is disambiguated instead of the name.
            'key' => 'services_lead_desc',
        ]);
        $fields->addRepeater('_rl_services', [
            'label' => 'Custom Service Catalogue',
            'instructions' => 'Adding any row replaces the full global catalogue for this partner — it does not append to it.',
            'layout' => 'block',
            'button_label' => 'Add Service',
        ])
            ->addText('name', [
                'label' => 'Service Name',
                'required' => 1,
            ])
            ->addText('best_for', [
                'label' => 'Best For',
                'instructions' => 'Target client or use case, shown under the service name.',
            ])
            ->addTextarea('desc', [
                'label' => 'Description',
                'rows' => 3,
            ])
            ->endRepeater();

        $fields->addTab('Lifecycle');
        $fields->addMessage(
            'lifecycle_note',
            'Leave empty to show the standard six-stage referral pipeline on the Referral Program tab.'
        );
        $fields->addRepeater('_rl_lifecycle_stages', [
            'label' => 'Custom Referral Lifecycle Stages',
            'instructions' => 'Adding any row replaces the full default pipeline for this partner — it does not append to it.',
            'layout' => 'block',
            'button_label' => 'Add Stage',
        ])
            ->addText('status', [
                'label' => 'Stage Title',
                'required' => 1,
            ])
            ->addTextarea('desc', [
                'label' => 'Description',
                'rows' => 2,
            ])
            ->endRepeater();

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
        $fields->addTextarea('_rl_override_value_prop', [
            'label' => 'Custom Direct-Hire Value Proposition (Overview Callout)',
            'placeholder' => PartnerHubGlobalData::getValueProposition()['desc'],
            'rows' => 3,
        ]);
        $fields->addTextarea('_rl_override_target_fit', [
            'label' => 'Custom Target Profile Fit (ICP Callout)',
            'placeholder' => PartnerHubGlobalData::getTargetFit()['desc'],
            'rows' => 3,
        ]);
        $fields->addTextarea('_rl_target_industries', [
            'label' => 'Custom Target Industries (ICP)',
            'instructions' => 'One industry per line. If empty, the 16 default industries are used.',
            'rows' => 5,
        ]);

        return $fields->build();
    }
}
