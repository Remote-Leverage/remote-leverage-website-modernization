<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class LeadsAdminDashboard
{
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
            position: 30
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'All Leads & Submissions',
            menu_title: 'All Leads',
            capability: 'manage_options',
            menu_slug: 'rl-leads',
            callback: [$this, 'renderDashboard']
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'Live Activity & Audit Logs',
            menu_title: 'Activity Logs',
            capability: 'manage_options',
            menu_slug: 'rl-leads-activity',
            callback: [$this, 'renderActivityLogs']
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'API & Integration Diagnostics',
            menu_title: 'Diagnostics',
            capability: 'manage_options',
            menu_slug: 'rl-leads-diagnostics',
            callback: [$this, 'renderDiagnostics']
        );
    }

    public function handleAdminActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_REQUEST['rl_action'] ?? '');

        if ($action === 'export_csv') {
            check_admin_referer('rl_export_leads_nonce');
            $this->exportLeadsCsv();
            exit;
        }

        if ($action === 'purge_leads') {
            check_admin_referer('rl_purge_leads_nonce');
            $purgedCount = app(PurgeOldLeadsAction::class)->execute(30);
            Cache::forget('rl_lead_dashboard_kpi_metrics');
            wp_safe_redirect(admin_url('admin.php?page=rl-leads&purged_count='.$purgedCount));
            exit;
        }

        if ($action === 'update_status') {
            check_admin_referer('rl_update_status_nonce');
            $leadId = absint($_POST['lead_id'] ?? 0);
            $newStatus = sanitize_text_field($_POST['new_status'] ?? '');
            $lead = Lead::find($leadId);

            if ($lead && in_array($newStatus, ['captured', 'qualified', 'booked', 'partial', 'abandoned', 'canceled'], true)) {
                $oldStatus = $lead->status;
                $lead->update(['status' => $newStatus]);
                Cache::forget('rl_lead_dashboard_kpi_metrics');

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

        if ($action === 'delete_lead') {
            check_admin_referer('rl_delete_lead_nonce');
            $leadId = absint($_GET['lead_id'] ?? 0);
            $lead = Lead::find($leadId);

            if ($lead) {
                $lead->activityLogs()->delete();
                $lead->delete();
                Cache::forget('rl_lead_dashboard_kpi_metrics');
                wp_safe_redirect(admin_url('admin.php?page=rl-leads&lead_deleted=1'));
                exit;
            }
        }
    }

    protected function exportLeadsCsv(): void
    {
        $filename = 'remoteleverage-leads-'.gmdate('Y-m-d-His').'.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'ID',
            'UUID',
            'Created Date (UTC)',
            'Full Name',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Phone Country',
            'Company',
            'Role Needed',
            'Monthly Revenue',
            'Status',
            'Scheduled Time',
            'Google Meet URL',
            'Source Type',
            'UTM Source',
            'UTM Medium',
            'UTM Campaign',
            'UTM Term',
            'UTM Content',
            'GCLID',
            'FBCLID',
            'Referral Code',
            'Landing URL',
            'Referrer URL',
            'Notes',
            'Activity Logs Count',
        ]);

        $query = Lead::query()->latest();

        if (! empty($_GET['status'])) {
            $query->where('status', sanitize_text_field($_GET['status']));
        }
        if (! empty($_GET['s'])) {
            $this->applyOptimizedSearch($query, sanitize_text_field($_GET['s']));
        }

        $query->chunkById(100, function ($leads) use ($output) {
            foreach ($leads as $lead) {
                $meeting = $this->extractMeetingDetails($lead);
                fputcsv($output, [
                    $lead->id,
                    $lead->uuid,
                    $lead->created_at?->toDateTimeString(),
                    $lead->name,
                    $lead->first_name,
                    $lead->last_name,
                    $lead->email,
                    $lead->phone,
                    $lead->phone_country,
                    $lead->company,
                    $lead->role_needed,
                    $lead->monthly_revenue,
                    $lead->status,
                    $meeting['start_time'] ?? '',
                    $meeting['meet_url'] ?? '',
                    $lead->source_type,
                    $lead->utm_source,
                    $lead->utm_medium,
                    $lead->utm_campaign,
                    $lead->utm_term,
                    $lead->utm_content,
                    $lead->gclid,
                    $lead->fbclid,
                    $lead->referral_code,
                    $lead->landing_url,
                    $lead->referrer_url,
                    $lead->notes,
                    $lead->activityLogs()->count(),
                ]);
            }
        });

        fclose($output);
    }

    public function enqueueAdminStyles(string $hook): void
    {
        if (! str_contains($hook, 'rl-leads')) {
            return;
        }

        wp_add_inline_style('wp-admin', '
            /* --- Base Typography & Reset --- */
            .rl-admin-wrap {
                max-width: 1440px;
                margin: 20px 20px 40px 0;
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

            /* --- Contact Cell Layout --- */
            .rl-contact-cell {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .rl-avatar-initials {
                width: 36px;
                height: 36px;
                border-radius: 9999px;
                background: #f4f4f5;
                border: 1px solid #e4e4e7;
                color: #09090b;
                font-weight: 600;
                font-size: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
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
                gap: 5px;
                padding: 2px 8px;
                border-radius: 9999px;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.4;
                white-space: nowrap;
            }
            .rl-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                flex-shrink: 0;
            }
            .rl-badge-booked {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }
            .rl-badge-booked .rl-status-dot { background: #10b981; }

            .rl-badge-captured, .rl-badge-qualified {
                background: #eff6ff;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }
            .rl-badge-captured .rl-status-dot, .rl-badge-qualified .rl-status-dot { background: #3b82f6; }

            .rl-badge-partial {
                background: #fffbeb;
                color: #92400e;
                border: 1px solid #fde68a;
            }
            .rl-badge-partial .rl-status-dot { background: #f59e0b; }

            .rl-badge-abandoned, .rl-badge-canceled, .rl-badge-failed {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }
            .rl-badge-abandoned .rl-status-dot, .rl-badge-canceled .rl-status-dot, .rl-badge-failed .rl-status-dot { background: #ef4444; }

            .rl-badge-succeeded {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }
            .rl-badge-dispatch {
                background: #f4f4f5;
                color: #18181b;
                border: 1px solid #e4e4e7;
            }
            .rl-badge-consumption {
                background: #eff6ff;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }

            /* --- Revenue Pills --- */
            .rl-pill-t10 {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 3px 8px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 600;
                background: #faf5ff;
                color: #6b21a8;
                border: 1px solid #e9d5ff;
            }
            .rl-pill-t0 {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 3px 8px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 500;
                background: #f4f4f5;
                color: #52525b;
                border: 1px solid #e4e4e7;
            }

            /* --- Meet Button --- */
            .rl-meet-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 5px 10px;
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.15s ease;
            }
            .rl-meet-btn:hover {
                background: #d1fae5;
                color: #047857;
                border-color: #6ee7b7;
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
            }
            .rl-audit-pill:hover {
                background: #e4e4e7;
                color: #09090b;
            }

            /* --- Timeline & Detail --- */
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
                border-left: 4px solid #f59e0b !important;
                border-radius: 8px !important;
                padding: 12px 16px !important;
                margin: 16px 0 !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
                color: #09090b !important;
                font-size: 13px !important;
            }
            .rl-admin-wrap .notice-success,
            .wp-admin.rl-leads-screen .notice-success {
                border-left-color: #10b981 !important;
            }
            .rl-admin-wrap .notice-error,
            .wp-admin.rl-leads-screen .notice-error {
                border-left-color: #ef4444 !important;
            }
            .rl-admin-wrap .notice-info,
            .wp-admin.rl-leads-screen .notice-info {
                border-left-color: #3b82f6 !important;
            }

            /* --- Modern WordPress Admin Chrome On Leads Screen --- */
            .rl-leads-screen #adminmenu,
            .rl-leads-screen #adminmenuback,
            .rl-leads-screen #adminmenuwrap {
                background-color: #09090b !important;
            }
            .rl-leads-screen #adminmenu a {
                color: #a1a1aa !important;
                font-weight: 500 !important;
            }
            .rl-leads-screen #adminmenu a:hover,
            .rl-leads-screen #adminmenu li.menu-top:hover,
            .rl-leads-screen #adminmenu li.opensub>a.menu-top {
                background-color: #18181b !important;
                color: #fafafa !important;
            }
            .rl-leads-screen #adminmenu li.current a.menu-top,
            .rl-leads-screen #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu {
                background-color: #27272a !important;
                color: #ffffff !important;
                font-weight: 600 !important;
            }
            .rl-leads-screen #adminmenu .wp-has-current-submenu .wp-submenu {
                background-color: #121215 !important;
            }
            .rl-leads-screen #adminmenu .wp-submenu a {
                color: #a1a1aa !important;
            }
            .rl-leads-screen #adminmenu .wp-submenu a:hover {
                color: #ffffff !important;
            }
            .rl-leads-screen #wpadminbar {
                background: #09090b !important;
                border-bottom: 1px solid #27272a !important;
            }
            .rl-leads-screen #wpadminbar .ab-item,
            .rl-leads-screen #wpadminbar a.ab-item {
                color: #a1a1aa !important;
            }
            .rl-leads-screen #wpadminbar .ab-item:hover,
            .rl-leads-screen #wpadminbar a.ab-item:hover {
                color: #ffffff !important;
                background: #18181b !important;
            }
        ');
    }

    /**
     * Apply high-performance smart search routing to the Lead query.
     * Uses B-tree indexed lookups for emails and phones, MySQL FULLTEXT inverted index
     * for general text search across (name, email, company, phone), and falls back
     * cleanly to indexed LIKE queries.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyOptimizedSearch($query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        // 1. Email Lookup: user typed an email or prefix (contains '@')
        // Uses the indexed `wp_rl_leads_email_index` B-tree index
        if (str_contains($search, '@')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', $search)
                    ->orWhere('email', 'LIKE', $search.'%');
            });

            return;
        }

        // 2. Phone Lookup: search contains only digits, +, -, (, ) and spaces
        // Uses `wp_rl_leads_phone_index` B-tree index
        if (preg_match('/^[+0-9\s\-()]{4,}$/', $search)) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $search);
            $query->where(function ($q) use ($search, $cleanPhone) {
                $q->where('phone', $search)
                    ->orWhere('phone', 'LIKE', '%'.$cleanPhone.'%');
            });

            return;
        }

        // 3. MySQL FULLTEXT Inverted Index Search
        // Sub-millisecond inverted-index lookup across (name, email, company, phone)
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'mysql' && mb_strlen($search) >= 3) {
            // Strip MySQL boolean operators to sanitize input
            $sanitized = preg_replace('/[+\-><()~*\"@]+/', ' ', $search);
            $tokens = array_filter(explode(' ', trim((string) $sanitized)));

            if (! empty($tokens)) {
                // Suffix wildcard for each word: e.g. "+acme* +john*"
                $booleanExpr = implode(' ', array_map(fn ($token) => '+'.$token.'*', $tokens));

                $query->where(function ($q) use ($booleanExpr, $search) {
                    $q->whereRaw(
                        'MATCH(name, email, company, phone) AGAINST(? IN BOOLEAN MODE)',
                        [$booleanExpr]
                    )
                    ->orWhere('utm_campaign', 'LIKE', $search.'%')
                    ->orWhere('referral_code', 'LIKE', $search.'%');
                });

                return;
            }
        }

        // 4. Fallback for SQLite / short terms: standard substring query
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->orWhere('phone', 'LIKE', "%{$search}%")
                ->orWhere('company', 'LIKE', "%{$search}%")
                ->orWhere('utm_campaign', 'LIKE', "%{$search}%")
                ->orWhere('referral_code', 'LIKE', "%{$search}%");
        });
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

        $query = Lead::query()->latest('id');

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

        if ($dateFilter === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($dateFilter === 'week') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($dateFilter === 'month') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        // Cache executive KPI metric counts for 3 minutes to avoid full table aggregate scans on every filter
        $metrics = Cache::remember('rl_lead_dashboard_kpi_metrics', 180, function () {
            return [
                'total' => Lead::count(),
                'booked' => Lead::where('status', 'booked')->count(),
                't10' => Lead::whereNotIn('monthly_revenue', ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'])
                    ->whereNotNull('monthly_revenue')
                    ->where('monthly_revenue', '!=', '')
                    ->count(),
                'partial' => Lead::where('status', 'partial')->count(),
                'logs' => LeadActivityLog::count(),
            ];
        });

        $totalLeads = $metrics['total'];
        $bookedLeads = $metrics['booked'];
        $t10Leads = $metrics['t10'];
        $partialLeads = $metrics['partial'];
        $totalLogs = $metrics['logs'];

        $leads = $query->paginate(20);

        ?>
        <div class="wrap rl-admin-wrap">
            <?php if (isset($_GET['purged_count'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Retention Purge Complete:</strong> <?php echo esc_html($_GET['purged_count']); ?> leads older than 30 days were successfully purged.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['lead_deleted'])) : ?>
                <div class="notice notice-info is-dismissible">
                    <p>Lead and its associated audit activity logs were permanently deleted.</p>
                </div>
            <?php endif; ?>

            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Lead Management & Submissions</h1>
                    <p class="rl-admin-subtitle">
                        Native Livewire lead ingestion, MRR tier routing, and dual-logging activity audit trail.
                    </p>
                </div>
                <div class="rl-actions-group">
                    <?php
                    $exportUrl = wp_nonce_url(
                        admin_url('admin.php?page=rl-leads&rl_action=export_csv&status='.urlencode($statusFilter).'&s='.urlencode($search)),
                        'rl_export_leads_nonce'
                    );
                    $purgeUrl = wp_nonce_url(
                        admin_url('admin.php?page=rl-leads&rl_action=purge_leads'),
                        'rl_purge_leads_nonce'
                    );
                    ?>
                    <a href="<?php echo esc_url($exportUrl); ?>" class="rl-btn rl-btn-outline">
                        <?php echo $this->iconDownload(); ?> Export Leads (CSV)
                    </a>
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-card <?php echo ! $statusFilter && ! $mrrFilter ? 'rl-card-active' : ''; ?>">
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
                    <div class="rl-card-value" style="color: #065f46;"><?php echo esc_html((string) $bookedLeads); ?></div>
                    <div class="rl-card-subtext">Calendar scheduled calls</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&mrr=t10')); ?>" class="rl-card <?php echo $mrrFilter === 't10' ? 'rl-card-active' : ''; ?>">
                    <div class="rl-card-header">
                        <span class="rl-card-title">High-Tier MRR (&ge;$10k)</span>
                        <?php echo $this->iconTrending(); ?>
                    </div>
                    <div class="rl-card-value" style="color: #6b21a8;"><?php echo esc_html((string) $t10Leads); ?></div>
                    <div class="rl-card-subtext">&ge; $10k/mo revenue tier</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&status=partial')); ?>" class="rl-card <?php echo $statusFilter === 'partial' ? 'rl-card-active' : ''; ?>">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Partial Form Drops</span>
                        <?php echo $this->iconAlert(); ?>
                    </div>
                    <div class="rl-card-value" style="color: #92400e;"><?php echo esc_html((string) $partialLeads); ?></div>
                    <div class="rl-card-subtext">Incomplete step 1 drop-offs</div>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads-activity')); ?>" class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Audit Log Events</span>
                        <?php echo $this->iconShield(); ?>
                    </div>
                    <div class="rl-card-value" style="color: #1e40af;"><?php echo esc_html((string) $totalLogs); ?></div>
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
                        <option value="booked" <?php selected($statusFilter, 'booked'); ?>>Booked</option>
                        <option value="captured" <?php selected($statusFilter, 'captured'); ?>>Captured / Qualified</option>
                        <option value="partial" <?php selected($statusFilter, 'partial'); ?>>Partial (Step 1)</option>
                        <option value="abandoned" <?php selected($statusFilter, 'abandoned'); ?>>Abandoned</option>
                        <option value="canceled" <?php selected($statusFilter, 'canceled'); ?>>Canceled</option>
                    </select>

                    <select name="mrr" class="rl-select">
                        <option value="">All Revenue Tiers</option>
                        <option value="t10" <?php selected($mrrFilter, 't10'); ?>>&ge; $10k/mo (Tier 10)</option>
                        <option value="t0" <?php selected($mrrFilter, 't0'); ?>>&lt; $10k/mo (Tier 0)</option>
                    </select>

                    <select name="date_range" class="rl-select">
                        <option value="">All Dates</option>
                        <option value="today" <?php selected($dateFilter, 'today'); ?>>Today</option>
                        <option value="week" <?php selected($dateFilter, 'week'); ?>>Last 7 Days</option>
                        <option value="month" <?php selected($dateFilter, 'month'); ?>>Last 30 Days</option>
                    </select>

                    <button type="submit" class="rl-btn rl-btn-primary">Filter</button>
                    <?php if ($search || $statusFilter || $mrrFilter || $dateFilter) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-btn rl-btn-outline">Reset</a>
                    <?php endif; ?>
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
                            <th>Attribution</th>
                            <th>Audit Logs</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leads->isEmpty()) : ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 48px; color: #a1a1aa;">
                                    No submissions found matching your filters.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($leads as $lead) :
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
                                            <div class="rl-avatar-initials"><?php echo esc_html($this->getInitials($lead->name, $lead->email)); ?></div>
                                            <div class="rl-contact-info">
                                                <span class="rl-contact-name"><?php echo esc_html($lead->name ?: 'Partial Contact'); ?></span>
                                                <a href="mailto:<?php echo esc_attr($lead->email); ?>" class="rl-contact-email"><?php echo esc_html($lead->email); ?></a>
                                                <div class="rl-contact-sub">
                                                    <?php if ($lead->phone) : ?>
                                                        <a href="tel:<?php echo esc_attr($lead->phone); ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px;">
                                                            <?php echo $this->iconPhone(); ?> <?php echo esc_html($lead->phone); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($lead->company) : ?>
                                                        <span class="rl-contact-company">
                                                            <?php echo $this->iconBuilding(); ?> <?php echo esc_html($lead->company); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($lead->monthly_revenue) : ?>
                                            <span class="<?php echo $isT10 ? 'rl-pill-t10' : 'rl-pill-t0'; ?>">
                                                <?php echo esc_html($lead->monthly_revenue); ?> &bull; <?php echo $isT10 ? 'T10' : 'T0'; ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">Not specified</span>
                                        <?php endif; ?>
                                        <?php if ($lead->role_needed) : ?>
                                            <div style="font-size: 11px; color: #71717a; margin-top: 3px;">Role: <?php echo esc_html($lead->role_needed); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($lead->status); ?>">
                                            <span class="rl-status-dot"></span>
                                            <?php echo esc_html(ucfirst($lead->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (! empty($meeting['meet_url'])) : ?>
                                            <a href="<?php echo esc_url($meeting['meet_url']); ?>" target="_blank" class="rl-meet-btn">
                                                <?php echo $this->iconVideo(); ?> Google Meet
                                            </a>
                                        <?php elseif ($lead->status === 'booked') : ?>
                                            <span style="color: #065f46; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                &check; Confirmed
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">Not Scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lead->utm_source || $lead->utm_campaign) : ?>
                                            <div style="font-weight: 600; color: #09090b; font-size: 12px;"><?php echo esc_html($lead->utm_source ?: 'direct'); ?></div>
                                            <?php if ($lead->utm_campaign) : ?>
                                                <div style="font-size: 11px; color: #71717a;">cmp: <?php echo esc_html($lead->utm_campaign); ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($lead->referral_code) : ?>
                                            <span class="rl-badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">via: <?php echo esc_html($lead->referral_code); ?></span>
                                        <?php else : ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">Direct Organic</span>
                                        <?php endif; ?>
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
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #71717a;">
                    Page <strong><?php echo esc_html((string) $leads->currentPage()); ?></strong> of <strong><?php echo esc_html((string) $leads->lastPage()); ?></strong>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($leads->previousPageUrl()) : ?>
                        <a href="<?php echo esc_url($leads->previousPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm"><?php echo $this->iconArrowLeft(); ?> Previous</a>
                    <?php endif; ?>
                    <?php if ($leads->nextPageUrl()) : ?>
                        <a href="<?php echo esc_url($leads->nextPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm">Next <?php echo $this->iconArrowRight(); ?></a>
                    <?php endif; ?>
                </div>
            </div>
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
            'rl_delete_lead_nonce'
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

            <?php if (isset($_GET['status_updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Lead status successfully updated.</p>
                </div>
            <?php endif; ?>

            <!-- Lead Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <h1 class="rl-admin-title" style="margin: 0;">
                            <?php echo esc_html($lead->name ?: 'Lead #'.$lead->id); ?>
                        </h1>
                        <span class="rl-badge rl-badge-<?php echo esc_attr($lead->status); ?>">
                            <span class="rl-status-dot"></span>
                            <?php echo esc_html(ucfirst($lead->status)); ?>
                        </span>
                    </div>
                    <div style="color: #71717a; font-size: 13px; margin-top: 6px;">
                        Captured: <strong><?php echo esc_html($lead->created_at?->format('F j, Y \a\t g:i:s A')); ?></strong> &bull; 
                        UUID: <code style="background: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 4px; padding: 2px 6px; font-size: 11px;"><?php echo esc_html($lead->uuid); ?></code>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Status Updater -->
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" style="display: flex; gap: 6px; align-items: center;">
                        <?php wp_nonce_field('rl_update_status_nonce'); ?>
                        <input type="hidden" name="rl_action" value="update_status" />
                        <input type="hidden" name="lead_id" value="<?php echo esc_attr((string) $lead->id); ?>" />
                        <select name="new_status" class="rl-select" style="font-weight: 500;">
                            <option value="captured" <?php selected($lead->status, 'captured'); ?>>Captured</option>
                            <option value="qualified" <?php selected($lead->status, 'qualified'); ?>>Qualified</option>
                            <option value="booked" <?php selected($lead->status, 'booked'); ?>>Booked</option>
                            <option value="partial" <?php selected($lead->status, 'partial'); ?>>Partial</option>
                            <option value="abandoned" <?php selected($lead->status, 'abandoned'); ?>>Abandoned</option>
                            <option value="canceled" <?php selected($lead->status, 'canceled'); ?>>Canceled</option>
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
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                <!-- Left Column: Contact, Qualification, Meeting & Attribution -->
                <div>
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
                            <?php if ($lead->notes) : ?>
                                <tr><td>Notes:</td><td><?php echo nl2br(esc_html($lead->notes)); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <!-- Consultation / Video Meeting Card -->
                    <div class="rl-detail-card">
                        <h3 class="rl-detail-title">Consultation & Video Meeting</h3>
                        <?php if (! empty($meeting['meet_url'])) : ?>
                            <div style="margin-bottom: 14px;">
                                <a href="<?php echo esc_url($meeting['meet_url']); ?>" target="_blank" class="rl-meet-btn" style="padding: 7px 14px; font-size: 13px;">
                                    <?php echo $this->iconVideo(); ?> Join Google Meet Room
                                </a>
                            </div>
                        <?php endif; ?>
                        <table class="rl-key-value-table">
                            <tr><td>Meeting Status:</td><td><span class="rl-badge rl-badge-<?php echo esc_attr($lead->status); ?>"><span class="rl-status-dot"></span><?php echo esc_html(ucfirst($lead->status)); ?></span></td></tr>
                            <?php if (! empty($meeting['meeting_id'])) : ?>
                                <tr><td>Meeting ID:</td><td><code><?php echo esc_html($meeting['meeting_id']); ?></code></td></tr>
                            <?php endif; ?>
                            <?php if (! empty($meeting['provider'])) : ?>
                                <tr><td>Provider:</td><td><?php echo esc_html(strtoupper($meeting['provider'])); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <!-- Attribution & UTM Data Card -->
                    <div class="rl-detail-card">
                        <h3 class="rl-detail-title">Attribution & UTM Data</h3>
                        <table class="rl-key-value-table">
                            <tr><td>Source:</td><td><strong><?php echo esc_html($lead->utm_source ?: '—'); ?></strong></td></tr>
                            <tr><td>Medium:</td><td><?php echo esc_html($lead->utm_medium ?: '—'); ?></td></tr>
                            <tr><td>Campaign:</td><td><?php echo esc_html($lead->utm_campaign ?: '—'); ?></td></tr>
                            <tr><td>Term / Content:</td><td><?php echo esc_html(trim(($lead->utm_term ?: '').' '.($lead->utm_content ?: '')) ?: '—'); ?></td></tr>
                            <tr><td>Source Type:</td><td><span class="rl-badge" style="background:#f4f4f5; color:#52525b;"><?php echo esc_html($lead->source_type ?: 'organic'); ?></span></td></tr>
                            <?php if ($lead->referral_code) : ?>
                                <tr><td>Referral Code:</td><td><code><?php echo esc_html($lead->referral_code); ?></code></td></tr>
                            <?php endif; ?>
                            <?php if ($lead->gclid) : ?>
                                <tr><td>Google Click ID:</td><td><code><?php echo esc_html($lead->gclid); ?></code></td></tr>
                            <?php endif; ?>
                            <?php if ($lead->fbclid) : ?>
                                <tr><td>Facebook Click ID:</td><td><code><?php echo esc_html($lead->fbclid); ?></code></td></tr>
                            <?php endif; ?>
                            <?php if ($lead->landing_url) : ?>
                                <tr><td>Landing URL:</td><td style="word-break: break-all; font-size: 11px;"><?php echo esc_html($lead->landing_url); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Right Column: Dual-Logging Execution & Integration Timeline -->
                <div>
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
                                <?php echo esc_html((string) $lead->activityLogs->count()); ?> events
                            </span>
                        </div>

                        <?php if ($lead->activityLogs->isEmpty()) : ?>
                            <p style="color: #a1a1aa; font-size: 13px;">No activity logged yet for this lead.</p>
                        <?php else : ?>
                            <div class="rl-timeline">
                                <?php foreach ($lead->activityLogs as $log) : ?>
                                    <div class="rl-timeline-item">
                                        <div class="rl-timeline-dot dot-<?php echo esc_attr($log->outcome); ?>"></div>
                                        <div class="rl-timeline-content">
                                            <div class="rl-timeline-meta">
                                                <span style="font-weight: 600; color: #09090b; font-size: 12px;"><?php echo esc_html($log->actor_domain); ?></span>
                                                <span class="rl-badge rl-badge-<?php echo esc_attr($log->stage); ?>"><?php echo esc_html(strtoupper($log->stage)); ?></span>
                                                <span class="rl-badge rl-badge-<?php echo esc_attr($log->outcome); ?>"><span class="rl-status-dot"></span><?php echo esc_html($log->outcome); ?></span>
                                                <span style="color: #a1a1aa; margin-left: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px;">
                                                    <?php echo esc_html($log->created_at?->format('H:i:s')); ?> (<?php echo esc_html($log->created_at?->diffForHumans()); ?>)
                                                </span>
                                            </div>
                                            <div style="font-size: 13px; font-weight: 500; color: #09090b; margin-bottom: 4px;">
                                                <?php echo esc_html($log->description); ?>
                                            </div>
                                            <?php if (! empty($log->payload)) : ?>
                                                <details style="margin-top: 6px;">
                                                    <summary style="font-size: 11px; color: #71717a; cursor: pointer; font-weight: 500;">View Event Payload JSON</summary>
                                                    <div class="rl-json-box"><?php echo esc_html(json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

        $logs = $query->paginate(30);

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
                        <option value="Lead" <?php selected($domainFilter, 'Lead'); ?>>Lead</option>
                        <option value="Scheduling" <?php selected($domainFilter, 'Scheduling'); ?>>Scheduling</option>
                        <option value="Tracking" <?php selected($domainFilter, 'Tracking'); ?>>Tracking</option>
                    </select>

                    <select name="outcome" class="rl-select">
                        <option value="">All Outcomes</option>
                        <option value="succeeded" <?php selected($outcomeFilter, 'succeeded'); ?>>Succeeded</option>
                        <option value="failed" <?php selected($outcomeFilter, 'failed'); ?>>Failed</option>
                    </select>

                    <button type="submit" class="rl-btn rl-btn-primary">Filter Logs</button>
                    <?php if ($search || $stageFilter || $domainFilter || $outcomeFilter) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads-activity')); ?>" class="rl-btn rl-btn-outline">Reset</a>
                    <?php endif; ?>
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
                        <?php if ($logs->isEmpty()) : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 48px; color: #a1a1aa;">
                                    No audit logs found matching criteria.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td style="white-space: nowrap;">
                                        <div style="font-weight: 600; color: #09090b;"><?php echo esc_html($log->created_at?->format('M j, Y')); ?></div>
                                        <div style="font-size: 11px; color: #71717a;" title="<?php echo esc_attr($log->created_at?->toDateTimeString()); ?>">
                                            <?php echo esc_html($log->created_at?->format('H:i:s')); ?> (<?php echo esc_html($log->created_at?->diffForHumans()); ?>)
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log->lead) : ?>
                                            <div class="rl-contact-cell">
                                                <div class="rl-avatar-initials"><?php echo esc_html($this->getInitials($log->lead->name, $log->lead->email)); ?></div>
                                                <div class="rl-contact-info">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&view_lead='.$log->lead_id)); ?>" class="rl-contact-name" style="text-decoration: none;">
                                                        <?php echo esc_html($log->lead->name ?: $log->lead->email); ?>
                                                    </a>
                                                    <span style="font-size: 11px; color: #71717a;"><?php echo esc_html($log->lead->email); ?></span>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <span style="color: #a1a1aa; font-size: 12px;">Lead #<?php echo esc_html((string) $log->lead_id); ?> (deleted)</span>
                                        <?php endif; ?>
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
                                        <?php if (! empty($log->payload)) : ?>
                                            <details>
                                                <summary style="font-size: 11px; color: #71717a; cursor: pointer; font-weight: 500;">JSON</summary>
                                                <div class="rl-json-box"><?php echo esc_html(json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                            </details>
                                        <?php else : ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #71717a;">
                    Page <strong><?php echo esc_html((string) $logs->currentPage()); ?></strong> of <strong><?php echo esc_html((string) $logs->lastPage()); ?></strong>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($logs->previousPageUrl()) : ?>
                        <a href="<?php echo esc_url($logs->previousPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm"><?php echo $this->iconArrowLeft(); ?> Previous</a>
                    <?php endif; ?>
                    <?php if ($logs->nextPageUrl()) : ?>
                        <a href="<?php echo esc_url($logs->nextPageUrl()); ?>" class="rl-btn rl-btn-outline rl-btn-sm">Next <?php echo $this->iconArrowRight(); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderDiagnostics(): void
    {
        $apiKey = config('services.calendly.api_key');
        $t10 = config('services.calendly.t10_event_type');
        $t0 = config('services.calendly.t0_event_type');

        $t10Status = 'Unknown';
        $t10Name = '';
        $t0Status = 'Unknown';
        $t0Name = '';

        if ($apiKey) {
            try {
                $r1 = Http::withToken($apiKey)->get($t10);
                $t10Status = $r1->successful() ? 'Active (200 OK)' : 'Status: '.$r1->status();
                $t10Name = $r1->json('resource.name', '');

                $r2 = Http::withToken($apiKey)->get($t0);
                $t0Status = $r2->successful() ? 'Active (200 OK)' : 'Status: '.$r2->status();
                $t0Name = $r2->json('resource.name', '');
            } catch (\Throwable $e) {
                $t10Status = 'Connection error: '.$e->getMessage();
            }
        }

        $cutoff = Carbon::now()->subDays(30);
        $staleLeadsCount = Lead::where('created_at', '<', $cutoff)->count();

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

            <!-- Segmented Navigation Tabs -->
            <?php $this->renderAdminNavigation('diagnostics'); ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Calendly Connection -->
                <div class="rl-detail-card">
                    <h3 class="rl-detail-title">Calendly Direct API Connection</h3>
                    <table class="rl-key-value-table">
                        <tr>
                            <td style="width: 170px;">API Key Configured:</td>
                            <td>
                                <?php if ($apiKey) : ?>
                                    <span class="rl-badge rl-badge-succeeded"><span class="rl-status-dot"></span>Configured (PAT)</span>
                                <?php else : ?>
                                    <span class="rl-badge rl-badge-failed"><span class="rl-status-dot"></span>Missing Key</span>
                                <?php endif; ?>
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
        </div>
        <?php
    }

    protected function renderAdminNavigation(string $activeTab): void
    {
        $tabs = [
            'leads' => ['label' => 'All Leads & Submissions', 'url' => admin_url('admin.php?page=rl-leads')],
            'activity' => ['label' => 'Live Activity & Audit Logs', 'url' => admin_url('admin.php?page=rl-leads-activity')],
            'diagnostics' => ['label' => 'Diagnostics & Health', 'url' => admin_url('admin.php?page=rl-leads-diagnostics')],
        ];
        ?>
        <div class="rl-tabs">
            <?php foreach ($tabs as $key => $tab) : ?>
                <a href="<?php echo esc_url($tab['url']); ?>" class="rl-tab <?php echo $activeTab === $key ? 'rl-tab-active' : ''; ?>">
                    <?php echo esc_html($tab['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    protected function getInitials(?string $name, ?string $email): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name);
            if (count($parts) >= 2) {
                return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
            }
            return strtoupper(mb_substr($name, 0, 2));
        }
        if ($email) {
            return strtoupper(mb_substr($email, 0, 2));
        }
        return 'RL';
    }

    protected function iconUsers(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    }

    protected function iconCalendar(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="m9 16 2 2 4-4"/></svg>';
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

    protected function extractMeetingDetails(Lead $lead): array
    {
        $log = $lead->activityLogs
            ? $lead->activityLogs->first(fn ($l) => $l->actor_domain === 'Scheduling' && $l->outcome === 'succeeded')
            : null;

        return $log?->payload ?? [];
    }
}
