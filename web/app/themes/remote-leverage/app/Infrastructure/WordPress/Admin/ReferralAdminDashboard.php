<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Referral\Actions\FulfillReferralAction;
use App\Domains\Referral\Actions\ProcessPayoutAction;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Support\Facades\Cache;

class ReferralAdminDashboard
{
    protected const REFERRAL_STATUSES = ['pending', 'qualified', 'fulfilled', 'rewarded', 'rejected'];

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
        if (str_starts_with((string) $page, 'rl-referrers')) {
            $classes .= ' rl-referrers-screen';
        }

        return $classes;
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            page_title: 'Referral Program',
            menu_title: 'Referrers',
            capability: 'manage_options',
            menu_slug: 'rl-referrers',
            callback: [$this, 'renderAnalytics'],
            icon_url: 'dashicons-groups',
            position: 31
        );

        add_submenu_page(
            parent_slug: 'rl-referrers',
            page_title: 'Analytics Overview',
            menu_title: 'Analytics',
            capability: 'manage_options',
            menu_slug: 'rl-referrers',
            callback: [$this, 'renderAnalytics']
        );

        add_submenu_page(
            parent_slug: 'rl-referrers',
            page_title: 'All Referrers',
            menu_title: 'All Referrers',
            capability: 'manage_options',
            menu_slug: 'rl-referrers-list',
            callback: [$this, 'renderReferrers']
        );

        add_submenu_page(
            parent_slug: 'rl-referrers',
            page_title: 'Referrals',
            menu_title: 'Referrals',
            capability: 'manage_options',
            menu_slug: 'rl-referrers-referrals',
            callback: [$this, 'renderReferrals']
        );

        add_submenu_page(
            parent_slug: 'rl-referrers',
            page_title: 'Rewards & Payouts',
            menu_title: 'Rewards & Payouts',
            capability: 'manage_options',
            menu_slug: 'rl-referrers-rewards',
            callback: [$this, 'renderRewards']
        );

        add_submenu_page(
            parent_slug: 'rl-referrers',
            page_title: 'Referral Program Settings',
            menu_title: 'Settings',
            capability: 'manage_options',
            menu_slug: 'rl-referrers-settings',
            callback: [$this, 'renderSettings']
        );
    }

    public function handleAdminActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_REQUEST['rl_action'] ?? '');

        if ($action === 'export_referrers_csv') {
            check_admin_referer('rl_export_referrers_nonce');
            $this->exportReferrersCsv();
            exit;
        }

        if ($action === 'update_referral_status') {
            check_admin_referer('rl_update_referral_status_nonce');
            $referralId = absint($_POST['referral_id'] ?? 0);
            $newStatus = sanitize_text_field($_POST['new_status'] ?? '');
            $referral = Referral::find($referralId);

            if ($referral && in_array($newStatus, self::REFERRAL_STATUSES, true)) {
                $referral->update(['status' => $newStatus]);

                // The deal closing (fulfilled/rewarded) is what earns the reward — never
                // the earlier booking step. Matches legacy's update_referral_status().
                if (in_array($newStatus, ['fulfilled', 'rewarded'], true)) {
                    app(FulfillReferralAction::class)->execute($referral);
                }

                Cache::forget('rl_referral_analytics_metrics');
                wp_safe_redirect(admin_url('admin.php?page=rl-referrers-referrals&status_updated=1'));
                exit;
            }
        }

        if ($action === 'issue_reward') {
            check_admin_referer('rl_issue_reward_nonce');
            $rewardId = absint($_GET['reward_id'] ?? 0);
            $reward = ReferralReward::find($rewardId);

            if ($reward && $reward->status === 'due') {
                $reward->update(['status' => 'issued', 'issued_at' => now()]);
                Cache::forget('rl_referral_analytics_metrics');
                wp_safe_redirect(admin_url('admin.php?page=rl-referrers-rewards&reward_issued=1'));
                exit;
            }
        }

        if ($action === 'send_payout') {
            check_admin_referer('rl_send_payout_nonce');
            $referrerId = absint($_POST['referrer_id'] ?? 0);
            $referrer = Referrer::find($referrerId);

            if ($referrer) {
                $dueRewards = ReferralReward::where('referrer_id', $referrer->id)->where('status', 'due')->get();
                $total = (float) $dueRewards->sum('amount');

                if ($total > 0) {
                    $payout = app(ProcessPayoutAction::class)->execute(
                        $referrer,
                        $total,
                        $dueRewards->pluck('id')->all(),
                        'Batch payout of '.$dueRewards->count().' due reward(s).'
                    );

                    if ($payout && $payout->status === 'completed') {
                        ReferralReward::whereIn('id', $dueRewards->pluck('id'))->update([
                            'status' => 'issued',
                            'issued_at' => now(),
                        ]);
                    }
                }

                Cache::forget('rl_referral_analytics_metrics');
                wp_safe_redirect(admin_url('admin.php?page=rl-referrers-rewards&payout_sent=1'));
                exit;
            }
        }

        if ($action === 'save_referral_settings') {
            check_admin_referer('rl_save_referral_settings_nonce');

            $result = app(ReferralSettingsService::class)->save([
                'default_reward_amount' => $_POST['default_reward_amount'] ?? '',
                'default_reward_currency' => $_POST['default_reward_currency'] ?? '',
                'default_reward_type' => $_POST['default_reward_type'] ?? '',
                'cookie_days' => $_POST['cookie_days'] ?? '',
                'landing_pages' => wp_unslash($_POST['landing_pages'] ?? ''),
            ]);

            if ($result['success']) {
                wp_safe_redirect(admin_url('admin.php?page=rl-referrers-settings&settings_saved=1'));
            } else {
                wp_safe_redirect(admin_url('admin.php?page=rl-referrers-settings&settings_error='.urlencode(implode(' ', $result['errors']))));
            }
            exit;
        }
    }

    public function enqueueAdminStyles(string $hook): void
    {
        if (! str_contains($hook, 'rl-referrers')) {
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
            .rl-data-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 13px;
            }
            .rl-data-table th {
                background: #fafafa;
                padding: 12px 16px;
                font-weight: 600;
                color: #71717a;
                border-bottom: 1px solid #e4e4e7;
                font-size: 12px;
                text-transform: none;
                letter-spacing: -0.01em;
            }
            .rl-data-table td {
                padding: 14px 16px;
                border-bottom: 1px solid #f4f4f5;
                color: #09090b;
                vertical-align: middle;
            }
            .rl-data-table tr:hover td {
                background: #fafafa;
            }
            .rl-data-table tr:last-child td {
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
            .rl-badge-fulfilled .rl-status-dot,
            .rl-badge-rewarded .rl-status-dot,
            .rl-badge-issued .rl-status-dot,
            .rl-badge-active .rl-status-dot {
                background: #18181b;
            }
            .rl-badge-pending .rl-status-dot,
            .rl-badge-due .rl-status-dot,
            .rl-badge-qualified .rl-status-dot {
                background: #71717a;
            }
            .rl-badge-rejected .rl-status-dot,
            .rl-badge-inactive .rl-status-dot {
                background: #dc2626;
            }

            /* --- Detail Card --- */
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

            /* --- WordPress Notice Modernization (Shadcn Callout) --- */
            .rl-admin-wrap .notice,
            .wp-admin.rl-referrers-screen .notice {
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
            .wp-admin.rl-referrers-screen .notice-success {
                border-left-color: #18181b !important;
            }
            .rl-admin-wrap .notice-error,
            .wp-admin.rl-referrers-screen .notice-error {
                border-left-color: #dc2626 !important;
            }
            .rl-admin-wrap .notice-info,
            .wp-admin.rl-referrers-screen .notice-info {
                border-left-color: #71717a !important;
            }
        ');
    }

    public function renderAnalytics(): void
    {
        $m = $this->getAnalyticsMetrics();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Referral Analytics</h1>
                    <p class="rl-admin-subtitle">
                        Referrer reach, pipeline conversion, and reward payout tracking.
                    </p>
                </div>
            </div>

            <?php $this->renderNav('rl-referrers'); ?>

            <div class="rl-stats-grid">
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Total Referrers</span>
                        <?php echo $this->iconUsers(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $m['totalReferrers']); ?></div>
                    <div class="rl-card-subtext">Registered partners</div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Total Reach</span>
                        <?php echo $this->iconEye(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $m['totalReach']); ?></div>
                    <div class="rl-card-subtext">Clicks recorded</div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Total Referrals</span>
                        <?php echo $this->iconShare(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $m['totalReferrals']); ?></div>
                    <div class="rl-card-subtext">Leads attributed to referrers</div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Reach &rarr; Lead Rate</span>
                        <?php echo $this->iconPercent(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $m['reachToLeadRate']); ?>%</div>
                    <div class="rl-card-subtext">Clicks that became a referral</div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Lead &rarr; Deal Rate</span>
                        <?php echo $this->iconCheckCircle(); ?>
                    </div>
                    <div class="rl-card-value"><?php echo esc_html((string) $m['leadToDealRate']); ?>%</div>
                    <div class="rl-card-subtext">Referrals that closed</div>
                </div>
            </div>

            <div class="rl-detail-card">
                <h3 class="rl-detail-title">Referral Pipeline</h3>
                <div class="rl-table-container">
                    <table class="rl-data-table">
                        <thead>
                            <tr><th>Pending</th><th>Qualified</th><th>Fulfilled / Rewarded</th><th>Rejected</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo esc_html((string) $m['pendingReferrals']); ?></td>
                                <td><?php echo esc_html((string) $m['qualifiedReferrals']); ?></td>
                                <td><?php echo esc_html((string) $m['fulfilledDeals']); ?></td>
                                <td><?php echo esc_html((string) $m['rejectedReferrals']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rl-detail-card">
                <h3 class="rl-detail-title">Rewards</h3>
                <div class="rl-table-container">
                    <table class="rl-data-table">
                        <thead>
                            <tr><th>Due (count)</th><th>Due ($)</th><th>Issued (count)</th><th>Issued ($)</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo esc_html((string) $m['rewardsDueCount']); ?></td>
                                <td>$<?php echo esc_html(number_format($m['rewardsDueSum'], 2)); ?></td>
                                <td><?php echo esc_html((string) $m['rewardsIssuedCount']); ?></td>
                                <td>$<?php echo esc_html(number_format($m['rewardsIssuedSum'], 2)); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rl-detail-card">
                <h3 class="rl-detail-title">Top 5 Referrers</h3>
                <div class="rl-table-container">
                    <table class="rl-data-table">
                        <thead>
                            <tr>
                                <th>Referrer</th>
                                <th>Reach</th>
                                <th>Total Leads</th>
                                <th>Qualified</th>
                                <th>Fulfilled Deals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($m['topReferrers'])) { ?>
                                <tr><td colspan="5" style="text-align: center; padding: 32px; color: #a1a1aa;">No referrals recorded yet.</td></tr>
                            <?php } else { ?>
                                <?php foreach ($m['topReferrers'] as $row) { ?>
                                    <tr>
                                        <td>
                                            <div class="rl-contact-cell">
                                                <div class="rl-avatar-initials"><?php echo esc_html($this->getInitials($row['name'], $row['email'])); ?></div>
                                                <div class="rl-contact-info">
                                                    <span class="rl-contact-name"><?php echo esc_html($row['name']); ?></span>
                                                    <span class="rl-contact-email"><?php echo esc_html($row['email']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html((string) $row['reach']); ?></td>
                                        <td><?php echo esc_html((string) $row['total_leads']); ?></td>
                                        <td><?php echo esc_html((string) $row['qualified_leads']); ?></td>
                                        <td><?php echo esc_html((string) $row['fulfilled_deals']); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rl-detail-card">
                <h3 class="rl-detail-title">Recent Referrals</h3>
                <div class="rl-table-container">
                    <table class="rl-data-table">
                        <thead>
                            <tr>
                                <th>Referrer</th>
                                <th>Lead</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($m['recentReferrals']->isEmpty()) { ?>
                                <tr><td colspan="4" style="text-align: center; padding: 32px; color: #a1a1aa;">No referrals recorded yet.</td></tr>
                            <?php } else { ?>
                                <?php foreach ($m['recentReferrals'] as $referral) { ?>
                                    <tr>
                                        <td><?php echo esc_html($referral->referrer?->name ?? 'Unknown'); ?></td>
                                        <td><?php echo esc_html($referral->lead_name ?: '—'); ?></td>
                                        <td>
                                            <span class="rl-badge rl-badge-<?php echo esc_attr($referral->status); ?>">
                                                <span class="rl-status-dot"></span>
                                                <?php echo esc_html(ucfirst($referral->status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($referral->created_at?->format('M j, Y H:i') ?? ''); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Aggregate referral analytics, cached briefly to avoid recomputing on every page load.
     * Ported from legacy RL_Admin_Analytics::get_metrics().
     *
     * @return array<string, mixed>
     */
    protected function getAnalyticsMetrics(): array
    {
        return Cache::remember('rl_referral_analytics_metrics', 180, function () {
            $totalReferrers = Referrer::count();
            $totalReach = ReferralClick::count();
            $totalReferrals = Referral::count();

            $qualifiedReferrals = Referral::where('status', 'qualified')->count();
            $fulfilledDeals = Referral::whereIn('status', ['fulfilled', 'rewarded'])->count();
            $pendingReferrals = Referral::where('status', 'pending')->count();
            $rejectedReferrals = Referral::where('status', 'rejected')->count();

            $rewardsDueCount = ReferralReward::where('status', 'due')->count();
            $rewardsDueSum = (float) ReferralReward::where('status', 'due')->sum('amount');
            $rewardsIssuedCount = ReferralReward::where('status', 'issued')->count();
            $rewardsIssuedSum = (float) ReferralReward::where('status', 'issued')->sum('amount');

            $reachToLeadRate = $totalReach > 0 ? round(($totalReferrals / $totalReach) * 100, 1) : 0.0;
            $leadToDealRate = $totalReferrals > 0 ? round(($fulfilledDeals / $totalReferrals) * 100, 1) : 0.0;

            $topReferrers = Referral::query()
                ->whereNotNull('referrer_id')
                ->selectRaw('referrer_id')
                ->selectRaw('COUNT(id) as total_leads')
                ->selectRaw("SUM(CASE WHEN status IN ('fulfilled', 'rewarded') THEN 1 ELSE 0 END) as fulfilled_deals")
                ->selectRaw("SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_leads")
                ->groupBy('referrer_id')
                ->orderByDesc('fulfilled_deals')
                ->orderByDesc('total_leads')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    $referrer = Referrer::find($row->referrer_id);

                    return [
                        'name' => $referrer->name ?? 'Deleted Referrer',
                        'email' => $referrer->email ?? '',
                        'reach' => ReferralClick::where('referrer_id', $row->referrer_id)->count(),
                        'total_leads' => (int) $row->total_leads,
                        'qualified_leads' => (int) $row->qualified_leads,
                        'fulfilled_deals' => (int) $row->fulfilled_deals,
                    ];
                })
                ->all();

            $recentReferrals = Referral::with('referrer')->latest()->take(8)->get();

            return compact(
                'totalReferrers', 'totalReach', 'totalReferrals',
                'qualifiedReferrals', 'fulfilledDeals', 'pendingReferrals', 'rejectedReferrals',
                'rewardsDueCount', 'rewardsDueSum', 'rewardsIssuedCount', 'rewardsIssuedSum',
                'reachToLeadRate', 'leadToDealRate', 'topReferrers', 'recentReferrals'
            );
        });
    }

    public function renderReferrers(): void
    {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $page = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;

        $query = Referrer::query()->withCount('referrals')->latest();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();
        $referrers = $query->forPage($page, $perPage)->get();

        $exportUrl = wp_nonce_url(admin_url('admin.php?page=rl-referrers-list&rl_action=export_referrers_csv'), 'rl_export_referrers_nonce');

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Referrers</h1>
                    <p class="rl-admin-subtitle">All registered referral partners and their performance.</p>
                </div>
                <div class="rl-actions-group">
                    <a href="<?php echo esc_url($exportUrl); ?>" class="rl-btn rl-btn-outline">
                        <?php echo $this->iconDownload(); ?> Export CSV
                    </a>
                </div>
            </div>

            <?php $this->renderNav('rl-referrers-list'); ?>

            <div class="rl-filter-bar">
                <form method="get" class="rl-filter-form">
                    <input type="hidden" name="page" value="rl-referrers-list" />
                    <div class="rl-search-wrapper">
                        <?php echo $this->iconSearch(); ?>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email, or referral code" class="rl-input-search" />
                    </div>
                    <button type="submit" class="rl-btn rl-btn-primary">Filter</button>
                    <?php if ($search) { ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-referrers-list')); ?>" class="rl-btn rl-btn-outline">Reset</a>
                    <?php } ?>
                </form>
                <div class="rl-count-badge">
                    Showing <strong><?php echo esc_html((string) count($referrers)); ?></strong> of <strong><?php echo esc_html((string) $total); ?></strong> referrers
                </div>
            </div>

            <div class="rl-table-container">
                <table class="rl-data-table">
                    <thead>
                        <tr>
                            <th>Referrer</th>
                            <th>Referral Code</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Referrals</th>
                            <th>Stripe Connected</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($referrers->isEmpty()) { ?>
                            <tr><td colspan="7" style="text-align: center; padding: 48px; color: #a1a1aa;">No referrers found.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($referrers as $referrer) { ?>
                                <tr>
                                    <td>
                                        <div class="rl-contact-cell">
                                            <div class="rl-avatar-initials"><?php echo esc_html($this->getInitials($referrer->name, $referrer->email)); ?></div>
                                            <div class="rl-contact-info">
                                                <span class="rl-contact-name"><?php echo esc_html($referrer->name); ?></span>
                                                <a href="mailto:<?php echo esc_attr($referrer->email); ?>" class="rl-contact-email"><?php echo esc_html($referrer->email); ?></a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?php echo esc_html($referrer->referral_code); ?></code></td>
                                    <td><?php echo esc_html($referrer->company ?: '—'); ?></td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($referrer->status); ?>">
                                            <span class="rl-status-dot"></span>
                                            <?php echo esc_html(ucfirst($referrer->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html((string) $referrer->referrals_count); ?></td>
                                    <td>
                                        <?php if ($referrer->stripe_account_id) { ?>
                                            <span class="rl-badge rl-badge-issued"><span class="rl-status-dot"></span>Yes</span>
                                        <?php } else { ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">No</span>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo esc_html($referrer->created_at?->format('M j, Y') ?? ''); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php $this->renderPagination($total, $perPage, $page, 'rl-referrers-list'); ?>
        </div>
        <?php
    }

    public function renderReferrals(): void
    {
        $statusFilter = sanitize_text_field($_GET['status'] ?? '');
        $page = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;

        $query = Referral::query()->with('referrer')->latest();
        if ($statusFilter !== '' && in_array($statusFilter, self::REFERRAL_STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        $total = (clone $query)->count();
        $referrals = $query->forPage($page, $perPage)->get();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Referrals</h1>
                    <p class="rl-admin-subtitle">Every lead attributed to a referral partner, from first click to closed deal.</p>
                </div>
            </div>

            <?php $this->renderNav('rl-referrers-referrals'); ?>

            <?php if (! empty($_GET['status_updated'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Referral status updated.</p></div>
            <?php } ?>

            <div class="rl-filter-bar">
                <form method="get" class="rl-filter-form">
                    <input type="hidden" name="page" value="rl-referrers-referrals" />
                    <select name="status" class="rl-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php foreach (self::REFERRAL_STATUSES as $status) { ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($statusFilter, $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                        <?php } ?>
                    </select>
                    <?php if ($statusFilter) { ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-referrers-referrals')); ?>" class="rl-btn rl-btn-outline">Reset</a>
                    <?php } ?>
                </form>
                <div class="rl-count-badge">
                    Showing <strong><?php echo esc_html((string) count($referrals)); ?></strong> of <strong><?php echo esc_html((string) $total); ?></strong> referrals
                </div>
            </div>

            <div class="rl-table-container">
                <table class="rl-data-table">
                    <thead>
                        <tr>
                            <th>Referrer</th>
                            <th>Lead</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($referrals->isEmpty()) { ?>
                            <tr><td colspan="6" style="text-align: center; padding: 48px; color: #a1a1aa;">No referrals found.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($referrals as $referral) { ?>
                                <tr>
                                    <td><?php echo esc_html($referral->referrer?->name ?? '—'); ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: #09090b;"><?php echo esc_html($referral->lead_name ?: '—'); ?></div>
                                        <?php if ($referral->lead_email) { ?>
                                            <div style="font-size: 11px; color: #71717a;"><?php echo esc_html($referral->lead_email); ?></div>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo esc_html($referral->source ?: '—'); ?></td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($referral->status); ?>">
                                            <span class="rl-status-dot"></span>
                                            <?php echo esc_html(ucfirst($referral->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($referral->created_at?->format('M j, Y') ?? ''); ?></td>
                                    <td>
                                        <form method="post" style="display: flex; gap: 6px; align-items: center;">
                                            <?php wp_nonce_field('rl_update_referral_status_nonce'); ?>
                                            <input type="hidden" name="rl_action" value="update_referral_status" />
                                            <input type="hidden" name="referral_id" value="<?php echo esc_attr((string) $referral->id); ?>" />
                                            <select name="new_status" class="rl-select">
                                                <?php foreach (self::REFERRAL_STATUSES as $status) { ?>
                                                    <option value="<?php echo esc_attr($status); ?>" <?php selected($referral->status, $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                                                <?php } ?>
                                            </select>
                                            <button type="submit" class="rl-btn rl-btn-outline rl-btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php $this->renderPagination($total, $perPage, $page, 'rl-referrers-referrals'); ?>
        </div>
        <?php
    }

    public function renderRewards(): void
    {
        $page = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;

        $query = ReferralReward::query()->with(['referral.referrer'])->latest('created_at');
        $total = (clone $query)->count();
        $rewards = $query->forPage($page, $perPage)->get();

        $dueTotal = (float) ReferralReward::where('status', 'due')->sum('amount');
        $issuedTotal = (float) ReferralReward::where('status', 'issued')->sum('amount');

        $referrersWithDue = Referrer::query()
            ->whereHas('referrals.rewards', fn ($q) => $q->where('status', 'due'))
            ->get();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Rewards &amp; Payouts</h1>
                    <p class="rl-admin-subtitle">Track earned rewards and send Stripe Connect payouts to referrers.</p>
                </div>
            </div>

            <?php $this->renderNav('rl-referrers-rewards'); ?>

            <?php if (! empty($_GET['reward_issued'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Reward marked as issued.</p></div>
            <?php } ?>
            <?php if (! empty($_GET['payout_sent'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Payout processed. Rewards with a successful transfer were marked issued.</p></div>
            <?php } ?>

            <div class="rl-stats-grid">
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Rewards Due</span>
                        <?php echo $this->iconAlert(); ?>
                    </div>
                    <div class="rl-card-value">$<?php echo esc_html(number_format($dueTotal, 2)); ?></div>
                    <div class="rl-card-subtext">Awaiting payout</div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-header">
                        <span class="rl-card-title">Rewards Issued</span>
                        <?php echo $this->iconCheckCircle(); ?>
                    </div>
                    <div class="rl-card-value">$<?php echo esc_html(number_format($issuedTotal, 2)); ?></div>
                    <div class="rl-card-subtext">Paid out to date</div>
                </div>
            </div>

            <?php if ($referrersWithDue->isNotEmpty()) { ?>
                <div class="rl-filter-bar">
                    <form method="post" class="rl-filter-form">
                        <?php wp_nonce_field('rl_send_payout_nonce'); ?>
                        <input type="hidden" name="rl_action" value="send_payout" />
                        <select name="referrer_id" class="rl-select">
                            <?php foreach ($referrersWithDue as $referrer) { ?>
                                <option value="<?php echo esc_attr((string) $referrer->id); ?>"><?php echo esc_html($referrer->name.' ('.$referrer->referral_code.')'); ?></option>
                            <?php } ?>
                        </select>
                        <button type="submit" class="rl-btn rl-btn-primary">Send Payout for Due Rewards</button>
                    </form>
                </div>
            <?php } ?>

            <div class="rl-table-container">
                <table class="rl-data-table">
                    <thead>
                        <tr>
                            <th>Referrer</th>
                            <th>Referral</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rewards->isEmpty()) { ?>
                            <tr><td colspan="7" style="text-align: center; padding: 48px; color: #a1a1aa;">No rewards found.</td></tr>
                        <?php } else { ?>
                            <?php foreach ($rewards as $reward) { ?>
                                <tr>
                                    <td><?php echo esc_html($reward->referral?->referrer?->name ?? '—'); ?></td>
                                    <td>#<?php echo esc_html((string) $reward->referral_id); ?></td>
                                    <td><?php echo esc_html(ucfirst($reward->reward_type)); ?></td>
                                    <td>$<?php echo esc_html(number_format((float) $reward->amount, 2)); ?> <?php echo esc_html($reward->currency); ?></td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($reward->status); ?>">
                                            <span class="rl-status-dot"></span>
                                            <?php echo esc_html(ucfirst($reward->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($reward->created_at?->format('M j, Y') ?? ''); ?></td>
                                    <td style="text-align: right;">
                                        <?php if ($reward->status === 'due') { ?>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-referrers-rewards&rl_action=issue_reward&reward_id='.$reward->id), 'rl_issue_reward_nonce')); ?>" class="rl-btn rl-btn-outline rl-btn-sm">Mark Issued</a>
                                        <?php } else { ?>
                                            <span style="color: #a1a1aa; font-size: 11px;">&mdash;</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php $this->renderPagination($total, $perPage, $page, 'rl-referrers-rewards'); ?>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        $settingsService = app(ReferralSettingsService::class);
        $settings = $settingsService->get();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Referral Program Settings</h1>
                    <p class="rl-admin-subtitle">Default reward terms, attribution window, and referral link destinations.</p>
                </div>
            </div>

            <?php $this->renderNav('rl-referrers-settings'); ?>

            <?php if (! empty($_GET['settings_saved'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php } elseif (! empty($_GET['settings_error'])) { ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['settings_error'])); ?></p></div>
            <?php } ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-referrers-settings')); ?>">
                <?php wp_nonce_field('rl_save_referral_settings_nonce'); ?>
                <input type="hidden" name="rl_action" value="save_referral_settings" />

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Default Reward</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="default_reward_amount">Amount</label></th>
                            <td>
                                <input type="number" step="0.01" min="0.01" id="default_reward_amount" name="default_reward_amount" class="small-text"
                                    value="<?php echo esc_attr((string) $settings['default_reward_amount']); ?>" />
                                <p class="description">Auto-generated for every referral fulfilled by a completed booking.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="default_reward_currency">Currency</label></th>
                            <td>
                                <input type="text" id="default_reward_currency" name="default_reward_currency" class="small-text" maxlength="3"
                                    value="<?php echo esc_attr($settings['default_reward_currency']); ?>" />
                                <p class="description">3-letter ISO code, e.g. USD.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="default_reward_type">Type</label></th>
                            <td>
                                <input type="text" id="default_reward_type" name="default_reward_type" class="regular-text"
                                    value="<?php echo esc_attr($settings['default_reward_type']); ?>" />
                                <p class="description">e.g. "cash" or "credit". Descriptive only — payouts always transfer via Stripe Connect.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Attribution</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="cookie_days">Cookie lifetime (days)</label></th>
                            <td>
                                <input type="number" min="1" id="cookie_days" name="cookie_days" class="small-text"
                                    value="<?php echo esc_attr((string) $settings['cookie_days']); ?>" />
                                <p class="description">How long the <code>rl_referrer</code> attribution cookie persists after a referrer link is visited.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rl-detail-card" style="margin-bottom: 20px;">
                    <h3 class="rl-detail-title">Landing Pages</h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="landing_pages">Referral link destinations</label></th>
                            <td>
                                <textarea id="landing_pages" name="landing_pages" rows="6" class="large-text code"><?php echo esc_textarea($settingsService->landingPagesToText($settings['landing_pages'])); ?></textarea>
                                <p class="description">One per line, format: <code>Name = https://url</code>. Populates the link generator in the Referrer Portal.</p>
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

    protected function exportReferrersCsv(): void
    {
        $filename = 'remoteleverage-referrers-'.gmdate('Y-m-d-His').'.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Email', 'Referral Code', 'Company', 'Status', 'Stripe Account', 'Referrals', 'Joined (UTC)']);

        Referrer::query()->withCount('referrals')->orderBy('id')->chunk(100, function ($referrers) use ($output) {
            foreach ($referrers as $referrer) {
                fputcsv($output, [
                    $referrer->id,
                    $referrer->name,
                    $referrer->email,
                    $referrer->referral_code,
                    $referrer->company,
                    $referrer->status,
                    $referrer->stripe_account_id,
                    $referrer->referrals_count,
                    $referrer->created_at?->toDateTimeString(),
                ]);
            }
        });

        fclose($output);
    }

    protected function renderNav(string $activeSlug): void
    {
        $tabs = [
            'rl-referrers' => 'Analytics',
            'rl-referrers-list' => 'All Referrers',
            'rl-referrers-referrals' => 'Referrals',
            'rl-referrers-rewards' => 'Rewards & Payouts',
            'rl-referrers-settings' => 'Settings',
        ];
        ?>
        <div class="rl-tabs">
            <?php foreach ($tabs as $slug => $label) { ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page='.$slug)); ?>" class="rl-tab <?php echo $activeSlug === $slug ? 'rl-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php } ?>
        </div>
        <?php
    }

    protected function renderPagination(int $total, int $perPage, int $currentPage, string $pageSlug): void
    {
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages <= 1) {
            return;
        }

        echo '<div style="margin-top: 16px; display: flex; gap: 6px; flex-wrap: wrap;">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $url = admin_url('admin.php?page='.$pageSlug.'&paged='.$i);
            $class = $i === $currentPage ? 'rl-btn rl-btn-primary rl-btn-sm' : 'rl-btn rl-btn-outline rl-btn-sm';
            echo '<a class="'.esc_attr($class).'" href="'.esc_url($url).'">'.esc_html((string) $i).'</a>';
        }
        echo '</div>';
    }

    protected function getInitials(?string $name, ?string $email): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name);
            if (count($parts) >= 2) {
                return strtoupper(mb_substr($parts[0], 0, 1).mb_substr(end($parts), 0, 1));
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

    protected function iconEye(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    }

    protected function iconShare(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>';
    }

    protected function iconPercent(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>';
    }

    protected function iconCheckCircle(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
    }

    protected function iconAlert(): string
    {
        return '<svg class="rl-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>';
    }

    protected function iconSearch(): string
    {
        return '<svg class="rl-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>';
    }

    protected function iconDownload(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
    }
}
