<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Lead\Actions\BlockLeadProfileAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Data\LeadAudience;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Models\LeadProfile;
use App\Domains\Lead\Services\LeadAvatar;
use App\Domains\Lead\Services\LeadPlatform;
use App\Domains\Lead\Services\LeadSearch;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Lead\Services\LeadStatus;
use App\Domains\Lead\Services\LeadSubmission;
use App\Domains\Scheduling\Actions\RetryFailedBookingAction;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Services\AvailabilityHealthMonitor;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LeadsAdminDashboard
{
    /** What each timeline relation is called in the notice shown when it cannot be read. */
    private const TIMELINE_SOURCE_LABELS = [
        'activityLogs' => 'activity log',
        'integrationCalls' => 'integration call history',
    ];

    /** @var array<string, EloquentCollection> Loaded timeline relations, keyed "<lead id>:<relation>". */
    private array $timelineSources = [];

    /** @var array<string, string> Labels of relations that failed to load, same key shape. */
    private array $unavailableSources = [];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
        add_action('admin_init', [$this, 'handleAdminActions']);
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);
    }

    public function addAdminBodyClass(string $classes): string
    {
        $page = $_GET['page'] ?? '';
        if (str_starts_with((string) $page, 'rl-leads')) {
            $classes .= ' rl-leads-screen';
        }

        return $classes;
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            page_title: 'Leads & Inquiries',
            menu_title: 'Leads',
            capability: 'manage_options',
            menu_slug: 'rl-leads',
            callback: [$this, 'renderDashboard'],
            icon_url: 'dashicons-groups',
            position: 30,
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'All Leads & Submissions',
            menu_title: 'All Leads',
            capability: 'manage_options',
            menu_slug: 'rl-leads',
            callback: [$this, 'renderDashboard'],
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'Live Activity & Audit Logs',
            menu_title: 'Activity Logs',
            capability: 'manage_options',
            menu_slug: 'rl-leads-activity',
            callback: [$this, 'renderActivityLogs'],
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'API & Integration Diagnostics',
            menu_title: 'Diagnostics',
            capability: 'manage_options',
            menu_slug: 'rl-leads-diagnostics',
            callback: [$this, 'renderDiagnostics'],
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'Lead Form & Routing Settings',
            menu_title: 'Settings',
            capability: 'manage_options',
            menu_slug: 'rl-leads-settings',
            callback: [$this, 'renderSettings'],
        );
    }

    public function handleAdminActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_REQUEST['rl_action'] ?? '');

        /*
         * Serving a finished export, not building one. The build happens in batches over AJAX
         * (see LeadExportPanel) because a single request that walks the whole lead table is a
         * request that dies on a timeout and leaves a truncated file looking complete.
         */
        if ($action === 'download_export') {
            app(LeadExportPanel::class)->handleDownload(sanitize_text_field($_GET['job'] ?? ''));
            exit;
        }

        if ($action === 'purge_leads') {
            check_admin_referer('rl_purge_leads_nonce');
            $purgedCount = app(PurgeOldLeadsAction::class)->execute(30);
            Cache::forget(Lead::KPI_CACHE_KEY);
            wp_safe_redirect(admin_url('admin.php?page=rl-leads&purged_count='.$purgedCount));
            exit;
        }

        if ($action === 'update_status') {
            check_admin_referer('rl_update_status_nonce');
            $leadId = absint($_POST['lead_id'] ?? 0);
            $newStatus = sanitize_text_field($_POST['new_status'] ?? '');
            $lead = Lead::find($leadId);

            /*
             * Checked against the enum rather than a list kept by hand. The list here used to
             * admit 'qualified' and 'partial', neither of which the column accepts — so the
             * guard passed and the UPDATE was what failed, which under STRICT_TRANS_TABLES is a
             * 500 rather than a message anyone can act on.
             */
            if ($lead && LeadStatus::isKnown($newStatus)) {
                $oldStatus = $lead->status;
                $lead->update(['status' => $newStatus]);
                Cache::forget(Lead::KPI_CACHE_KEY);

                LeadActivityLog::create([
                    'lead_id' => $lead->id,
                    'event_type' => 'StatusUpdatedManually',
                    'actor_domain' => 'Lead',
                    'stage' => 'admin_action',
                    'outcome' => 'succeeded',
                    'description' => "Status manually changed from {$oldStatus} to {$newStatus} by admin",
                    'payload' => ['changed_by' => wp_get_current_user()->user_login, 'from' => $oldStatus, 'to' => $newStatus],
                    'created_at' => Carbon::now(),
                ]);

                wp_safe_redirect(admin_url('admin.php?page=rl-leads&view_lead='.$lead->id.'&status_updated=1'));
                exit;
            }
        }

        if ($action === 'block_lead_profile' || $action === 'unblock_lead_profile') {
            check_admin_referer('rl_block_lead_nonce');

            $lead = Lead::query()->find((int) ($_POST['lead_id'] ?? 0));

            if ($lead) {
                $blocker = app(BlockLeadProfileAction::class);

                if ($action === 'block_lead_profile') {
                    $blocker->blockLead($lead, trim((string) ($_POST['block_reason'] ?? '')));
                } elseif ($lead->profile_id) {
                    $profile = LeadProfile::query()->find($lead->profile_id);

                    if ($profile) {
                        $blocker->unblock($profile);
                    }
                }

                wp_safe_redirect(admin_url('admin.php?page=rl-leads&view_lead='.$lead->id.'&block_updated=1'));
                exit;
            }
        }

        if ($action === 'save_lead_settings') {
            check_admin_referer('rl_save_lead_settings_nonce');

            $result = app(LeadSettingsService::class)->save([
                'retention_days' => $_POST['retention_days'] ?? '',
                'notification_emails' => $_POST['notification_emails'] ?? '',
                'optional_fields' => $_POST['optional_fields'] ?? [],
                'hubspot_access_token' => $_POST['hubspot_access_token'] ?? '',
                'hubspot_portal_id' => $_POST['hubspot_portal_id'] ?? '',
                'hubspot_lifecycle_property' => $_POST['hubspot_lifecycle_property'] ?? '',
                'hubspot_lifecycle_fulfilled_value' => $_POST['hubspot_lifecycle_fulfilled_value'] ?? '',
                'zerobounce_enabled' => $_POST['zerobounce_enabled'] ?? '',
                'zerobounce_api_key' => $_POST['zerobounce_api_key'] ?? '',
                'domain_validator_mode' => $_POST['domain_validator_mode'] ?? 'none',
                'email_domains' => $_POST['email_domains'] ?? '',
                'blacklisted_emails' => $_POST['blacklisted_emails'] ?? '',
                'email_validation_message' => $_POST['email_validation_message'] ?? '',
                'slack_webhook_url' => $_POST['slack_webhook_url'] ?? '',
                'slack_signing_secret' => $_POST['slack_signing_secret'] ?? '',
                'lead_webhook_url' => $_POST['lead_webhook_url'] ?? '',
            ]);

            if ($result['success']) {
                wp_safe_redirect(admin_url('admin.php?page=rl-leads-settings&settings_saved=1'));
            } else {
                wp_safe_redirect(admin_url('admin.php?page=rl-leads-settings&settings_error='.urlencode(implode(' ', $result['errors']))));
            }
            exit;
        }

        if ($action === 'retry_calendly_booking') {
            check_admin_referer('rl_retry_booking_nonce');
            $leadId = absint($_REQUEST['lead_id'] ?? 0);
            $result = app(RetryFailedBookingAction::class)->execute($leadId);
            Cache::forget(Lead::KPI_CACHE_KEY);
            wp_safe_redirect(admin_url('admin.php?page=rl-leads-diagnostics&retry_result='.($result['success'] ? 'success' : 'failed').'&lead_id='.$leadId));
            exit;
        }

        if ($action === 'delete_lead') {
            check_admin_referer('rl_delete_lead_nonce');
            $leadId = absint($_GET['lead_id'] ?? 0);
            $lead = Lead::find($leadId);

            if ($lead) {
                $lead->activityLogs()->delete();
                $lead->delete();
                Cache::forget(Lead::KPI_CACHE_KEY);
                wp_safe_redirect(admin_url('admin.php?page=rl-leads&lead_deleted=1'));
                exit;
            }
        }
    }

    public function enqueueAdminStyles(string $hook): void
    {
        if (! str_contains($hook, 'rl-leads')) {
            return;
        }

        wp_add_inline_style('wp-admin', '
            /* --- Base Typography & Reset --- */
            .rl-admin-wrap {
                max-width: 1360px;
                margin: 28px auto 64px auto !important;
                padding: 0 20px !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                color: #09090b;
                -webkit-font-smoothing: antialiased;
            }
            .rl-admin-wrap * {
                box-sizing: border-box;
            }

            /* --- Header & Layout --- */
            .rl-admin-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 20px;
            }
            .rl-admin-title {
                font-size: 24px;
                font-weight: 700;
                letter-spacing: -0.025em;
                color: #09090b;
                margin: 0;
                line-height: 1.25;
            }
            .rl-admin-subtitle {
                margin: 4px 0 0;
                color: #71717a;
                font-size: 13px;
                line-height: 1.4;
            }
            .rl-actions-group {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            /* --- Segmented Navigation Tabs --- */
            .rl-tabs {
                display: inline-flex;
                height: 38px;
                align-items: center;
                border-radius: 8px;
                background-color: #f4f4f5;
                padding: 3px;
                gap: 2px;
                margin-bottom: 22px;
                border: 1px solid #e4e4e7;
            }
            .rl-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 14px;
                font-size: 13px;
                font-weight: 500;
                color: #71717a;
                text-decoration: none;
                border-radius: 6px;
                transition: all 0.15s ease;
                white-space: nowrap;
            }
            .rl-tab:hover {
                color: #09090b;
            }
            .rl-tab-active {
                background-color: #ffffff;
                color: #09090b !important;
                font-weight: 600;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
            }

            /* --- Shadcn Buttons --- */
            .rl-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                font-size: 13px;
                font-weight: 500;
                padding: 7px 14px;
                border-radius: 6px;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.15s ease;
                line-height: 1.4;
                white-space: nowrap;
                border: 1px solid transparent;
            }
            .rl-btn-primary {
                background: #18181b;
                color: #fafafa !important;
                border-color: #18181b;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }
            .rl-btn-primary:hover {
                background: #27272a;
                border-color: #27272a;
                color: #fafafa !important;
            }
            .rl-btn-outline {
                background: #ffffff;
                color: #09090b !important;
                border-color: #e4e4e7;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            }
            .rl-btn-outline:hover {
                background: #f4f4f5;
                border-color: #d4d4d8;
                color: #09090b !important;
            }
            .rl-btn-destructive {
                background: #ffffff;
                color: #ef4444 !important;
                border-color: #fecaca;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            }
            .rl-btn-destructive:hover {
                background: #fef2f2;
                border-color: #f87171;
                color: #dc2626 !important;
            }
            .rl-btn-sm {
                padding: 5px 10px;
                font-size: 12px;
            }

            /* --- KPI Metrics Grid --- */
            .rl-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
                gap: 14px;
                margin-bottom: 22px;
            }
            .rl-card {
                background: #ffffff;
                border: 1px solid #e4e4e7;
                border-radius: 12px;
                padding: 18px 20px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
                text-decoration: none;
                color: inherit;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transition: all 0.15s ease;
            }
            .rl-card:hover {
                border-color: #a1a1aa;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transform: translateY(-1px);
            }
            .rl-card-active {
                border-color: #18181b !important;
                box-shadow: 0 0 0 1px #18181b, 0 4px 12px rgba(0, 0, 0, 0.06);
                background: #ffffff;
            }
            .rl-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 8px;
            }
            .rl-card-title {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #71717a;
            }
            .rl-card-icon {
                width: 16px;
                height: 16px;
                color: #a1a1aa;
                flex-shrink: 0;
            }
            .rl-card-value {
                font-size: 28px;
                font-weight: 700;
                letter-spacing: -0.025em;
                color: #09090b;
                line-height: 1.1;
            }
            .rl-card-subtext {
                font-size: 12px;
                color: #71717a;
                margin-top: 6px;
            }

            /* --- Filter Toolbar --- */
            .rl-filter-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 16px;
                background: #ffffff;
                border: 1px solid #e4e4e7;
                border-radius: 10px;
                padding: 10px 14px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            }
            .rl-filter-form {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .rl-search-wrapper {
                position: relative;
                display: inline-flex;
                align-items: center;
            }
            .rl-search-icon {
                position: absolute;
                left: 11px;
                width: 15px;
                height: 15px;
                color: #a1a1aa;
                pointer-events: none;
            }
            .rl-input-search {
                padding: 7px 12px 7px 34px !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                width: 270px;
                background: #fafafa !important;
                color: #09090b !important;
                outline: none !important;
                transition: all 0.15s ease;
                height: 36px;
                box-sizing: border-box;
            }
            .rl-input-search:focus {
                background: #ffffff !important;
                border-color: #18181b !important;
                box-shadow: 0 0 0 1px #18181b !important;
            }
            .rl-select {
                padding: 0 30px 0 12px !important;
                border: 1px solid #e4e4e7 !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                height: 36px !important;
                line-height: 34px !important;
                background: #ffffff url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2371717a\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'m6 9 6 6 6-6\'/%3E%3C/svg%3E") no-repeat right 10px center !important;
                color: #09090b !important;
                cursor: pointer;
                appearance: none;
                -webkit-appearance: none;
                box-sizing: border-box;
            }
            .rl-select:focus {
                border-color: #18181b !important;
                box-shadow: 0 0 0 1px #18181b !important;
                outline: none !important;
            }
            .rl-count-badge {
                font-size: 12px;
                color: #71717a;
                background: #f4f4f5;
                padding: 4px 10px;
                border-radius: 9999px;
                border: 1px solid #e4e4e7;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            /* --- Modern Table --- */
            .rl-table-container {
                background: #ffffff;
                border: 1px solid #e4e4e7;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            }
            .rl-lead-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 13px;
            }
            .rl-lead-table th {
                background: #fafafa;
                padding: 12px 16px;
                font-weight: 600;
                color: #71717a;
                border-bottom: 1px solid #e4e4e7;
                font-size: 12px;
                text-transform: none;
                letter-spacing: -0.01em;
            }
            .rl-lead-table td {
                padding: 14px 16px;
                border-bottom: 1px solid #f4f4f5;
                color: #09090b;
                vertical-align: middle;
            }
            .rl-lead-table tr:hover td {
                background: #fafafa;
            }
            .rl-lead-table tr:last-child td {
                border-bottom: none;
            }

            /* --- Attribution Cell (truncated: long UTM campaigns blow out the table) --- */
            .rl-lead-table th.rl-col-attr,
            .rl-lead-table td.rl-col-attr {
                width: 180px;
                max-width: 180px;
            }
            .rl-attr-cell {
                max-width: 180px;
            }
            .rl-attr-source,
            .rl-attr-campaign {
                display: block;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .rl-attr-source {
                font-weight: 600;
                color: #09090b;
                font-size: 12px;
            }
            .rl-attr-campaign {
                font-size: 11px;
                color: #71717a;
            }
            .rl-attr-cell .rl-badge {
                display: inline-block;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* --- Contact Cell Layout --- */
            .rl-contact-cell {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .rl-avatar {
                position: relative;
                width: var(--rl-avatar-size, 36px);
                height: var(--rl-avatar-size, 36px);
                flex-shrink: 0;
            }
            .rl-avatar-initials {
                width: var(--rl-avatar-size, 36px);
                height: var(--rl-avatar-size, 36px);
                border-radius: 9999px;
                background: #f4f4f5;
                border: 1px solid #e4e4e7;
                color: #09090b;
                font-weight: 600;
                font-size: calc(var(--rl-avatar-size, 36px) / 3);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            /*
             * The logo sits over the initials rather than replacing them, so a favicon that
             * 404s can simply delete itself and reveal what was always underneath. White
             * background because most favicons are dark marks cut for a light tab bar.
             */
            .rl-avatar-logo {
                position: absolute;
                inset: 0;
                width: var(--rl-avatar-size, 36px);
                height: var(--rl-avatar-size, 36px);
                border-radius: 9999px;
                border: 1px solid #e4e4e7;
                background: #ffffff;
                object-fit: contain;
                padding: calc(var(--rl-avatar-size, 36px) / 6);
                box-sizing: border-box;
            }
            .rl-contact-info {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .rl-contact-name {
                font-size: 13px;
                font-weight: 600;
                color: #09090b;
            }
            .rl-contact-email {
                font-size: 12px;
                color: #71717a;
                text-decoration: none;
            }
            .rl-contact-info .rl-contact-email {
                display: block;
                max-width: 260px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .rl-contact-email:hover {
                color: #09090b;
                text-decoration: underline;
            }
            .rl-contact-sub {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 11px;
                color: #71717a;
                margin-top: 1px;
            }
            .rl-contact-company {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-weight: 500;
                color: #3f3f46;
            }

            /* --- Badges --- */
            .rl-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 9999px;
                font-size: 11px;
                font-weight: 500;
                line-height: 1.4;
                white-space: nowrap;
                background: #ffffff;
                color: #09090b;
                border: 1px solid #e4e4e7;
            }
            .rl-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                flex-shrink: 0;
                background: #71717a;
            }
            .rl-badge-booked .rl-status-dot { background: #18181b; }
            .rl-badge-captured .rl-status-dot, .rl-badge-qualified .rl-status-dot { background: #71717a; }
            .rl-badge-partial .rl-status-dot { background: #a1a1aa; }
            .rl-badge-abandoned .rl-status-dot, .rl-badge-canceled .rl-status-dot, .rl-badge-failed .rl-status-dot { background: #dc2626; }
            .rl-badge-succeeded .rl-status-dot { background: #18181b; }

            /*
             * "Possible VA" — a phone number from outside the US and Canada, which is usually
             * someone applying for work rather than hiring. Amber rather than red: it is a
             * prompt to look, not a judgement, and a real international client wears it too.
             */
            .rl-badge-va {
                background: #fffbeb;
                color: #92400e;
                border-color: #fde68a;
                cursor: help;
            }

            /* --- Revenue Pills --- */
            .rl-pill-t10 {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 2px 8px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 500;
                background: #18181b;
                color: #fafafa;
                border: 1px solid #18181b;
                white-space: nowrap;
            }
            .rl-pill-t0 {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 2px 8px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 500;
                background: #f4f4f5;
                color: #52525b;
                border: 1px solid #e4e4e7;
                white-space: nowrap;
            }

            /* --- Meet Button --- */
            .rl-meet-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                background: #ffffff;
                color: #09090b;
                border: 1px solid #e4e4e7;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.15s ease;
            }
            .rl-meet-btn:hover {
                background: #f4f4f5;
                color: #09090b;
                border-color: #d4d4d8;
            }

            /* --- Audit Count Pill --- */
            .rl-audit-pill {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 9px;
                border-radius: 9999px;
                font-size: 11px;
                font-weight: 500;
                background: #f4f4f5;
                color: #3f3f46;
                border: 1px solid #e4e4e7;
                text-decoration: none;
                transition: all 0.15s ease;
                white-space: nowrap;
            }
            .rl-audit-pill:hover {
                background: #e4e4e7;
                color: #09090b;
            }

            /* --- Timeline & Detail --- */

            /*
             * The timeline column scrolls on its own; the left column rides the page.
             *
             * The left column is a fixed set of cards that ends. The right one is an unbounded
             * audit timeline that routinely runs several screens long, so scrolled together,
             * reading an event near its end means the contact details it refers to are long
             * gone off the top. Pinning the timeline and giving it its own scrollbar keeps both
             * readable against each other without turning the page into two windows.
             *
             * `overscroll-behavior: contain` stops the timeline, once it has hit its end, from
             * handing the scroll on to the page — without it, reaching the bottom drags the
             * whole screen, which is the exact jump this removes.
             */
            .rl-detail-grid {
                display: grid;
                grid-template-columns: 1fr 2fr;
                gap: 20px;
                align-items: start;
            }
            .rl-timeline-col {
                position: sticky;
                top: 46px;                       /* 32px admin bar + 14px breathing room */
                max-height: calc(100vh - 62px);
                overflow-y: auto;
                overscroll-behavior: contain;
                padding-right: 6px;              /* keeps the scrollbar off the card borders */
            }
            .rl-timeline-col::-webkit-scrollbar {
                width: 8px;
            }
            .rl-timeline-col::-webkit-scrollbar-thumb {
                background: #d4d4d8;
                border-radius: 9999px;
            }
            .rl-timeline-col::-webkit-scrollbar-thumb:hover {
                background: #a1a1aa;
            }
            .rl-timeline-col::-webkit-scrollbar-track {
                background: transparent;
            }

            /*
             * Below this the grid is a single column, so a viewport-height scroll box would be a
             * short window stacked under the details rather than beside them. The page scrolls
             * normally instead.
             */
            @media screen and (max-width: 1100px) {
                .rl-detail-grid {
                    grid-template-columns: 1fr;
                }
                .rl-timeline-col {
                    position: static;
                    max-height: none;
                    overflow-y: visible;
                    padding-right: 0;
                }
            }

            .rl-detail-card {
                background: #ffffff;
                border: 1px solid #e4e4e7;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
                margin-bottom: 16px;
            }
            .rl-detail-title {
                margin: 0 0 12px 0;
                font-size: 14px;
                font-weight: 600;
                color: #09090b;
                border-bottom: 1px solid #f4f4f5;
                padding-bottom: 8px;
            }
            .rl-key-value-table {
                width: 100%;
                font-size: 12px;
                line-height: 1.8;
                border-collapse: collapse;
            }
            .rl-key-value-table td:first-child {
                color: #71717a;
                width: 130px;
                vertical-align: top;
            }
            .rl-key-value-table td:last-child {
                color: #09090b;
            }
            .rl-timeline {
                position: relative;
                margin-left: 14px;
                padding-left: 20px;
                border-left: 2px solid #e4e4e7;
            }
            .rl-timeline-item {
                position: relative;
                margin-bottom: 18px;
            }
            .rl-timeline-dot {
                position: absolute;
                left: -27px;
                top: 4px;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                border: 2px solid #ffffff;
                background: #3b82f6;
                box-shadow: 0 0 0 2px #e4e4e7;
            }
            .rl-timeline-dot.dot-failed {
                background: #ef4444;
                box-shadow: 0 0 0 2px #fecaca;
            }
            .rl-timeline-dot.dot-succeeded {
                background: #10b981;
                box-shadow: 0 0 0 2px #a7f3d0;
            }
            .rl-timeline-content {
                background: #fafafa;
                border: 1px solid #e4e4e7;
                border-radius: 8px;
                padding: 12px 14px;
            }
            .rl-timeline-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 6px;
                font-size: 11px;
            }
            .rl-json-box {
                background: #09090b;
                color: #a1a1aa;
                padding: 12px 14px;
                border-radius: 8px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 11px;
                line-height: 1.6;
                overflow-x: auto;
                max-height: 280px;
                margin-top: 8px;
                white-space: pre-wrap;
                border: 1px solid #27272a;
            }

            /* --- WordPress Notice Modernization (Shadcn Callout) --- */
            .rl-admin-wrap .notice,
            .wp-admin.rl-leads-screen .notice {
                background: #ffffff !important;
                border: 1px solid #e4e4e7 !important;
                border-left: 3px solid #18181b !important;
                border-radius: 8px !important;
                padding: 12px 16px !important;
                margin: 16px 0 !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
                color: #09090b !important;
                font-size: 13px !important;
            }
            .rl-admin-wrap .notice-success,
            .wp-admin.rl-leads-screen .notice-success {
                border-left-color: #18181b !important;
            }
            .rl-admin-wrap .notice-error,
            .wp-admin.rl-leads-screen .notice-error {
                border-left-color: #dc2626 !important;
            }
            .rl-admin-wrap .notice-info,
            .wp-admin.rl-leads-screen .notice-info {
                border-left-color: #71717a !important;
            }
        ');
    }

    /**
     * Apply the lead search to a query.
     *
     * The routing itself moved to {@see LeadSearch} when the CSV export gained its own filters:
     * an export that honours the toolbar's search term has to resolve it exactly as the screen
     * does, and a second copy of this would quietly hand back a different set of leads than the
     * list it was started from.
     *
     * @param  Builder  $query
     */
    protected function applyOptimizedSearch($query, string $search): void
    {
        LeadSearch::apply($query, $search);
    }

    /**
     * Narrow a lead query to one of the toolbar's date windows.
     *
     * All three are rolling windows counted back from now, and the first one is why.
     *
     * It used to be `whereDate('created_at', Carbon::today())` — a calendar day, resolved in the
     * app timezone. PHP here runs in UTC and WordPress has no timezone set, so from 8pm Eastern
     * onwards `Carbon::today()` was already tomorrow: the filter asked for a day that had barely
     * started and returned nothing, while the table beside it printed "Sep 17" on 96 rows. It
     * emptied the list every evening, in the middle of the working day.
     *
     * A rolling 24 hours has no midnight to fall off, so it cannot do that in any timezone. The
     * cost is that this no longer answers "what came in on the 17th" — the dropdown says "Last
     * 24 hours" rather than "Today" because that is now what it means.
     *
     * @param  Builder  $query
     */
    protected function applyDateRange($query, string $range): void
    {
        // `today` is the slug this window shipped under; old bookmarks still carry it.
        $days = match ($range) {
            '24h', 'today' => 1,
            'week' => 7,
            'month' => 30,
            default => 0,
        };

        if ($days > 0) {
            $query->where('created_at', '>=', Carbon::now()->subDays($days));
        }
    }

    /**
     * Paginate a query for a WP admin screen.
     *
     * Laravel's default page parameter is `page`, which inside wp-admin is already taken by the
     * screen slug (`admin.php?page=rl-leads`). Left at the default, every screen was stuck:
     * `resolveCurrentPage()` read "rl-leads", found it non-numeric and returned page 1 forever,
     * and the Next link it generated pointed at `admin.php?page=2` — a slug that matches no
     * registered menu page. So the cursor is `paged` (WP's own name for it) and the path is the
     * admin entry point with the screen slug and the active filters appended, so a page change
     * keeps the list it was started from.
     *
     * @param  Builder  $query
     * @param  array<string, string>  $filters  Toolbar state to carry across pages; empty values are dropped.
     */
    protected function paginateScreen($query, int $perPage, string $screen, array $filters = []): LengthAwarePaginator
    {
        $paged = max(1, absint($_GET['paged'] ?? 1));

        return $query->paginate($perPage, ['*'], 'paged', $paged)
            ->withPath(admin_url('admin.php'))
            ->appends(array_merge(
                ['page' => $screen],
                array_filter($filters, static fn ($value) => $value !== '' && $value !== null),
            ));
    }

    public function renderDashboard(): void
    {
        $viewLeadId = isset($_GET['view_lead']) ? absint($_GET['view_lead']) : 0;

        if ($viewLeadId > 0) {
            $this->renderLeadDetail($viewLeadId);

            return;
        }

        $search = sanitize_text_field($_GET['s'] ?? '');
        $statusFilter = sanitize_text_field($_GET['status'] ?? '');
        $mrrFilter = sanitize_text_field($_GET['mrr'] ?? '');
        $dateFilter = sanitize_text_field($_GET['date_range'] ?? '');
        $platformFilter = sanitize_text_field($_GET['platform'] ?? '');
        $audienceFilter = sanitize_text_field($_GET['audience'] ?? '');
        $submissionFilter = sanitize_text_field($_GET['submission'] ?? '');

        /*
         * `status=partial` is where the Partial Form Drops card used to point, and it is in
         * whatever bookmarks that card produced. It never matched anything — `partial` is not in
         * the status enum — so rather than leave those links returning an empty list, they are
         * read as what they were always asking for.
         */
        if (strtolower($statusFilter) === 'partial') {
            $submissionFilter = LeadSubmission::PARTIAL;
            $statusFilter = '';
        }

        // A status the column cannot hold would filter the list down to nothing and look like an
        // answer; treat it as no filter at all.
        if ($statusFilter !== '' && ! LeadStatus::isKnown($statusFilter)) {
            $statusFilter = '';
        }

        /*
         * Newest first means newest *submission*, not highest id. The Gravity Forms import
         * writes each row's historical `submitted_at` into `created_at` while the rows still
         * take fresh auto-increment ids, so ordering by id put 2023 backfill at the top and
         * buried today's leads on the last page. Id only breaks ties within the same second.
         */
        $query = Lead::query()->latest('created_at')->latest('id');

        if ($search) {
            $this->applyOptimizedSearch($query, $search);
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        if ($mrrFilter === 't10') {
            $query->whereNotIn('monthly_revenue', ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'])
                ->whereNotNull('monthly_revenue')
                ->where('monthly_revenue', '!=', '');
        } elseif ($mrrFilter === 't0') {
            $query->whereIn('monthly_revenue', ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k']);
        }

        LeadPlatform::apply($query, $platformFilter);
        LeadAudience::constrain($query, $audienceFilter);
        LeadSubmission::apply($query, $submissionFilter);

        $this->applyDateRange($query, $dateFilter);

        // Cache executive KPI metric counts for 3 minutes to avoid full table aggregate scans on every filter
        $metrics = Cache::remember(Lead::KPI_CACHE_KEY, 180, function () {
            return [
                'total' => Lead::count(),
                'booked' => Lead::where('status', 'booked')->count(),
                't10' => Lead::whereNotIn('monthly_revenue', ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'])
                    ->whereNotNull('monthly_revenue')
                    ->where('monthly_revenue', '!=', '')
                    ->count(),
                // `status` has no 'partial' — the drop-off lives in submission_type.
                'partial' => tap(Lead::query(), fn ($q) => LeadSubmission::apply($q, LeadSubmission::PARTIAL))->count(),
                'logs' => LeadActivityLog::count(),
            ];
        });

        $totalLeads = $metrics['total'];
        $bookedLeads = $metrics['booked'];
        $t10Leads = $metrics['t10'];
        $partialLeads = $metrics['partial'];
        $totalLogs = $metrics['logs'];

        $leads = $this->paginateScreen($query, 20, 'rl-leads', [
            's' => $search,
            'status' => $statusFilter,
            'mrr' => $mrrFilter,
            'date_range' => $dateFilter,
            'platform' => $platformFilter,
            'audience' => $audienceFilter,
            'submission' => $submissionFilter,
        ]);

        ?>
        <div class="wrap rl-admin-wrap">
            <?php if (isset($_GET['purged_count'])) { ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Retention Purge Complete:</strong> <?php echo esc_html($_GET['purged_count']); ?> leads older than 30 days were successfully purged.</p>
                </div>
            <?php } ?>

            <?php if (isset($_GET['lead_deleted'])) { ?>
                <div class="notice notice-info is-dismissible">
                    <p>Lead and its associated audit activity logs were permanently deleted.</p>
                </div>
            <?php } ?>

            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Lead Management & Submissions</h1>
                    <p class="rl-admin-subtitle">
                        Native Livewire lead ingestion, MRR tier routing, and dual-logging activity audit trail.
                    </p>
                </div>
                <div class="rl-actions-group">
                    <?php
                    $purgeUrl = wp_nonce_url(
                        admin_url('admin.php?page=rl-leads&rl_action=purge_leads'),
                        'rl_purge_leads_nonce',
                    );
        ?>
                    <?php app(LeadExportPanel::class)->renderTrigger(); ?>
                    <a href="<?php echo esc_url($purgeUrl); ?>" 
                       onclick="return confirm('Purge un-booked partial leads older than 30 days? This action cannot be undone.');" 
                       class="rl-btn rl-btn-destructive">
                        <?php echo $this->iconTrash(); ?> Run Retention Purge (30+ Days)
                    </a>
                </div>
            </div>

            <!-- Segmented Navigation Tabs -->
            <?php $this->renderAdminNavigation('leads'); ?>

            <!-- KPI Metrics Grid -->
            <div class="rl-stats-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-card <?php echo ! $statusFilter && ! $mrrFilter && ! $platformFilter && ! $audienceFilter && ! $submissionFilter ? 'rl-card-active' : ''; ?>">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Total Submissions</span>
                        <?php echo $this->iconUsers(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $totalLeads); ?></div>
                    <div class="rl-card-subtext">All captured records</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&status=booked')); ?>" class="rl-card <?php echo $statusFilter === 'booked' ? 'rl-card-active' : ''; ?>">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Booked Consultations</span>
                        <?php echo $this->iconCalendar(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $bookedLeads); ?></div>
                    <div class="rl-card-subtext">Calendar scheduled calls</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&mrr=t10')); ?>" class="rl-card <?php echo $mrrFilter === 't10' ? 'rl-card-active' : ''; ?>">
                    <div class="rl-card-header">
                        <span class="rl-card-title">High-Tier MRR (&ge;$10k)</span>
                        <?php echo $this->iconTrending(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $t10Leads); ?></div>
                    <div class="rl-card-subtext">&ge; $10k/mo revenue tier</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&submission=partial')); ?>" class="rl-card <?php echo $submissionFilter === LeadSubmission::PARTIAL ? 'rl-card-active' : ''; ?>">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Partial Form Drops</span>
                        <?php echo $this->iconAlert(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $partialLeads); ?></div>
                    <div class="rl-card-subtext">Incomplete step 1 drop-offs</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads-activity')); ?>" class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Audit Log Events</span>
                        <?php echo $this->iconShield(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $totalLogs); ?></div>
                    <div class="rl-card-subtext">Dual-logged audit trail</div>
                </a>
            </div>

            <!-- Filter Toolbar -->
            <div class="rl-filter-bar">
                <form method="get" action="" class="rl-filter-form">
                    <input type="hidden" name="page" value="rl-leads" />
                    
                    <div class="rl-search-wrapper">
                        <?php echo $this->iconSearch(); ?>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email, phone, UTM..." class="rl-input-search" />
                    </div>
                    
                    <select name="status" class="rl-select">
                        <option value="">All Statuses</option>
                        <?php foreach (LeadStatus::options() as $slug => $label) { ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($statusFilter, $slug); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="submission" class="rl-select">
                        <option value="">All Submissions</option>
                        <?php foreach (LeadSubmission::options() as $slug => $label) { ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($submissionFilter, $slug); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="mrr" class="rl-select">
                        <option value="">All Revenue Tiers</option>
                        <option value="t10" <?php selected($mrrFilter, 't10'); ?>>&ge; $10k/mo (Tier 10)</option>
                        <option value="t0" <?php selected($mrrFilter, 't0'); ?>>&lt; $10k/mo (Tier 0)</option>
                    </select>

                    <select name="platform" class="rl-select">
                        <option value="">All Platforms</option>
                        <?php foreach (LeadPlatform::options() as $slug => $label) { ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($platformFilter, $slug); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="audience" class="rl-select">
                        <option value="">Everyone</option>
                        <option value="clients" <?php selected($audienceFilter, 'clients'); ?>>Exclude possible VAs</option>
                        <option value="va" <?php selected($audienceFilter, 'va'); ?>>Only possible VAs</option>
                    </select>

                    <select name="date_range" class="rl-select">
                        <option value="">All Dates</option>
                        <option value="24h" <?php selected(in_array($dateFilter, ['24h', 'today'], true)); ?>>Last 24 hours</option>
                        <option value="week" <?php selected($dateFilter, 'week'); ?>>Last 7 Days</option>
                        <option value="month" <?php selected($dateFilter, 'month'); ?>>Last 30 Days</option>
                    </select>

                    <button type="submit" class="rl-btn rl-btn-primary">Filter</button>
                    <?php if ($search || $statusFilter || $mrrFilter || $dateFilter || $platformFilter || $audienceFilter || $submissionFilter) { ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-btn rl-btn-outline">Reset</a>
                    <?php } ?>
                </form>

                <div class="rl-count-badge">
                    Showing <strong><?php echo esc_html((string) count($leads->items())); ?></strong> of <strong><?php echo esc_html((string) $leads->total()); ?></strong> leads
                </div>
            </div>

            <!-- Leads Table -->
            <div class="rl-table-container">
                <table class="rl-lead-table">
                    <thead>
                        <tr>
                            <th>Date / Age</th>
                            <th>Contact & Company</th>
                            <th>Revenue (MRR)</th>
                            <th>Status</th>
                            <th>Scheduled Consultation</th>
                            <th class="rl-col-attr">Attribution</th>
                            <th>Audit Logs</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leads->isEmpty()) { ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 48px; color: #a1a1aa;">
                                    No submissions found matching your filters.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($leads as $lead) {
                                $meeting = $this->extractMeetingDetails($lead);
                                $isT10 = ! in_array($lead->monthly_revenue, ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'], true) && ! empty($lead->monthly_revenue);
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #09090b;"><?php echo esc_html($lead->created_at?->format('M j, Y')); ?></div>
                                        <div style="font-size: 11px; color: #71717a;" title="<?php echo esc_attr($lead->created_at?->toDateTimeString()); ?>">
                                            <?php echo esc_html($lead->created_at?->diffForHumans()); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rl-contact-cell">
                                            <?php $this->renderAvatar($lead->name, $lead->email); ?>
                                            <div class="rl-contact-info">
                                                <span class="rl-contact-name">
                                                    <?php echo esc_html($lead->name ?: 'Partial Contact'); ?>
                                                    <?php $audience = $lead->audience(); ?>
                                                    <?php if ($audience->label()) { ?>
                                                        <span class="rl-badge rl-badge-va" title="<?php echo esc_attr((string) $audience->note()); ?>"><?php echo esc_html($audience->label()); ?></span>
                                                    <?php } ?>
                                                </span>
                                                <a href="mailto:<?php echo esc_attr($lead->email); ?>" class="rl-contact-email" title="<?php echo esc_attr($lead->email); ?>"><?php echo esc_html($lead->email); ?></a>
                                                <div class="rl-contact-sub">
                                                    <?php if ($lead->phone) { ?>
                                                        <a href="tel:<?php echo esc_attr($lead->phone); ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;">
                                                            <?php echo $this->iconPhone(); ?> <?php echo esc_html($lead->phone); ?>
                                                        </a>
                                                    <?php } ?>
                                                    <?php if ($lead->company) { ?>
                                                        <span class="rl-contact-company">
                                                            <?php echo $this->iconBuilding(); ?> <?php echo esc_html($lead->company); ?>
                                                        </span>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($lead->monthly_revenue) { ?>
                                            <span class="<?php echo $isT10 ? 'rl-pill-t10' : 'rl-pill-t0'; ?>">
                                                <?php echo esc_html($lead->monthly_revenue); ?> &bull; <?php echo $isT10 ? 'T10' : 'T0'; ?>
                                            </span>
                                        <?php } else { ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">Not specified</span>
                                        <?php } ?>
                                        <?php if ($lead->role_needed) { ?>
                                            <div style="font-size: 11px; color: #71717a; margin-top: 3px;">Role: <?php echo esc_html($lead->role_needed); ?></div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="rl-badge <?php echo esc_attr(LeadStatus::badgeClass($lead->status)); ?>">
                                            <span class="rl-status-dot"></span>
                                            <?php echo esc_html(LeadStatus::label($lead->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (! empty($meeting['meet_url'])) { ?>
                                            <a href="<?php echo esc_url($meeting['meet_url']); ?>" target="_blank" class="rl-meet-btn">
                                                <?php echo $this->iconVideo(); ?> Google Meet
                                            </a>
                                        <?php } elseif ($lead->status === 'booked') { ?>
                                            <span class="rl-badge">
                                                <span class="rl-status-dot"></span>
                                                <?php echo $this->iconCheck(); ?> Confirmed
                                            </span>
                                        <?php } else { ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">Not Scheduled</span>
                                        <?php } ?>
                                    </td>
                                    <td class="rl-col-attr">
                                        <div class="rl-attr-cell">
                                            <?php if ($lead->utm_source || $lead->utm_campaign) { ?>
                                                <span class="rl-attr-source" title="<?php echo esc_attr($lead->utm_source ?: 'direct'); ?>"><?php echo esc_html($lead->utm_source ?: 'direct'); ?></span>
                                                <?php if ($lead->utm_campaign) { ?>
                                                    <span class="rl-attr-campaign" title="<?php echo esc_attr($lead->utm_campaign); ?>">cmp: <?php echo esc_html($lead->utm_campaign); ?></span>
                                                <?php } ?>
                                            <?php } elseif ($lead->referral_code) { ?>
                                                <span class="rl-badge" title="<?php echo esc_attr($lead->referral_code); ?>">via: <?php echo esc_html($lead->referral_code); ?></span>
                                            <?php } else { ?>
                                                <span style="color: #a1a1aa; font-size: 11px;">Direct Organic</span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)); ?>" class="rl-audit-pill">
                                            <?php echo esc_html((string) $lead->activityLogs()->count()); ?> events <?php echo $this->iconArrowRight(); ?>
                                        </a>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)); ?>" class="rl-btn rl-btn-outline rl-btn-sm">
                                            View Details <?php echo $this->iconArrowRight(); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #71717a;">
                    Page <strong><?php echo esc_html((string) $leads->currentPage()); ?></strong> of <strong><?php echo esc_html((string) $leads->lastPage()); ?></strong>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($leads->previousPageUrl()) { ?>
                        <a href="<?php echo esc_url($leads->previousPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm"><?php echo $this->iconArrowLeft(); ?> Previous</a>
                    <?php } ?>
                    <?php if ($leads->nextPageUrl()) { ?>
                        <a href="<?php echo esc_url($leads->nextPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm">Next <?php echo $this->iconArrowRight(); ?></a>
                    <?php } ?>
                </div>
            </div>

            <?php
                /*
                 * The export modal ships with the list, inside the wrap, so its markup is on the
                 * page before anyone presses the button. It carries the toolbar's search term,
                 * because an export started from a filtered list should cover what the list was
                 * showing — the term is resolved through the same LeadSearch the screen uses.
                 */
                app(LeadExportPanel::class)->renderModal($search, $statusFilter, $platformFilter, $audienceFilter, $submissionFilter);
        ?>
        </div>
        <?php
    }

    public function renderLeadDetail(int $leadId): void
    {
        $lead = Lead::with(['activityLogs' => fn ($q) => $q->orderBy('created_at', 'asc')])->find($leadId);

        if (! $lead) {
            echo '<div class="wrap rl-admin-wrap"><div class="notice notice-error"><p>Lead record not found.</p></div></div>';

            return;
        }

        $meeting = $this->extractMeetingDetails($lead);
        $deleteUrl = wp_nonce_url(
            admin_url('admin.php?page=rl-leads&rl_action=delete_lead&lead_id='.$lead->id),
            'rl_delete_lead_nonce',
        );

        ?>
        <div class="wrap rl-admin-wrap">
            <div style="margin-bottom: 16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-btn rl-btn-outline rl-btn-sm">
                    <?php echo $this->iconArrowLeft(); ?> Back to all submissions
                </a>
            </div>

            <!-- Segmented Navigation Tabs -->
            <?php $this->renderAdminNavigation('leads'); ?>

            <?php if (isset($_GET['status_updated'])) { ?>
                <div class="notice notice-success is-dismissible">
                    <p>Lead status successfully updated.</p>
                </div>
            <?php } ?>

            <!-- Lead Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <?php $this->renderAvatar($lead->name, $lead->email, 48); ?>
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <h1 class="rl-admin-title" style="margin: 0;">
                                <?php echo esc_html($lead->name ?: 'Lead #'.$lead->id); ?>
                            </h1>
                            <span class="rl-badge <?php echo esc_attr(LeadStatus::badgeClass($lead->status)); ?>">
                                <span class="rl-status-dot"></span>
                                <?php echo esc_html(LeadStatus::label($lead->status)); ?>
                            </span>
                            <?php $audience = $lead->audience(); ?>
                            <?php if ($audience->label()) { ?>
                                <span class="rl-badge rl-badge-va" title="<?php echo esc_attr((string) $audience->note()); ?>"><?php echo esc_html($audience->label()); ?></span>
                            <?php } ?>
                        </div>
                        <div style="color: #71717a; font-size: 13px; margin-top: 6px;">
                            Captured: <strong><?php echo esc_html($lead->created_at?->format('F j, Y \a\t g:i:s A')); ?></strong> &bull; 
                            UUID: <code style="background: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 4px; padding: 2px 6px; font-size: 11px;"><?php echo esc_html($lead->uuid); ?></code>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Status Updater -->
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" style="display: flex; gap: 6px; align-items: center;">
                        <?php wp_nonce_field('rl_update_status_nonce'); ?>
                        <input type="hidden" name="rl_action" value="update_status" />
                        <input type="hidden" name="lead_id" value="<?php echo esc_attr((string) $lead->id); ?>" />
                        <select name="new_status" class="rl-select" style="font-weight: 500;">
                            <?php foreach (LeadStatus::options() as $slug => $label) { ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($lead->status, $slug); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <button type="submit" class="rl-btn rl-btn-primary">Update Status</button>
                    </form>

                    <a href="<?php echo esc_url($deleteUrl); ?>" 
                       onclick="return confirm('Delete this lead and all its audit activity logs?');" 
                       class="rl-btn rl-btn-destructive">
                        <?php echo $this->iconTrash(); ?> Delete Lead
                    </a>
                </div>
            </div>

            <!-- Detail Grid -->
            <div class="rl-detail-grid">
                <!-- Left Column: Session Replay, Contact, Qualification, Meeting & Attribution -->
                <div>
                    <!-- Session Replay -->
                    <?php
                        /*
                         * Always offer a way into PostHog, even without a session id.
                         *
                         * The card used to render only when the lead carried `posthog_session_id`,
                         * which is the minority of leads — the browser stamp does not survive a
                         * blocked or slow PostHog. A missing card reads as "PostHog has nothing on
                         * this person", which is a different and usually wrong claim. The email
                         * search is the honest fallback: it cannot 404, and it says plainly that
                         * the exact session is what we are missing.
                         */
                        $replayUrl = $lead->posthogReplayUrl();
        $personUrl = $lead->posthogPersonUrl();
        ?>
                    <?php if ($replayUrl || $personUrl) { ?>
                        <div class="rl-detail-card">
                            <h3 class="rl-detail-title">Session Replay</h3>
                            <?php if ($replayUrl) { ?>
                                <p style="margin: 0 0 10px; color: #52525b; font-size: 12px;">
                                    Watch what this person actually did — which step they stalled on, what they
                                    re-read, where they left.
                                </p>
                                <a href="<?php echo esc_url($replayUrl); ?>" target="_blank" rel="noopener noreferrer"
                                   class="button button-secondary">Open in PostHog &rarr;</a>
                            <?php } else { ?>
                                <p style="margin: 0 0 10px; color: #52525b; font-size: 12px;">
                                    This lead reached us without a PostHog session id, so there is no direct
                                    replay link — PostHog was blocked, or had not loaded when the form was
                                    submitted. Search by email for whatever it did record.
                                </p>
                                <a href="<?php echo esc_url($personUrl); ?>" target="_blank" rel="noopener noreferrer"
                                   class="button button-secondary">Find in PostHog &rarr;</a>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <!-- Contact Details Card -->
                    <div class="rl-detail-card">
                        <h3 class="rl-detail-title">Contact & Qualification</h3>
                        <table class="rl-key-value-table">
                            <tr><td>Email:</td><td><strong><a href="mailto:<?php echo esc_attr($lead->email); ?>" class="rl-contact-email"><?php echo esc_html($lead->email); ?></a></strong></td></tr>
                            <tr><td>Phone:</td><td><?php echo $lead->phone ? '<a href="tel:'.esc_attr($lead->phone).'" style="color:inherit; text-decoration:none;">'.esc_html($lead->phone).'</a>' : '—'; ?></td></tr>
                            <tr><td>Company:</td><td><strong><?php echo esc_html($lead->company ?: '—'); ?></strong></td></tr>
                            <tr><td>Monthly Revenue:</td><td><strong style="color: #6b21a8;"><?php echo esc_html($lead->monthly_revenue ?: '—'); ?></strong></td></tr>
                            <tr><td>Role Needed:</td><td><?php echo esc_html($lead->role_needed ?: '—'); ?></td></tr>
                            <tr><td>Weekly Hours:</td><td><?php echo esc_html($lead->weekly_hours ?: '—'); ?></td></tr>
                            <tr><td>Start Timeline:</td><td><?php echo esc_html($lead->start_date ?: '—'); ?></td></tr>
                            <?php if ($lead->notes) { ?>
                                <tr><td>Notes:</td><td><?php echo nl2br(esc_html($lead->notes)); ?></td></tr>
                            <?php } ?>
                        </table>
                    </div>

                    <!-- Consultation / Video Meeting Card -->
                    <div class="rl-detail-card">
                        <h3 class="rl-detail-title">Consultation & Video Meeting</h3>
                        <?php if (! empty($meeting['meet_url'])) { ?>
                            <div style="margin-bottom: 14px;">
                                <a href="<?php echo esc_url($meeting['meet_url']); ?>" target="_blank" class="rl-meet-btn" style="padding: 7px 14px; font-size: 13px;">
                                    <?php echo $this->iconVideo(); ?> Join Google Meet Room
                                </a>
                            </div>
                        <?php } ?>
                        <table class="rl-key-value-table">
                            <tr><td>Meeting Status:</td><td><span class="rl-badge <?php echo esc_attr(LeadStatus::badgeClass($lead->status)); ?>"><span class="rl-status-dot"></span><?php echo esc_html(LeadStatus::label($lead->status)); ?></span></td></tr>
                            <?php if (! empty($meeting['meeting_id'])) { ?>
                                <tr><td>Meeting ID:</td><td><code><?php echo esc_html($meeting['meeting_id']); ?></code></td></tr>
                            <?php } ?>
                            <?php if (! empty($meeting['provider'])) { ?>
                                <tr><td>Provider:</td><td><?php echo esc_html(strtoupper($meeting['provider'])); ?></td></tr>
                            <?php } ?>
                        </table>
                    </div>

                    <!-- Identity and blocking -->
                    <?php
                    $profile = $lead->profile_id
                        ? LeadProfile::query()->find($lead->profile_id)
                        : null;
        $isBlocked = (bool) $lead->is_blocked;
        ?>
                    <div class="rl-detail-card" style="<?php echo $isBlocked ? 'border-left: 3px solid #b91c1c;' : ''; ?>">
                        <h3 class="rl-detail-title">Identity<?php echo $isBlocked ? ' — BLOCKED' : ''; ?></h3>

                        <?php if ($profile) { ?>
                            <p style="margin: 0 0 10px; color: #52525b; font-size: 12px;">
                                Blocking applies to the <strong>person</strong>, not this submission: every
                                email, phone and device already linked to them is blocked, and so is any
                                identifier seen alongside those later. They are not told — the form keeps
                                working, but nothing reaches Slack, HubSpot or the calendar.
                            </p>

                            <table class="rl-key-value-table">
                                <tr><td>Profile:</td><td><code><?php echo esc_html(substr((string) $profile->uuid, 0, 8)); ?></code></td></tr>
                                <tr><td>Leads:</td><td><?php echo (int) $profile->lead_count; ?></td></tr>
                                <?php foreach ($profile->identifiers as $identifier) { ?>
                                    <tr>
                                        <td><?php echo esc_html(ucfirst((string) $identifier->type)); ?>:</td>
                                        <td>
                                            <code style="font-size: 11px;"><?php echo esc_html((string) $identifier->value_preview); ?></code>
                                            <?php if ($identifier->strength === 'weak') { ?>
                                                <span style="color: #a1a1aa; font-size: 10px;"> evidence only, never merges</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <?php if ($isBlocked) { ?>
                                    <tr><td>Blocked by:</td><td><?php echo esc_html((string) $profile->blocked_by); ?></td></tr>
                                    <tr><td>Reason:</td><td><?php echo esc_html((string) $profile->block_reason); ?></td></tr>
                                <?php } ?>
                            </table>

                            <form method="post" style="margin-top: 12px;"
                                  onsubmit="return confirm('<?php echo $isBlocked ? 'Unblock this person everywhere?' : 'Block this person across every identifier they have used?'; ?>');">
                                <?php wp_nonce_field('rl_block_lead_nonce'); ?>
                                <input type="hidden" name="rl_action" value="<?php echo $isBlocked ? 'unblock_lead_profile' : 'block_lead_profile'; ?>" />
                                <input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
                                <?php if (! $isBlocked) { ?>
                                    <input type="text" name="block_reason" class="regular-text" placeholder="Reason (recorded against the profile)" style="margin-bottom: 8px; width: 100%;" />
                                <?php } ?>
                                <button type="submit" class="button <?php echo $isBlocked ? 'button-secondary' : 'button-link-delete'; ?>">
                                    <?php echo $isBlocked ? 'Unblock this person' : 'Block this person'; ?>
                                </button>
                            </form>
                        <?php } else { ?>
                            <p style="margin: 0; color: #52525b; font-size: 12px;">
                                No identity profile yet — this lead carried nothing identifiable, or predates
                                identity resolution.
                            </p>
                        <?php } ?>
                    </div>

                    <!-- Everything else the visit carried -->
                    <?php
        $rawAttribution = is_array($lead->attribution) ? $lead->attribution : [];
        if ($rawAttribution !== []) { ?>
                        <div class="rl-detail-card">
                            <h3 class="rl-detail-title">Full Capture</h3>
                            <p style="margin: 0 0 10px; color: #52525b; font-size: 12px;">
                                Everything else the visit carried — the HandL first-touch set, and any URL
                                parameter without a column of its own. Shown in full deliberately: a parameter
                                nobody has mapped yet is exactly the one worth seeing here.
                            </p>
                            <table class="rl-key-value-table">
                                <?php foreach ($rawAttribution as $group => $values) { ?>
                                    <?php if (is_array($values)) { ?>
                                        <tr><td colspan="2" style="padding-top: 8px; font-weight: 600; color: #09090b;">
                                            <?php echo esc_html($group === 'unmapped' ? 'Unmapped parameters' : ucfirst((string) $group)); ?>
                                        </td></tr>
                                        <?php foreach ($values as $key => $value) { ?>
                                            <tr>
                                                <td style="font-size: 11px;"><?php echo esc_html((string) $key); ?>:</td>
                                                <td style="word-break: break-all; font-size: 11px;"><code><?php echo esc_html($this->attributionScalar($value)); ?></code></td>
                                            </tr>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <tr>
                                            <td style="font-size: 11px;"><?php echo esc_html((string) $group); ?>:</td>
                                            <td style="word-break: break-all; font-size: 11px;"><code><?php echo esc_html($this->attributionScalar($values)); ?></code></td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </table>
                        </div>
                    <?php } ?>

                    <!-- Attribution & UTM Data Card -->
                    <div class="rl-detail-card">
                        <h3 class="rl-detail-title">Attribution & UTM Data</h3>
                        <table class="rl-key-value-table">
                            <tr><td>Source:</td><td><strong><?php echo esc_html($lead->utm_source ?: '—'); ?></strong></td></tr>
                            <tr><td>Medium:</td><td><?php echo esc_html($lead->utm_medium ?: '—'); ?></td></tr>
                            <tr><td>Campaign:</td><td><?php echo esc_html($lead->utm_campaign ?: '—'); ?></td></tr>
                            <tr><td>Term / Content:</td><td><?php echo esc_html(trim(($lead->utm_term ?: '').' '.($lead->utm_content ?: '')) ?: '—'); ?></td></tr>
                            <tr><td>Source Type:</td><td><span class="rl-badge" style="background:#f4f4f5; color:#52525b;"><?php echo esc_html($lead->source_type ?: 'organic'); ?></span></td></tr>
                            <?php if ($lead->referral_code) { ?>
                                <tr><td>Referral Code:</td><td><code><?php echo esc_html($lead->referral_code); ?></code></td></tr>
                            <?php } ?>
                            <?php if ($lead->gclid) { ?>
                                <tr><td>Google Click ID:</td><td><?php echo $this->renderLongToken($lead->gclid); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->fbclid) { ?>
                                <tr><td>Facebook Click ID:</td><td><?php echo $this->renderLongToken($lead->fbclid); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->utm_id) { ?>
                                <tr><td>UTM ID:</td><td><code><?php echo esc_html($lead->utm_id); ?></code></td></tr>
                            <?php } ?>
                            <?php if ($lead->li_fat_id) { ?>
                                <tr><td>LinkedIn Click ID:</td><td><?php echo $this->renderLongToken($lead->li_fat_id); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->fbc) { ?>
                                <tr><td>Meta _fbc:</td><td><?php echo $this->renderLongToken($lead->fbc); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->partner) { ?>
                                <tr><td>Partner:</td><td><?php echo esc_html($lead->partner); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->oppref) { ?>
                                <tr><td>Opp Ref:</td><td><code><?php echo esc_html($lead->oppref); ?></code></td></tr>
                            <?php } ?>
                            <?php if ($lead->data_source) { ?>
                                <tr><td>Data Source:</td><td><?php echo esc_html($lead->data_source); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->submission_type) { ?>
                                <tr><td>Submission:</td><td><?php echo esc_html($lead->submission_type); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->ip_address) { ?>
                                <tr><td>IP Address:</td><td><code><?php echo esc_html($lead->ip_address); ?></code></td></tr>
                            <?php } ?>
                            <?php if ($lead->landing_url) { ?>
                                <tr><td>Landing URL:</td><td style="word-break: break-all; font-size: 11px;"><?php echo esc_html($lead->landing_url); ?></td></tr>
                            <?php } ?>
                            <?php if ($lead->scheduler_link) { ?>
                                <tr><td>Scheduler Link:</td><td style="word-break: break-all; font-size: 11px;"><?php echo esc_html($lead->scheduler_link); ?></td></tr>
                            <?php } ?>
                        </table>
                    </div>
                </div>

                <!-- Right Column: Dual-Logging Execution & Integration Timeline -->
                <div class="rl-timeline-col">
                    <div class="rl-detail-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f4f4f5; padding-bottom: 10px;">
                            <div>
                                <h3 class="rl-detail-title" style="margin: 0; border: none; padding: 0;">
                                    Execution & Audit Timeline
                                </h3>
                                <div style="font-size: 12px; color: #71717a; margin-top: 2px;">
                                    Dual-logging contract: Stage 1 (Dispatch) &bull; Stage 2 (Consumption)
                                </div>
                            </div>
                            <span class="rl-count-badge">
                                <?php
                                        /*
                                         * Counted off the same guarded load the timeline below uses, so a
                                         * source that could not be read cannot report a count it did not fetch.
                                         */
                                        $eventCount = $this->timelineSource($lead, 'activityLogs')->count()
                                            + $this->timelineSource($lead, 'integrationCalls')->count();
        ?>
                                <?php echo esc_html((string) $eventCount); ?> events
                            </span>
                        </div>

                        <?php $timeline = $this->mergedTimeline($lead); ?>

                        <?php if (($missingSources = $this->unavailableTimelineSources($lead)) !== []) { ?>
                            <div class="notice notice-warning inline" style="margin: 0 0 14px; padding: 8px 12px;">
                                <p style="margin: 0; font-size: 12px;">
                                    <strong>Timeline incomplete.</strong>
                                    The <?php echo esc_html(implode(' and the ', $missingSources)); ?>
                                    could not be read, so events from
                                    <?php echo count($missingSources) === 1 ? 'it are' : 'them are'; ?>
                                    missing below. This is usually a pending database migration —
                                    run <code>wp acorn migrate</code>. The reason is in the error log.
                                </p>
                            </div>
                        <?php } ?>

                        <?php if ($timeline === []) { ?>
                            <?php
        /*
         * "Nothing happened" and "we could not look" are different answers, and
         * only one of them is safe to act on. When every source failed there is
         * no empty state to report — the notice above is the whole message.
         */
                            ?>
                            <?php if (count($missingSources) < count(self::TIMELINE_SOURCE_LABELS)) { ?>
                                <p style="color: #a1a1aa; font-size: 13px;">
                                    <?php echo $missingSources === []
                                        ? 'No activity logged yet for this lead.'
                                        : 'Nothing logged in the sources that could be read.'; ?>
                                </p>
                            <?php } ?>
                        <?php } else { ?>
                            <?php
                                /*
                                 * The icon sprite, printed once. Not escaped because it is static
                                 * author-controlled SVG from IntegrationIcons, and escaping it
                                 * would render the markup as text.
                                 */
                                echo IntegrationIcons::sprite();
                            ?>
                            <div class="rl-timeline">
                                <?php foreach ($timeline as $entry) { ?>
                                    <?php $row = $entry['model']; ?>
                                    <div class="rl-timeline-item">
                                        <div class="rl-timeline-dot dot-<?php echo esc_attr($entry['outcome']); ?>"></div>
                                        <div class="rl-timeline-content">
                                            <div class="rl-timeline-meta">
                                                <?php echo IntegrationIcons::icon($entry['icon_key'], $entry['label']); ?>
                                                <span style="font-weight: 600; color: #09090b; font-size: 12px;"><?php echo esc_html($entry['label']); ?></span>

                                                <?php if ($entry['type'] === 'log') { ?>
                                                    <span class="rl-badge rl-badge-<?php echo esc_attr($row->stage); ?>"><?php echo esc_html(strtoupper($row->stage)); ?></span>
                                                <?php } else { ?>
                                                    <span class="rl-badge rl-badge-consumption">HTTP</span>
                                                    <?php if ($row->status_code) { ?>
                                                        <span class="rl-badge rl-badge-<?php echo esc_attr($entry['outcome']); ?>"><?php echo esc_html((string) $row->status_code); ?></span>
                                                    <?php } ?>
                                                <?php } ?>

                                                <span class="rl-badge rl-badge-<?php echo esc_attr($entry['outcome']); ?>"><span class="rl-status-dot"></span><?php echo esc_html($entry['outcome']); ?></span>

                                                <?php if ($entry['type'] === 'call' && $row->duration_ms !== null) { ?>
                                                    <span style="color: #a1a1aa; font-size: 11px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;"><?php echo esc_html((string) $row->duration_ms); ?>ms</span>
                                                <?php } ?>

                                                <span style="color: #a1a1aa; margin-left: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px;">
                                                    <?php echo esc_html($entry['at']?->format('H:i:s')); ?> (<?php echo esc_html($entry['at']?->diffForHumans()); ?>)
                                                </span>
                                            </div>

                                            <div style="font-size: 13px; font-weight: 500; color: #09090b; margin-bottom: 4px;">
                                                <?php echo esc_html($entry['description']); ?>
                                            </div>

                                            <?php if ($entry['type'] === 'log') { ?>
                                                <?php if (! empty($row->payload)) { ?>
                                                    <details style="margin-top: 6px;">
                                                        <summary style="font-size: 11px; color: #71717a; cursor: pointer; font-weight: 500;">View Event Payload JSON</summary>
                                                        <div class="rl-json-box"><?php echo esc_html(json_encode($row->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                                    </details>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #71717a; margin-bottom: 6px; word-break: break-all;">
                                                    <?php echo esc_html($row->url); ?>
                                                </div>

                                                <?php if ($row->credential_label) { ?>
                                                    <div style="font-size: 11px; color: #71717a; margin-bottom: 6px;">
                                                        as <strong style="color: #3f3f46;"><?php echo esc_html($row->credential_label); ?></strong>
                                                    </div>
                                                <?php } ?>

                                                <?php if ($row->error_message) { ?>
                                                    <div style="font-size: 12px; color: #b91c1c; margin-bottom: 6px;">
                                                        <?php echo esc_html($row->error_message); ?>
                                                    </div>
                                                <?php } ?>

                                                <details style="margin-top: 6px;">
                                                    <summary style="font-size: 11px; color: #71717a; cursor: pointer; font-weight: 500;">View request</summary>
                                                    <div class="rl-json-box"><?php echo esc_html(json_encode($row->request_headers ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                                    <?php if ($row->request_body) { ?>
                                                        <div class="rl-json-box"><?php echo esc_html($row->prettyBody($row->request_body)); ?></div>
                                                    <?php } ?>
                                                </details>

                                                <details style="margin-top: 6px;">
                                                    <summary style="font-size: 11px; color: #71717a; cursor: pointer; font-weight: 500;">View response</summary>
                                                    <div class="rl-json-box"><?php echo esc_html(json_encode($row->response_headers ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                                    <?php if ($row->response_body) { ?>
                                                        <div class="rl-json-box"><?php echo esc_html($row->prettyBody($row->response_body)); ?></div>
                                                    <?php } ?>
                                                </details>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderActivityLogs(): void
    {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $stageFilter = sanitize_text_field($_GET['stage'] ?? '');
        $domainFilter = sanitize_text_field($_GET['domain'] ?? '');
        $outcomeFilter = sanitize_text_field($_GET['outcome'] ?? '');

        $query = LeadActivityLog::with('lead')->latest('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('event_type', 'LIKE', "%{$search}%")
                    ->orWhereHas('lead', function ($lq) use ($search) {
                        $lq->where('email', 'LIKE', "%{$search}%")
                            ->orWhere('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($stageFilter) {
            $query->where('stage', $stageFilter);
        }

        if ($domainFilter) {
            $query->where('actor_domain', $domainFilter);
        }

        if ($outcomeFilter) {
            $query->where('outcome', $outcomeFilter);
        }

        $logs = $this->paginateScreen($query, 30, 'rl-leads-activity', [
            's' => $search,
            'stage' => $stageFilter,
            'domain' => $domainFilter,
            'outcome' => $outcomeFilter,
        ]);

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Live Activity & Audit Logs</h1>
                    <p class="rl-admin-subtitle">
                        Real-time audit trail of all Lead Domain events: Stage 1 (Dispatch) & Stage 2 (Consumption by Tracking, CRM, Slack).
                    </p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-btn rl-btn-outline">
                    <?php echo $this->iconArrowLeft(); ?> Back to All Leads
                </a>
            </div>

            <!-- Segmented Navigation Tabs -->
            <?php $this->renderAdminNavigation('activity'); ?>

            <!-- Filter Toolbar -->
            <div class="rl-filter-bar">
                <form method="get" action="" class="rl-filter-form">
                    <input type="hidden" name="page" value="rl-leads-activity" />
                    
                    <div class="rl-search-wrapper">
                        <?php echo $this->iconSearch(); ?>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search description, email..." class="rl-input-search" />
                    </div>

                    <select name="stage" class="rl-select">
                        <option value="">All Stages</option>
                        <option value="dispatch" <?php selected($stageFilter, 'dispatch'); ?>>Stage 1: Dispatch</option>
                        <option value="consumption" <?php selected($stageFilter, 'consumption'); ?>>Stage 2: Consumption</option>
                        <option value="admin_action" <?php selected($stageFilter, 'admin_action'); ?>>Admin Action</option>
                    </select>

                    <select name="domain" class="rl-select">
                        <option value="">All Domains</option>
                        <?php
                            /*
                             * Read off the table rather than hardcoded. The fixed list had drifted to
                             * three of the seven actors actually being written — Slack, OutgoingWebhook,
                             * Referral and EmailNotification were all unfilterable — and a hardcoded
                             * list silently loses each new actor the day it starts logging.
                             */
                            $domains = LeadActivityLog::query()
                                ->select('actor_domain')
                                ->distinct()
                                ->orderBy('actor_domain')
                                ->pluck('actor_domain')
                                ->filter()
                                ->all();
        ?>
                        <?php foreach ($domains as $domain) { ?>
                            <option value="<?php echo esc_attr($domain); ?>" <?php selected($domainFilter, $domain); ?>>
                                <?php echo esc_html($domain); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <select name="outcome" class="rl-select">
                        <option value="">All Outcomes</option>
                        <option value="succeeded" <?php selected($outcomeFilter, 'succeeded'); ?>>Succeeded</option>
                        <option value="failed" <?php selected($outcomeFilter, 'failed'); ?>>Failed</option>
                    </select>

                    <button type="submit" class="rl-btn rl-btn-primary">Filter Logs</button>
                    <?php if ($search || $stageFilter || $domainFilter || $outcomeFilter) { ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads-activity')); ?>" class="rl-btn rl-btn-outline">Reset</a>
                    <?php } ?>
                </form>

                <div class="rl-count-badge">
                    Total: <strong><?php echo esc_html((string) $logs->total()); ?></strong> audit events
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="rl-table-container">
                <table class="rl-lead-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Lead Contact</th>
                            <th>Stage</th>
                            <th>Domain</th>
                            <th>Outcome</th>
                            <th>Action / Description</th>
                            <th>Payload</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->isEmpty()) { ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 48px; color: #a1a1aa;">
                                    No audit logs found matching criteria.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($logs as $log) { ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <div style="font-weight: 600; color: #09090b;"><?php echo esc_html($log->created_at?->format('M j, Y')); ?></div>
                                        <div style="font-size: 11px; color: #71717a;" title="<?php echo esc_attr($log->created_at?->toDateTimeString()); ?>">
                                            <?php echo esc_html($log->created_at?->format('H:i:s')); ?> (<?php echo esc_html($log->created_at?->diffForHumans()); ?>)
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log->lead) { ?>
                                            <div class="rl-contact-cell">
                                                <?php $this->renderAvatar($log->lead->name, $log->lead->email); ?>
                                                <div class="rl-contact-info">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&view_lead='.$log->lead_id)); ?>" class="rl-contact-name" style="text-decoration: none;">
                                                        <?php echo esc_html($log->lead->name ?: $log->lead->email); ?>
                                                    </a>
                                                    <span style="font-size: 11px; color: #71717a;"><?php echo esc_html($log->lead->email); ?></span>
                                                </div>
                                            </div>
                                        <?php } else { ?>
                                            <span style="color: #a1a1aa; font-size: 12px;">Lead #<?php echo esc_html((string) $log->lead_id); ?> (deleted)</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($log->stage); ?>">
                                            <?php echo esc_html(strtoupper($log->stage)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($log->actor_domain); ?></strong>
                                    </td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($log->outcome); ?>">
                                            <span class="rl-status-dot"></span>
                                            <?php echo esc_html($log->outcome); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 350px;">
                                        <div style="font-weight: 500; color: #09090b;"><?php echo esc_html($log->description); ?></div>
                                        <div style="font-size: 11px; color: #71717a; margin-top: 2px;">Event: <code><?php echo esc_html($log->event_type); ?></code></div>
                                    </td>
                                    <td>
                                        <?php if (! empty($log->payload)) { ?>
                                            <details>
                                                <summary style="font-size: 11px; color: #71717a; cursor: pointer; font-weight: 500;">JSON</summary>
                                                <div class="rl-json-box"><?php echo esc_html(json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                            </details>
                                        <?php } else { ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">—</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #71717a;">
                    Page <strong><?php echo esc_html((string) $logs->currentPage()); ?></strong> of <strong><?php echo esc_html((string) $logs->lastPage()); ?></strong>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($logs->previousPageUrl()) { ?>
                        <a href="<?php echo esc_url($logs->previousPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm"><?php echo $this->iconArrowLeft(); ?> Previous</a>
                    <?php } ?>
                    <?php if ($logs->nextPageUrl()) { ?>
                        <a href="<?php echo esc_url($logs->nextPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm">Next <?php echo $this->iconArrowRight(); ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderDiagnostics(): void
    {
        $tokenPool = app(CalendlyTokenPool::class);
        $calendlyClient = app(CalendlyClient::class);
        $roleResolver = app(CalendlyEventTypeRoleResolver::class);

        $hasTokens = ! empty($tokenPool->getEligibleTokens());
        $t10 = $roleResolver->get('t10');
        $t0 = $roleResolver->get('t0');

        $t10Status = 'Unknown';
        $t10Name = '';
        $t0Status = 'Unknown';
        $t0Name = '';

        if ($hasTokens) {
            try {
                $r1 = $t10 ? $calendlyClient->getEventType($t10) : null;
                $t10Status = $r1 ? 'Active (200 OK)' : 'Unreachable across pool';
                $t10Name = $r1['name'] ?? '';

                $r2 = $t0 ? $calendlyClient->getEventType($t0) : null;
                $t0Status = $r2 ? 'Active (200 OK)' : 'Unreachable across pool';
                $t0Name = $r2['name'] ?? '';
            } catch (\Throwable $e) {
                $t10Status = 'Connection error: '.$e->getMessage();
            }
        }

        $cutoff = Carbon::now()->subDays(30);
        $staleLeadsCount = Lead::where('created_at', '<', $cutoff)->count();

        $stuckLeads = Lead::where('status', 'booking_failed')
            ->orWhere(function (Builder $query) {
                $query->where('status', 'booking_pending')->where('booking_retry_count', '>', 0);
            })
            ->orderByDesc('booking_retry_count')
            ->get();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">System Diagnostics & Health</h1>
                    <p class="rl-admin-subtitle">
                        Live integration status for Calendly Direct API and automated data retention compliance.
                    </p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-btn rl-btn-outline">
                    <?php echo $this->iconArrowLeft(); ?> Back to All Leads
                </a>
            </div>

            <?php if (! empty($_GET['retry_result'])) { ?>
                <div class="notice <?php echo $_GET['retry_result'] === 'success' ? 'notice-success' : 'notice-error'; ?>">
                    <p>Booking retry <?php echo $_GET['retry_result'] === 'success' ? 'succeeded' : 'failed'; ?> for lead #<?php echo esc_html((string) absint($_GET['lead_id'] ?? 0)); ?>.</p>
                </div>
            <?php } ?>

            <!-- Segmented Navigation Tabs -->
            <?php $this->renderAdminNavigation('diagnostics'); ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Calendly Connection -->
                <div class="rl-detail-card">
                    <h3 class="rl-detail-title">Calendly Direct API Connection</h3>
                    <table class="rl-key-value-table">
                        <tr>
                            <td style="width: 170px;">Token Pool:</td>
                            <td>
                                <?php if ($hasTokens) { ?>
                                    <span class="rl-badge rl-badge-succeeded"><span class="rl-status-dot"></span>Configured (<?php echo esc_html((string) count($tokenPool->getEligibleTokens())); ?> eligible)</span>
                                <?php } else { ?>
                                    <span class="rl-badge rl-badge-failed"><span class="rl-status-dot"></span>No eligible tokens</span>
                                <?php } ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-calendly')); ?>" class="rl-btn rl-btn-outline rl-btn-sm" style="margin-left:6px;">Manage Pool</a>
                            </td>
                        </tr>
                        <tr>
                            <td>T10 Event (&ge;$10k MRR):</td>
                            <td>
                                <strong><?php echo esc_html($t10Name ?: 'VA Consultation T10'); ?></strong><br>
                                <span style="font-size: 11px; color: #71717a;"><?php echo esc_html($t10Status); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>T0 Event (&lt;$10k MRR):</td>
                            <td>
                                <strong><?php echo esc_html($t0Name ?: 'VA Consultation T0'); ?></strong><br>
                                <span style="font-size: 11px; color: #71717a;"><?php echo esc_html($t0Status); ?></span>
                            </td>
                        </tr>
                        <?php
                        /*
                         * "Active (200 OK)" above answers whether Calendly is reachable, which is a
                         * different question from whether there is anything left to book — on
                         * 2026-09-17 the t10 event type was 200 OK and completely sold out for hours,
                         * and this panel said everything was fine. These rows are the second question.
                         *
                         * Read from the last real availability fetch rather than making one: rendering
                         * an admin screen must not cost four Calendly calls, and a value older than
                         * AvailabilityHealthMonitor::STALE_AFTER_SECONDS says so instead of lying.
                         */
                        $availability = app(AvailabilityHealthMonitor::class)->status();
        foreach (['t10' => 'T10', 't0' => 'T0'] as $role => $label) {
            $seen = $availability[$role] ?? null;
            $stale = AvailabilityHealthMonitor::isStale($seen['checked_at'] ?? null);
            ?>
                            <tr>
                                <td><?php echo esc_html($label); ?> Availability:</td>
                                <td>
                                    <?php if ($seen === null) { ?>
                                        <span class="rl-badge"><span class="rl-status-dot"></span>Not checked yet</span>
                                    <?php } elseif (! empty($seen['sold_out'])) { ?>
                                        <span class="rl-badge rl-badge-failed"><span class="rl-status-dot"></span>Sold out<?php echo $stale ? ' (stale)' : ''; ?></span>
                                    <?php } else { ?>
                                        <span class="rl-badge rl-badge-succeeded"><span class="rl-status-dot"></span>Bookable<?php echo $stale ? ' (stale)' : ''; ?></span>
                                    <?php } ?>
                                    <?php
                                    /*
                                     * "Bookable" is true right up to the moment it is not, which is what made the
                                     * 2026-09-17 sell-out invisible until it had already cost a night of bookings.
                                     * The fill percentage is the part of this row that can be read early, so it sits
                                     * next to the badge rather than in the small print below it.
                                     */
                                    $fill = $seen['fill'] ?? null;

            if (is_numeric($fill)) {
                $band = (int) ($seen['band'] ?? AvailabilityHealthMonitor::BAND_OK);
                $fillClass = match (true) {
                    $band >= AvailabilityHealthMonitor::BAND_CRITICAL => 'rl-badge-failed',
                    $band === AvailabilityHealthMonitor::BAND_WARNING => 'rl-badge-pending',
                    default => 'rl-badge-succeeded',
                };
                ?>
                                        <span class="rl-badge <?php echo esc_attr($fillClass); ?>" style="margin-left:4px;">
                                            <span class="rl-status-dot"></span><?php echo esc_html(number_format(((float) $fill) * 100, 1)); ?>% full
                                        </span>
                                        <?php
            }
            ?>
                                    <?php if ($seen !== null) { ?>
                                        <br><span style="font-size: 11px; color: #71717a;">
                                            <?php
                                                /*
                                                 * The soonest bookable date is the number worth surfacing. "3 open days
                                                 * in 2026-09" says nothing about whether a lead can book today, and these
                                                 * calendars only publish a rolling few days, so the count swings for
                                                 * reasons that are not health.
                                                 */
                                                if (! empty($seen['next_available'])) {
                                                    echo 'Next opening '.esc_html(Carbon::parse($seen['next_available'])->format('M j'));
                                                } else {
                                                    echo 'No bookable dates';
                                                }

                                        /*
                                         * The counts behind the percentage. "93% full" is the alarm; "3 of 42 slots left"
                                         * is what tells you whether that is one cancellation away from fine.
                                         */
                                        if (isset($seen['open_slots'], $seen['booked_slots'])) {
                                            $capacity = (int) $seen['open_slots'] + (int) $seen['booked_slots'];
                                            echo ' &middot; '.esc_html((string) (int) $seen['open_slots']).' of '.esc_html((string) $capacity)
                                                .' slots free in the next '.esc_html((string) (int) ($seen['window_days'] ?? 4)).'d';
                                        }
                                        ?>
                                            &middot; checked <?php echo esc_html(Carbon::parse($seen['checked_at'])->diffForHumans()); ?>
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php
        }
        ?>
                    </table>
                </div>

                <!-- Retention & Database Status -->
                <div class="rl-detail-card">
                    <h3 class="rl-detail-title">Data Retention & Database Health</h3>
                    <table class="rl-key-value-table">
                        <tr>
                            <td style="width: 170px;">Retention Policy Floor:</td>
                            <td><strong>30 Days (ADR-0008)</strong></td>
                        </tr>
                        <tr>
                            <td>Leads Older Than 30 Days:</td>
                            <td>
                                <strong><?php echo esc_html((string) $staleLeadsCount); ?></strong> eligible for purge
                            </td>
                        </tr>
                        <tr>
                            <td>Active Database Tables:</td>
                            <td>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <span class="rl-badge rl-badge-succeeded"><span class="rl-status-dot"></span>wp_rl_leads (<?php echo esc_html((string) Lead::count()); ?>)</span>
                                    <span class="rl-badge rl-badge-succeeded"><span class="rl-status-dot"></span>wp_rl_lead_activity_logs (<?php echo esc_html((string) LeadActivityLog::count()); ?>)</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Booking Retry Queue -->
            <div class="rl-detail-card">
                <h3 class="rl-detail-title">Booking Retry Queue (<?php echo esc_html((string) $stuckLeads->count()); ?>)</h3>
                <table class="widefat rl-key-value-table">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Status</th>
                            <th>Retries</th>
                            <th>Next Retry</th>
                            <th>Last Failure Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stuckLeads as $stuckLead) {
                            $lastFailure = $stuckLead->activityLogs()
                                ->where('event_type', 'LeadCreated')
                                ->where('outcome', 'failed')
                                ->orderByDesc('created_at')
                                ->first();
                            ?>
                            <tr>
                                <td><?php echo esc_html($stuckLead->name.' ('.$stuckLead->email.')'); ?></td>
                                <td>
                                    <span class="rl-badge <?php echo $stuckLead->status === 'booking_failed' ? 'rl-badge-failed' : 'rl-badge-partial'; ?>">
                                        <span class="rl-status-dot"></span><?php echo esc_html($stuckLead->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html((string) $stuckLead->booking_retry_count).'/5'; ?></td>
                                <td><?php echo $stuckLead->booking_next_retry_at ? esc_html($stuckLead->booking_next_retry_at->format('Y-m-d H:i')) : '—'; ?></td>
                                <td style="max-width: 320px;"><?php echo esc_html($lastFailure?->description ?? '—'); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-leads-diagnostics')); ?>">
                                        <?php wp_nonce_field('rl_retry_booking_nonce'); ?>
                                        <input type="hidden" name="rl_action" value="retry_calendly_booking" />
                                        <input type="hidden" name="lead_id" value="<?php echo esc_attr((string) $stuckLead->id); ?>" />
                                        <button type="submit" class="rl-btn rl-btn-outline rl-btn-sm">Retry Booking Now</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if ($stuckLeads->isEmpty()) { ?>
                            <tr><td colspan="6">No leads currently stuck in the booking retry queue.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        $settings = app(LeadSettingsService::class)->get();
        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Lead Form & Routing Settings</h1>
                    <p class="rl-admin-subtitle">
                        Admin-configurable form fields, notification routing, and retention policy (ADR-0008).
                    </p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-btn rl-btn-outline">
                    <?php echo $this->iconArrowLeft(); ?> Back to All Leads
                </a>
            </div>

            <?php $this->renderAdminNavigation('settings'); ?>

            <?php if (isset($_GET['settings_saved'])) { ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Settings saved.</strong></p>
                </div>
            <?php } elseif (! empty($_GET['settings_error'])) { ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html(wp_unslash($_GET['settings_error'])); ?></p>
                </div>
            <?php } ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-leads-settings')); ?>">
                <?php wp_nonce_field('rl_save_lead_settings_nonce'); ?>
                <input type="hidden" name="rl_action" value="save_lead_settings" />

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Notifications</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="notification_emails">Recipient emails</label></th>
                            <td>
                                <input type="text" id="notification_emails" name="notification_emails" class="regular-text"
                                    value="<?php echo esc_attr(implode(', ', $settings['notification_emails'])); ?>"
                                    placeholder="sales@remoteleverage.com, ops@remoteleverage.com" />
                                <p class="description">Comma-separated. Notified by email on every new lead capture.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Optional Form Fields</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Visible fields</th>
                            <td>
                                <?php foreach (LeadSettingsService::OPTIONAL_FIELDS as $field) { ?>
                                    <label style="display: block; margin-bottom: 6px;">
                                        <input type="checkbox" name="optional_fields[<?php echo esc_attr($field); ?>]" value="1"
                                            <?php checked(! empty($settings['optional_fields'][$field])); ?> />
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $field))); ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Retention Policy</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="retention_days">Retention days</label></th>
                            <td>
                                <input type="number" id="retention_days" name="retention_days" min="<?php echo esc_attr((string) LeadSettingsService::MINIMUM_RETENTION_DAYS); ?>"
                                    value="<?php echo esc_attr((string) $settings['retention_days']); ?>" class="small-text" />
                                <p class="description">Minimum <?php echo esc_html((string) LeadSettingsService::MINIMUM_RETENTION_DAYS); ?> days (ADR-0008 floor). Leads older than this are eligible for purge.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Integration Overrides</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="hubspot_access_token">HubSpot access token</label></th>
                            <td><input type="password" id="hubspot_access_token" name="hubspot_access_token" class="regular-text"
                                    value="<?php echo esc_attr($settings['hubspot_access_token']); ?>" autocomplete="off" />
                                <p class="description">Overrides <code>HUBSPOT_ACCESS_TOKEN</code> from <code>.env</code> when set.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hubspot_portal_id">HubSpot portal ID</label></th>
                            <td><input type="text" id="hubspot_portal_id" name="hubspot_portal_id" class="regular-text"
                                    value="<?php echo esc_attr($settings['hubspot_portal_id']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hubspot_lifecycle_property">Deal status property</label></th>
                            <td><input type="text" id="hubspot_lifecycle_property" name="hubspot_lifecycle_property" class="regular-text"
                                    value="<?php echo esc_attr($settings['hubspot_lifecycle_property']); ?>" />
                                <p class="description">The HubSpot <strong>contact</strong> property that says where a deal stands &mdash; usually <code>lifecyclestage</code>, or <code>hs_lead_status</code> if your team works that field instead. Mirrored onto each referred lead hourly and shown to referrers as their referral&rsquo;s status. Getting this wrong means referrals never progress and no referrer is ever paid.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hubspot_lifecycle_fulfilled_value">Closing value</label></th>
                            <td><input type="text" id="hubspot_lifecycle_fulfilled_value" name="hubspot_lifecycle_fulfilled_value" class="regular-text"
                                    value="<?php echo esc_attr($settings['hubspot_lifecycle_fulfilled_value']); ?>" />
                                <p class="description">The value of the property above that means the deal closed. Reaching it fulfils the attached referral and creates the reward automatically &mdash; no admin action needed. Default <code>customer</code>.</p></td>
                        </tr>
                        <tr>
                            <th scope="row" colspan="2" style="padding-top: 24px;">
                                <h3 style="margin: 0 0 4px;">Email validation</h3>
                                <p style="font-weight: 400; color: #52525b; margin: 0;">
                                    Checked in order: blacklisted address, then domain rule, then ZeroBounce.
                                    The local lists cost nothing, so a known-bad address never spends a credit.
                                    ZeroBounce <strong>fails open</strong> — if it is unreachable the address is
                                    accepted, because rejecting a real buyer costs more than letting one through.
                                </p>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="domain_validator_mode">Domain validator</label></th>
                            <td>
                                <select id="domain_validator_mode" name="domain_validator_mode">
                                    <?php foreach (['none' => 'None', 'allow' => 'Allow only these domains', 'block' => 'Block these domains'] as $value => $label) { ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['domain_validator_mode'], $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php } ?>
                                </select>
                                <p class="description">Production runs <strong>Block</strong>, listing disposable-mailbox providers.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_domains">Email domains</label></th>
                            <td>
                                <textarea id="email_domains" name="email_domains" rows="6" class="large-text code"
                                          placeholder="cuvox.de&#10;armyspy.com&#10;dayrep.com"><?php echo esc_textarea($settings['email_domains']); ?></textarea>
                                <p class="description">One per line, domain only (no <code>@</code>).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="blacklisted_emails">Blacklisted addresses</label></th>
                            <td>
                                <textarea id="blacklisted_emails" name="blacklisted_emails" rows="3" class="large-text code"><?php echo esc_textarea($settings['blacklisted_emails']); ?></textarea>
                                <p class="description">Comma separated. Exact addresses only — use the domain list for whole providers.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zerobounce_enabled">ZeroBounce</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="zerobounce_enabled" name="zerobounce_enabled" value="1" <?php checked((bool) $settings['zerobounce_enabled']); ?> />
                                    Verify the mailbox actually exists
                                </label>
                                <p><input type="password" id="zerobounce_api_key" name="zerobounce_api_key" class="regular-text"
                                          value="<?php echo esc_attr($settings['zerobounce_api_key']); ?>" autocomplete="off"
                                          placeholder="Falls back to ZEROBOUNCE_API_KEY" /></p>
                                <p class="description">Rejects <code>invalid</code>, <code>spamtrap</code>, <code>abuse</code> and <code>do_not_mail</code>. <code>catch-all</code> and <code>unknown</code> pass — they mean undetermined, not bad.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_validation_message">Rejection message</label></th>
                            <td>
                                <input type="text" id="email_validation_message" name="email_validation_message" class="large-text"
                                       value="<?php echo esc_attr($settings['email_validation_message']); ?>"
                                       placeholder="Please use a valid business email address." />
                                <p class="description">Shown for every rejection reason, so it must not reveal which list matched.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slack_webhook_url">Slack webhook URL</label></th>
                            <td><input type="url" id="slack_webhook_url" name="slack_webhook_url" class="regular-text"
                                    value="<?php echo esc_attr($settings['slack_webhook_url']); ?>" placeholder="https://hooks.slack.com/services/..." /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slack_signing_secret">Slack signing secret</label></th>
                            <td>
                                <input type="password" id="slack_signing_secret" name="slack_signing_secret" class="regular-text"
                                       value="<?php echo esc_attr($settings['slack_signing_secret']); ?>"
                                       autocomplete="off" placeholder="32 hex characters" />
                                <p class="description">
                                    From the Slack app's Basic Information &rarr; App Credentials. Verifies that a button
                                    press really came from Slack, and is what makes the Claim / Mark contacted / Block
                                    buttons appear on a lead alert at all. <code>SLACK_SIGNING_SECRET</code> wins where it
                                    is set. Set the app's Interactivity request URL to
                                    <code><?php echo esc_html(home_url('/api/webhooks/slack/interactions')); ?></code> as
                                    well, or the buttons will render and go nowhere.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lead_webhook_url">Outgoing lead webhook URL</label></th>
                            <td>
                                <input type="url" id="lead_webhook_url" name="lead_webhook_url" class="regular-text"
                                    value="<?php echo esc_attr($settings['lead_webhook_url']); ?>" placeholder="https://api.example.com/webhooks/leads" />
                                <p class="description">
                                    Last resort only. The endpoint is configured in <code>config/services.php</code>
                                    (defaulting to n8n) and <code>LEAD_WEBHOOK_URL</code> overrides that &mdash; this
                                    field is read only when both are empty, so a value here has no effect on an
                                    environment that has either.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p>
                    <button type="submit" class="rl-btn rl-btn-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    protected function renderAdminNavigation(string $activeTab): void
    {
        $tabs = [
            'leads' => ['label' => 'All Leads & Submissions', 'url' => admin_url('admin.php?page=rl-leads')],
            'activity' => ['label' => 'Live Activity & Audit Logs', 'url' => admin_url('admin.php?page=rl-leads-activity')],
            'diagnostics' => ['label' => 'Diagnostics & Health', 'url' => admin_url('admin.php?page=rl-leads-diagnostics')],
            'settings' => ['label' => 'Form & Routing Settings', 'url' => admin_url('admin.php?page=rl-leads-settings')],
        ];
        ?>
        <div class="rl-tabs">
            <?php foreach ($tabs as $key => $tab) { ?>
                <a href="<?php echo esc_url($tab['url']); ?>" class="rl-tab <?php echo $activeTab === $key ? 'rl-tab-active' : ''; ?>">
                    <?php echo esc_html($tab['label']); ?>
                </a>
            <?php } ?>
        </div>
        <?php
    }

    protected function getInitials(?string $name, ?string $email): string
    {
        return LeadAvatar::initials($name, $email);
    }

    /**
     * The avatar beside a lead: their company's logo over their tinted initials.
     *
     * The initials are always in the markup, underneath. The logo is an <img> laid over them
     * that deletes itself if the request fails — which is what an unknown domain does, because
     * the favicon service answers 404 rather than handing back a generic globe with a 200. So a
     * row degrades to initials on its own, with no probe from PHP and no placeholder to detect.
     *
     * `referrerpolicy` matters more than it looks: without it the admin URL, filters and all,
     * rides along to Google on every row.
     */
    protected function renderAvatar(?string $name, ?string $email, int $size = 36): void
    {
        [$background, $foreground] = LeadAvatar::tint($email, $name);
        $logo = LeadAvatar::logoUrl($email);
        $domain = LeadAvatar::domain($email);
        ?>
        <div class="rl-avatar" style="--rl-avatar-size: <?php echo esc_attr((string) $size); ?>px;">
            <span class="rl-avatar-initials" style="background: <?php echo esc_attr($background); ?>; color: <?php echo esc_attr($foreground); ?>;">
                <?php echo esc_html(LeadAvatar::initials($name, $email)); ?>
            </span>
            <?php if ($logo !== null) { ?>
                <img class="rl-avatar-logo"
                     src="<?php echo esc_url($logo); ?>"
                     alt=""
                     title="<?php echo esc_attr((string) $domain); ?>"
                     loading="lazy"
                     decoding="async"
                     referrerpolicy="no-referrer"
                     onerror="this.remove();" />
            <?php } ?>
        </div>
        <?php
    }

    protected function iconUsers(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    }

    protected function iconCalendar(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="m9 16 2 2 4-4"/></svg>';
    }

    protected function iconCheck(): string
    {
        return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    }

    protected function iconTrending(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>';
    }

    protected function iconAlert(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>';
    }

    protected function iconShield(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>';
    }

    protected function iconSearch(): string
    {
        return '<svg class="rl-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>';
    }

    protected function iconDownload(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
    }

    protected function iconTrash(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';
    }

    protected function iconVideo(): string
    {
        return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect width="15" height="14" x="1" y="5" rx="2" ry="2"/></svg>';
    }

    protected function iconArrowRight(): string
    {
        return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
    }

    /**
     * Render an opaque vendor token without letting it wreck the layout.
     *
     * Ad-platform click ids are long and getting longer — a real `fbclid` arrives at 212
     * characters and `_fbc` wraps it in another 20 — so printed raw they took two full-width
     * lines each and pushed the rest of the attribution card out of alignment.
     *
     * Truncated rather than wrapped, and collapsed behind a `<details>` rather than cut off
     * for good: nobody reads these, but they do get pasted into Meta's Events Manager when
     * attribution is being chased, so the full value has to stay selectable. No JS — the admin
     * page has none and this does not warrant starting.
     */
    /**
     * Flatten an attribution value to a displayable string.
     *
     * Full Capture shows whatever the visit carried, so it cannot assume the payload is two
     * levels of scalars. The Gravity import writes lists (gravity.forms, gravity.entry_ids);
     * casting one to string raises E_WARNING, which Acorn promotes to an ErrorException and
     * takes the whole detail page down.
     */
    protected function attributionScalar(mixed $value): string
    {
        if (is_array($value)) {
            $flat = array_filter($value, static fn ($item): bool => ! is_array($item) && ! is_object($item));

            // A plain list reads better comma-joined than as JSON; anything deeper keeps its shape.
            return count($flat) === count($value)
                ? implode(', ', array_map(static fn ($item): string => (string) $item, $flat))
                : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    protected function renderLongToken(?string $value, int $visible = 32): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) <= $visible * 2) {
            return '<code>'.esc_html($value).'</code>';
        }

        // Head and tail both shown: the head identifies the token, the tail is what differs
        // between two clicks from the same campaign.
        $preview = mb_substr($value, 0, $visible).'…'.mb_substr($value, -8);

        return '<details style="display:inline-block; max-width:100%; vertical-align:top;">'
            .'<summary style="cursor:pointer; list-style:none; outline:none;">'
            .'<code style="font-size:11px;" title="'.esc_attr($value).'">'.esc_html($preview).'</code>'
            .'<span style="color:#71717a; font-size:10px; margin-left:6px;">'.mb_strlen($value).' chars</span>'
            .'</summary>'
            .'<code style="font-size:10px; display:block; margin-top:4px; word-break:break-all; white-space:normal; color:#3f3f46;">'
            .esc_html($value)
            .'</code>'
            .'</details>';
    }

    protected function iconArrowLeft(): string
    {
        return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>';
    }

    protected function iconPhone(): string
    {
        return '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
    }

    protected function iconBuilding(): string
    {
        return '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/></svg>';
    }

    /**
     * The lead's activity log and its integration calls, interleaved in time.
     *
     * Two sources rather than one because they answer different questions, and reading them in
     * separate lists loses the thing that makes them useful together: a HubSpot 400 sits
     * immediately under the "synced to HubSpot" entry that claimed success, and the ordering is
     * what makes that visible.
     *
     * @return array<int, array{type: string, model: object, at: Carbon|null, outcome: string, icon_key: string, label: string, description: string}>
     */
    protected function mergedTimeline(Lead $lead): array
    {
        $entries = [];

        foreach ($this->timelineSource($lead, 'activityLogs') as $log) {
            $entries[] = [
                'type' => 'log',
                'model' => $log,
                'at' => $log->created_at,
                'outcome' => (string) $log->outcome,
                'icon_key' => (string) $log->actor_domain,
                'label' => (string) $log->actor_domain,
                'description' => (string) $log->description,
            ];
        }

        foreach ($this->timelineSource($lead, 'integrationCalls') as $call) {
            $entries[] = [
                'type' => 'call',
                'model' => $call,
                'at' => $call->created_at,
                'outcome' => (string) $call->outcome,
                'icon_key' => (string) $call->integration,
                'label' => ucfirst((string) $call->integration),
                'description' => (string) ($call->operation ?: $call->method.' '.$call->url),
            ];
        }

        /*
         * Stable sort. Several of these are written within the same second — the HubSpot call and
         * the log entry announcing it, for instance — and an unstable comparison would let them
         * swap between page loads, which reads as the call having happened before the decision.
         */
        usort($entries, function (array $a, array $b) {
            $at = $a['at']?->getTimestamp() ?? 0;
            $bt = $b['at']?->getTimestamp() ?? 0;

            if ($at !== $bt) {
                return $at <=> $bt;
            }

            // Within the same second, the decision precedes the call it caused.
            return ($a['type'] === 'log' ? 0 : 1) <=> ($b['type'] === 'log' ? 0 : 1);
        });

        return $entries;
    }

    /**
     * One of the two timeline relations, or an empty collection if its table cannot be read.
     *
     * Both halves of this card are diagnostics. Losing one is a degraded card; letting it throw
     * costs the whole lead detail screen — name, email, booking state, everything — because the
     * exception escapes mid-render, after output has started, and surfaces as a "headers already
     * sent" fatal that names neither the table nor the relation. That is what a pending
     * `create_integration_calls_table` migration did on 2026-09-16.
     *
     * Deliberately narrow: only `QueryException`, which is the storage-shaped failure (missing
     * table, missing column, connection gone). Anything else is a bug in the timeline itself and
     * should still be loud.
     *
     * @param  'activityLogs'|'integrationCalls'  $relation
     * @return EloquentCollection<int, covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function timelineSource(Lead $lead, string $relation): EloquentCollection
    {
        $key = $lead->getKey().':'.$relation;

        if (isset($this->timelineSources[$key])) {
            return $this->timelineSources[$key];
        }

        try {
            return $this->timelineSources[$key] = $lead->{$relation};
        } catch (QueryException $e) {
            /*
             * Recorded against the lead rather than a flat flag: the count badge and the timeline
             * body both ask, and a second attempt would repeat a query already known to fail.
             */
            $this->unavailableSources[$key] = self::TIMELINE_SOURCE_LABELS[$relation] ?? $relation;

            Log::warning(
                "LeadsAdminDashboard: {$relation} unavailable for lead {$lead->getKey()}, "
                ."timeline rendered without it: {$e->getMessage()}"
            );

            return $this->timelineSources[$key] = new EloquentCollection;
        }
    }

    /**
     * Human names of the timeline sources that failed to load for this lead, if any.
     *
     * Drives the inline notice on the card. A silent guard would be worse than the crash it
     * replaces — an empty timeline reading as "nothing happened" is how a missing table becomes
     * a wrong conclusion about a lead.
     *
     * @return array<int, string>
     */
    protected function unavailableTimelineSources(Lead $lead): array
    {
        $prefix = $lead->getKey().':';

        return array_values(array_filter(
            $this->unavailableSources,
            fn (string $key) => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        ));
    }

    protected function extractMeetingDetails(Lead $lead): array
    {
        $log = $lead->activityLogs
            ? $lead->activityLogs->first(fn ($l) => $l->actor_domain === 'Scheduling' && $l->outcome === 'succeeded')
            : null;

        return $log?->payload ?? [];
    }
}
