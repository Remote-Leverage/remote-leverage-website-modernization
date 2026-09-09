<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

/**
 * Admin metaboxes for the `rl_partner` co-branded hub CPT (WR-115/116/117/118/119).
 *
 * Ported from the legacy rl-partners-hub plugin's RL_Partner_Metabox, which had
 * no equivalent in this codebase at all — content editors previously had no way
 * to configure a partner hub without editing raw post meta.
 */
class PartnerHubAdmin
{
    protected const POST_TYPE = 'rl_partner';

    /**
     * Fields saved verbatim as sanitized text, keyed by meta key.
     *
     * @var array<string, string> meta_key => sanitizer ('text', 'url', 'email', 'textarea')
     */
    protected const FIELDS = [
        // Branding
        '_rl_partner_name' => 'text',
        '_rl_partner_code' => 'text',
        '_rl_partner_logo_url' => 'url',
        '_rl_partner_cover_url' => 'url',
        '_rl_partner_website' => 'url',

        // Terms & Agreement Specs
        '_rl_partnership_type' => 'text',
        '_rl_territory' => 'text',
        '_rl_reporting_period' => 'text',
        '_rl_initial_term' => 'text',
        '_rl_renewal_terms' => 'text',

        // Direction A: Partner -> Remote Leverage
        '_rl_referral_form_url' => 'url',
        '_rl_referral_drive_url' => 'url',
        '_rl_intro_email' => 'email',
        '_rl_partner_to_rl_fee' => 'textarea',

        // Direction B: Remote Leverage -> Partner
        '_rl_partner_referral_label' => 'text',
        '_rl_partner_referral_email' => 'text', // may hold "pending to define", not always a valid email
        '_rl_partner_referral_url' => 'url',
        '_rl_rl_to_partner_fee' => 'textarea',

        // Dedicated Resources
        '_rl_rl_resource_title' => 'text',
        '_rl_rl_resource_url' => 'url',
        '_rl_partner_resource_title' => 'text',
        '_rl_partner_resource_url' => 'url',
        '_rl_one_pager_pdf_url' => 'url',
        '_rl_agreement_pdf_url' => 'url',

        // Co-Marketing
        '_rl_comarketing_text' => 'textarea',
        '_rl_comarketing_approval_note' => 'text',

        // Manager Contact
        '_rl_manager_name' => 'text',
        '_rl_manager_email' => 'email',
        '_rl_manager_title' => 'text',

        // Overrides
        '_rl_override_welcome_text' => 'textarea',
        '_rl_override_company_desc' => 'textarea',
        '_rl_override_referral_rules' => 'textarea',
        '_rl_override_commission_terms' => 'textarea',
    ];

    protected const SECTIONS = [
        'overview' => '1. Overview & Quick Actions',
        'icp' => '2. Ideal Client Profile',
        'services' => '3. Services Overview',
        'why-rl' => '4. Why Remote Leverage',
        'referral-program' => '5. Referral Program & Rules',
        'comarketing' => '6. Co-Marketing',
        'case-studies' => '7. Case Studies',
        'faq' => '8. Frequently Asked Questions',
        'contact' => '9. Contact Team',
    ];

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_'.self::POST_TYPE, [$this, 'saveMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueMediaUploader']);
        add_filter('manage_'.self::POST_TYPE.'_posts_columns', [$this, 'addAdminColumns']);
        add_action('manage_'.self::POST_TYPE.'_posts_custom_column', [$this, 'renderAdminColumns'], 10, 2);
    }

    public function enqueueMediaUploader(): void
    {
        global $post_type;
        if ($post_type === self::POST_TYPE) {
            wp_enqueue_media();
        }
    }

    public function addAdminColumns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $title) {
            $new[$key] = $title;
            if ($key === 'title') {
                $new['partner_code'] = 'Referral Code';
                $new['referral_form'] = 'Submission Form';
            }
        }

        return $new;
    }

    public function renderAdminColumns(string $column, int $postId): void
    {
        if ($column === 'partner_code') {
            $code = get_post_meta($postId, '_rl_partner_code', true);
            echo '<code>'.esc_html($code ?: '—').'</code>';

            return;
        }

        if ($column === 'referral_form') {
            $form = get_post_meta($postId, '_rl_referral_form_url', true);
            if ($form) {
                echo '<a href="'.esc_url($form).'" target="_blank">View Form &#8599;</a>';
            } else {
                echo '<span style="color:#999;">None set</span>';
            }
        }
    }

    public function addMetaBoxes(): void
    {
        add_meta_box('rl_partner_tools', 'Partner Data Transfer Tools', [$this, 'renderToolsMetabox'], self::POST_TYPE, 'side', 'high');
        add_meta_box('rl_partner_branding', '1. Partner Branding & Identification', [$this, 'renderBrandingMetabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('rl_partner_terms', '2. Partnership Terms & Agreement Specifications', [$this, 'renderTermsMetabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('rl_partner_actions', '3. Two-Way Referral Actions & Commission Terms', [$this, 'renderActionsMetabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('rl_partner_resources', '4. Dedicated Resources & External Hub Links', [$this, 'renderResourcesMetabox'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('rl_partner_comarketing', '5. Co-Marketing Opportunities & Guidelines', [$this, 'renderComarketingMetabox'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('rl_partner_manager', '6. Dedicated Partner Manager Contact', [$this, 'renderManagerMetabox'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('rl_partner_overrides', '7. Content Overrides & Eligibility Rules (Optional)', [$this, 'renderOverridesMetabox'], self::POST_TYPE, 'normal', 'default');
        add_meta_box('rl_partner_attachments', '8. Per-Section PDFs & Downloadable Attachments', [$this, 'renderAttachmentsMetabox'], self::POST_TYPE, 'normal', 'default');
    }

    protected function field(int $postId, string $key, string $fallback = ''): string
    {
        $value = get_post_meta($postId, $key, true);

        return $value !== '' ? (string) $value : $fallback;
    }

    public function renderToolsMetabox(\WP_Post $post): void
    {
        ?>
        <p style="font-size:12px;color:#64748b;margin:0 0 8px 0;">Export or import a complete partner configuration as JSON.</p>
        <button type="button" id="rl-export-json-btn" class="button button-primary" style="width:100%;margin-bottom:8px;">Export to JSON</button>
        <button type="button" id="rl-import-json-btn" class="button" style="width:100%;">Import from JSON</button>

        <div id="rl-import-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
            <div style="background:#fff;width:560px;max-width:90%;border-radius:8px;padding:24px;">
                <h3 style="margin-top:0;">Import Partner Configuration</h3>
                <textarea id="rl-import-json-textarea" rows="8" style="width:100%;font-family:monospace;font-size:12px;" placeholder='{"partner_name": "...", "actions": {...}}'></textarea>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                    <button type="button" id="rl-cancel-import-btn" class="button">Cancel</button>
                    <button type="button" id="rl-confirm-import-btn" class="button button-primary">Load Data into Form</button>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var fieldMap = {
                partner_name: '_rl_partner_name', partner_code: '_rl_partner_code',
                partner_logo_url: '_rl_partner_logo_url', partner_cover_url: '_rl_partner_cover_url',
                partner_website: '_rl_partner_website',
                'terms.partnership_type': '_rl_partnership_type', 'terms.territory': '_rl_territory',
                'terms.reporting_period': '_rl_reporting_period', 'terms.initial_term': '_rl_initial_term',
                'terms.renewal_terms': '_rl_renewal_terms',
                'actions.direction_partner_to_rl.form_url': '_rl_referral_form_url',
                'actions.direction_partner_to_rl.drive_sheet_url': '_rl_referral_drive_url',
                'actions.direction_partner_to_rl.intro_email': '_rl_intro_email',
                'actions.direction_partner_to_rl.fee_structure': '_rl_partner_to_rl_fee',
                'actions.direction_rl_to_partner.cta_label': '_rl_partner_referral_label',
                'actions.direction_rl_to_partner.referral_email': '_rl_partner_referral_email',
                'actions.direction_rl_to_partner.referral_url': '_rl_partner_referral_url',
                'actions.direction_rl_to_partner.fee_structure': '_rl_rl_to_partner_fee',
                'resources.rl_resource_title': '_rl_rl_resource_title', 'resources.rl_resource_url': '_rl_rl_resource_url',
                'resources.partner_resource_title': '_rl_partner_resource_title', 'resources.partner_resource_url': '_rl_partner_resource_url',
                'resources.one_pager_pdf_url': '_rl_one_pager_pdf_url', 'resources.agreement_pdf_url': '_rl_agreement_pdf_url',
                'comarketing.lead_text': '_rl_comarketing_text', 'comarketing.approval_note': '_rl_comarketing_approval_note',
                'manager.name': '_rl_manager_name', 'manager.email': '_rl_manager_email', 'manager.title': '_rl_manager_title',
                'overrides.welcome_text': '_rl_override_welcome_text', 'overrides.company_desc': '_rl_override_company_desc',
                'overrides.referral_rules': '_rl_override_referral_rules', 'overrides.commission_terms': '_rl_override_commission_terms'
            };

            function getByPath(obj, path) {
                return path.split('.').reduce(function(o, k) { return (o && o[k] !== undefined) ? o[k] : undefined; }, obj);
            }

            $('#rl-export-json-btn').on('click', function(e) {
                e.preventDefault();
                var data = { post_title: $('#title').val() || '' };
                $.each(fieldMap, function(path, fieldId) {
                    var keys = path.split('.');
                    var val = $('#' + fieldId).val() || '';
                    var target = data;
                    for (var i = 0; i < keys.length - 1; i++) {
                        target[keys[i]] = target[keys[i]] || {};
                        target = target[keys[i]];
                    }
                    target[keys[keys.length - 1]] = val;
                });
                data.comarketing = data.comarketing || {};
                data.comarketing.enabled = $('input[name="_rl_enable_comarketing"]').is(':checked');

                var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'rl-partner-' + ($('#_rl_partner_code').val() || 'partner').toLowerCase().replace(/[^a-z0-9_-]/g, '-') + '.json';
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
            });

            $('#rl-import-json-btn').on('click', function(e) { e.preventDefault(); $('#rl-import-modal').css('display', 'flex'); });
            $('#rl-cancel-import-btn').on('click', function(e) { e.preventDefault(); $('#rl-import-modal').hide(); });

            $('#rl-confirm-import-btn').on('click', function(e) {
                e.preventDefault();
                var raw = $('#rl-import-json-textarea').val().trim();
                if (!raw) { alert('Please paste valid JSON first.'); return; }
                try {
                    var data = JSON.parse(raw);
                    if (data.post_title && $('#title').length) $('#title').val(data.post_title);
                    $.each(fieldMap, function(path, fieldId) {
                        var val = getByPath(data, path);
                        if (val !== undefined && val !== '') $('#' + fieldId).val(val);
                    });
                    if (data.comarketing) {
                        $('input[name="_rl_enable_comarketing"]').prop('checked', !!data.comarketing.enabled);
                    }
                    $('#rl-import-modal').hide();
                    alert('Partner configuration loaded into the form. Click Update to save.');
                } catch (err) {
                    alert('Invalid JSON format: ' + err.message);
                }
            });
        });
        </script>
        <?php
    }

    public function renderBrandingMetabox(\WP_Post $post): void
    {
        wp_nonce_field('rl_partner_meta_nonce_action', 'rl_partner_meta_nonce');
        ?>
        <p><label style="font-weight:600;display:block;">Partner Name</label>
        <input type="text" id="_rl_partner_name" name="_rl_partner_name" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_name')); ?>" placeholder="e.g. Oyster" /></p>

        <p><label style="font-weight:600;display:block;">Referral Code</label>
        <input type="text" id="_rl_partner_code" name="_rl_partner_code" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_code')); ?>" placeholder="e.g. RL-OYSTER" /></p>

        <p><label style="font-weight:600;display:block;">Partner Logo URL</label>
        <input type="url" id="_rl_partner_logo_url" name="_rl_partner_logo_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_logo_url')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Partner Hero Cover Image URL</label>
        <input type="url" id="_rl_partner_cover_url" name="_rl_partner_cover_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_cover_url')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Partner Official Website URL</label>
        <input type="url" id="_rl_partner_website" name="_rl_partner_website" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_website')); ?>" /></p>
        <?php
    }

    public function renderTermsMetabox(\WP_Post $post): void
    {
        ?>
        <p><label style="font-weight:600;display:block;">Partnership Type</label>
        <input type="text" id="_rl_partnership_type" name="_rl_partnership_type" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partnership_type', 'Mutual Referral Partner')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Territory Coverage</label>
        <input type="text" id="_rl_territory" name="_rl_territory" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_territory', 'Worldwide')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Reporting Period</label>
        <input type="text" id="_rl_reporting_period" name="_rl_reporting_period" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_reporting_period', 'Quarterly')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Initial Term</label>
        <input type="text" id="_rl_initial_term" name="_rl_initial_term" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_initial_term', '12 months')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Renewal Terms</label>
        <input type="text" id="_rl_renewal_terms" name="_rl_renewal_terms" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_renewal_terms', 'Automatic 12-month renewal, subject to the partnership agreement')); ?>" /></p>
        <?php
    }

    public function renderActionsMetabox(\WP_Post $post): void
    {
        ?>
        <div style="border-left:4px solid #892BE2;padding:14px;background:#f8fafc;margin-bottom:16px;">
            <h4 style="margin-top:0;">Direction A: Partner &rarr; Remote Leverage (Submitting Leads to RL)</h4>

            <p><label style="font-weight:600;display:block;">Google Form Submission URL</label>
            <input type="url" id="_rl_referral_form_url" name="_rl_referral_form_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_referral_form_url')); ?>" /></p>

            <p><label style="font-weight:600;display:block;">Referral Tracking Sheet URL</label>
            <input type="url" id="_rl_referral_drive_url" name="_rl_referral_drive_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_referral_drive_url')); ?>" /></p>

            <p><label style="font-weight:600;display:block;">Remote Leverage Direct Intro Email</label>
            <input type="email" id="_rl_intro_email" name="_rl_intro_email" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_intro_email', 'partnerships@remoteleverage.com')); ?>" /></p>

            <p><label style="font-weight:600;display:block;">Partner Referral Fee Structure (Partner &rarr; RL)</label>
            <textarea id="_rl_partner_to_rl_fee" name="_rl_partner_to_rl_fee" rows="2" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_partner_to_rl_fee')); ?></textarea></p>
        </div>

        <div style="border-left:4px solid #007cba;padding:14px;background:#f8fafc;">
            <h4 style="margin-top:0;">Direction B: Remote Leverage &rarr; Partner (Referring Clients to Partner)</h4>

            <p><label style="font-weight:600;display:block;">Partner Referral Button Label</label>
            <input type="text" id="_rl_partner_referral_label" name="_rl_partner_referral_label" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_referral_label')); ?>" placeholder="e.g. Refer a Client to Oyster" /></p>

            <p><label style="font-weight:600;display:block;">Partner Referral Contact Email</label>
            <input type="text" id="_rl_partner_referral_email" name="_rl_partner_referral_email" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_referral_email')); ?>" placeholder="e.g. partners@oysterhr.com or 'pending to define'" /></p>

            <p><label style="font-weight:600;display:block;">Partner Referral Form / Portal URL (Optional)</label>
            <input type="url" id="_rl_partner_referral_url" name="_rl_partner_referral_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_referral_url')); ?>" /></p>

            <p><label style="font-weight:600;display:block;">RL Referral Fee Structure (RL &rarr; Partner)</label>
            <textarea id="_rl_rl_to_partner_fee" name="_rl_rl_to_partner_fee" rows="2" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_rl_to_partner_fee')); ?></textarea></p>
        </div>
        <?php
    }

    public function renderResourcesMetabox(\WP_Post $post): void
    {
        ?>
        <p><label style="font-weight:600;display:block;">Remote Leverage Resource Title</label>
        <input type="text" id="_rl_rl_resource_title" name="_rl_rl_resource_title" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_rl_resource_title', 'Remote Leverage Partner Assets')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Remote Leverage Resource Link</label>
        <input type="url" id="_rl_rl_resource_url" name="_rl_rl_resource_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_rl_resource_url')); ?>" /></p>

        <hr />

        <p><label style="font-weight:600;display:block;">Partner Resource Hub Title</label>
        <input type="text" id="_rl_partner_resource_title" name="_rl_partner_resource_title" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_resource_title')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Partner Resource Link (Notion / Portal URL)</label>
        <input type="url" id="_rl_partner_resource_url" name="_rl_partner_resource_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_partner_resource_url')); ?>" /></p>

        <hr />

        <p><label style="font-weight:600;display:block;">Overview PDF One-Pager URL</label>
        <input type="url" id="_rl_one_pager_pdf_url" name="_rl_one_pager_pdf_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_one_pager_pdf_url')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Partner Agreement Document / PDF URL</label>
        <input type="url" id="_rl_agreement_pdf_url" name="_rl_agreement_pdf_url" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_agreement_pdf_url')); ?>" /></p>
        <?php
    }

    public function renderComarketingMetabox(\WP_Post $post): void
    {
        $enabled = get_post_meta($post->ID, '_rl_enable_comarketing', true);
        if ($enabled === '') {
            $enabled = '1';
        }
        ?>
        <p><label>
            <input type="checkbox" name="_rl_enable_comarketing" value="1" <?php checked($enabled, '1'); ?> />
            <strong>Enable Co-Marketing Tab in Navigation</strong>
        </label></p>

        <p><label style="font-weight:600;display:block;">Co-Marketing Opportunities Description</label>
        <textarea id="_rl_comarketing_text" name="_rl_comarketing_text" rows="3" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_comarketing_text')); ?></textarea></p>

        <p><label style="font-weight:600;display:block;">Mutual Approval Requirement Notice</label>
        <input type="text" id="_rl_comarketing_approval_note" name="_rl_comarketing_approval_note" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_comarketing_approval_note')); ?>" /></p>
        <?php
    }

    public function renderManagerMetabox(\WP_Post $post): void
    {
        ?>
        <p><label style="font-weight:600;display:block;">Manager Name</label>
        <input type="text" id="_rl_manager_name" name="_rl_manager_name" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_manager_name', 'Partnerships Team')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Manager Email</label>
        <input type="email" id="_rl_manager_email" name="_rl_manager_email" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_manager_email', 'partnerships@remoteleverage.com')); ?>" /></p>

        <p><label style="font-weight:600;display:block;">Manager Title / Role</label>
        <input type="text" id="_rl_manager_title" name="_rl_manager_title" class="widefat" value="<?php echo esc_attr($this->field($post->ID, '_rl_manager_title', 'Dedicated Partnerships Team')); ?>" /></p>
        <?php
    }

    public function renderOverridesMetabox(\WP_Post $post): void
    {
        ?>
        <p style="background:#f0f6fc;border-left:4px solid #007cba;padding:10px;">
            <strong>Note:</strong> Leave any of these fields blank to automatically use the global defaults.
        </p>

        <p><label style="font-weight:600;display:block;">Custom Welcome Message (Hero Section)</label>
        <textarea id="_rl_override_welcome_text" name="_rl_override_welcome_text" rows="3" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_override_welcome_text')); ?></textarea></p>

        <p><label style="font-weight:600;display:block;">Custom Remote Leverage Description (Overview Section)</label>
        <textarea id="_rl_override_company_desc" name="_rl_override_company_desc" rows="3" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_override_company_desc')); ?></textarea></p>

        <p><label style="font-weight:600;display:block;">Custom Referral Eligibility Rules (One rule per line)</label>
        <textarea id="_rl_override_referral_rules" name="_rl_override_referral_rules" rows="5" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_override_referral_rules')); ?></textarea></p>

        <p><label style="font-weight:600;display:block;">Additional Commission Terms & Notes</label>
        <textarea id="_rl_override_commission_terms" name="_rl_override_commission_terms" rows="3" class="widefat"><?php echo esc_textarea($this->field($post->ID, '_rl_override_commission_terms')); ?></textarea></p>
        <?php
    }

    public function renderAttachmentsMetabox(\WP_Post $post): void
    {
        $saved = get_post_meta($post->ID, '_rl_section_attachments', true);
        if (! is_array($saved)) {
            $saved = [];
        }
        ?>
        <p style="background:#F5EEFD;border-left:4px solid #892BE2;padding:12px;">
            Attach downloadable PDFs <strong>per section</strong> for this partnership. Files appear on the corresponding tab and in the right sidebar.
        </p>

        <div class="rl-sec-attachments-container">
            <?php foreach (self::SECTIONS as $secKey => $secTitle) {
                $items = isset($saved[$secKey]) && is_array($saved[$secKey]) ? $saved[$secKey] : [];
                ?>
                <div style="margin-bottom:16px;background:#fafafa;border:1px solid #e4e4e7;border-radius:8px;padding:12px;">
                    <div style="font-weight:700;display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span><?php echo esc_html($secTitle); ?></span>
                        <button type="button" class="button button-small rl-add-attachment-btn" data-section="<?php echo esc_attr($secKey); ?>">+ Add Attachment</button>
                    </div>
                    <div id="rl-attachments-list-<?php echo esc_attr($secKey); ?>">
                        <?php foreach ($items as $idx => $item) { ?>
                            <div class="rl-attachment-row" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                                <input type="text" name="_rl_section_attachments[<?php echo esc_attr($secKey); ?>][<?php echo (int) $idx; ?>][title]" value="<?php echo esc_attr($item['title'] ?? ''); ?>" placeholder="Attachment Title" style="flex:1;min-width:160px;" />
                                <input type="url" class="rl-attachment-url-input" name="_rl_section_attachments[<?php echo esc_attr($secKey); ?>][<?php echo (int) $idx; ?>][url]" value="<?php echo esc_url($item['url'] ?? ''); ?>" placeholder="File URL" style="flex:2;min-width:200px;" />
                                <button type="button" class="button rl-media-upload-btn">Upload / Select</button>
                                <input type="text" class="rl-attachment-size-input" name="_rl_section_attachments[<?php echo esc_attr($secKey); ?>][<?php echo (int) $idx; ?>][size]" value="<?php echo esc_attr($item['size'] ?? ''); ?>" placeholder="Size" style="width:90px;" />
                                <button type="button" class="button button-link-delete rl-remove-attachment-btn" onclick="jQuery(this).closest('.rl-attachment-row').remove();">Remove</button>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>

        <script>
        jQuery(function($) {
            $(document).on('click', '.rl-media-upload-btn', function(e) {
                e.preventDefault();
                var row = $(this).closest('.rl-attachment-row');
                var uploader = wp.media({ title: 'Select or Upload PDF', button: { text: 'Use File' }, multiple: false });
                uploader.on('select', function() {
                    var att = uploader.state().get('selection').first().toJSON();
                    row.find('.rl-attachment-url-input').val(att.url);
                    var titleInput = row.find('input[name*="[title]"]');
                    if (!titleInput.val()) titleInput.val(att.title || att.filename);
                    if (att.filesizeHumanReadable) row.find('.rl-attachment-size-input').val(att.filesizeHumanReadable);
                });
                uploader.open();
            });

            $(document).on('click', '.rl-add-attachment-btn', function(e) {
                e.preventDefault();
                var secKey = $(this).data('section');
                var container = $('#rl-attachments-list-' + secKey);
                var index = container.find('.rl-attachment-row').length;
                container.append(
                    '<div class="rl-attachment-row" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">' +
                    '<input type="text" name="_rl_section_attachments[' + secKey + '][' + index + '][title]" placeholder="Attachment Title" style="flex:1;min-width:160px;" />' +
                    '<input type="url" class="rl-attachment-url-input" name="_rl_section_attachments[' + secKey + '][' + index + '][url]" placeholder="File URL" style="flex:2;min-width:200px;" />' +
                    '<button type="button" class="button rl-media-upload-btn">Upload / Select</button>' +
                    '<input type="text" class="rl-attachment-size-input" name="_rl_section_attachments[' + secKey + '][' + index + '][size]" placeholder="Size" style="width:90px;" />' +
                    '<button type="button" class="button button-link-delete rl-remove-attachment-btn" onclick="jQuery(this).closest(\'.rl-attachment-row\').remove();">Remove</button>' +
                    '</div>'
                );
            });
        });
        </script>
        <?php
    }

    public function saveMetaBoxes(int $postId): void
    {
        if (! isset($_POST['rl_partner_meta_nonce']) || ! wp_verify_nonce($_POST['rl_partner_meta_nonce'], 'rl_partner_meta_nonce_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        update_post_meta($postId, '_rl_enable_comarketing', isset($_POST['_rl_enable_comarketing']) ? '1' : '0');

        foreach (self::FIELDS as $field => $sanitizer) {
            if (! isset($_POST[$field])) {
                continue;
            }

            $value = match ($sanitizer) {
                'url' => esc_url_raw($_POST[$field]),
                'email' => sanitize_email($_POST[$field]),
                'textarea' => sanitize_textarea_field($_POST[$field]),
                default => sanitize_text_field($_POST[$field]),
            };

            update_post_meta($postId, $field, $value);
        }

        if (isset($_POST['_rl_section_attachments']) && is_array($_POST['_rl_section_attachments'])) {
            $clean = [];
            foreach ($_POST['_rl_section_attachments'] as $secKey => $items) {
                $secKey = sanitize_key($secKey);
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    if (empty($item['url'])) {
                        continue;
                    }
                    $clean[$secKey][] = [
                        'title' => sanitize_text_field($item['title'] ?? ''),
                        'url' => esc_url_raw($item['url']),
                        'size' => sanitize_text_field($item['size'] ?? 'PDF Document'),
                    ];
                }
            }
            update_post_meta($postId, '_rl_section_attachments', $clean);
        } else {
            delete_post_meta($postId, '_rl_section_attachments');
        }
    }
}
