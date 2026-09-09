<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Referral\Actions\ProcessPayoutAction;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralSettingsService;

class ReferralAdminDashboard
{
    protected const REFERRAL_STATUSES = ['pending', 'qualified', 'fulfilled', 'rewarded', 'rejected'];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_init', [$this, 'handleAdminActions']);
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            page_title: 'Referrers & Referrals',
            menu_title: 'Referrers',
            capability: 'manage_options',
            menu_slug: 'rl-referrers',
            callback: [$this, 'renderReferrers'],
            icon_url: 'dashicons-groups',
            position: 31
        );

        add_submenu_page(
            parent_slug: 'rl-referrers',
            page_title: 'All Referrers',
            menu_title: 'All Referrers',
            capability: 'manage_options',
            menu_slug: 'rl-referrers',
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

        $exportUrl = wp_nonce_url(admin_url('admin.php?page=rl-referrers&rl_action=export_referrers_csv'), 'rl_export_referrers_nonce');

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Referrers</h1>
            <a href="<?php echo esc_url($exportUrl); ?>" class="page-title-action">Export CSV</a>
            <?php $this->renderNav('rl-referrers'); ?>

            <form method="get">
                <input type="hidden" name="page" value="rl-referrers" />
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email, or referral code" />
                    <input type="submit" class="button" value="Search Referrers" />
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
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
                        <tr><td colspan="8">No referrers found.</td></tr>
                    <?php } else { ?>
                        <?php foreach ($referrers as $referrer) { ?>
                            <tr>
                                <td><strong><?php echo esc_html($referrer->name); ?></strong></td>
                                <td><?php echo esc_html($referrer->email); ?></td>
                                <td><code><?php echo esc_html($referrer->referral_code); ?></code></td>
                                <td><?php echo esc_html($referrer->company ?: '—'); ?></td>
                                <td><?php echo esc_html(ucfirst($referrer->status)); ?></td>
                                <td><?php echo esc_html((string) $referrer->referrals_count); ?></td>
                                <td><?php echo $referrer->stripe_account_id ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html($referrer->created_at?->format('M j, Y') ?? ''); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

            <?php $this->renderPagination($total, $perPage, $page, 'rl-referrers'); ?>
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
        <div class="wrap">
            <h1 class="wp-heading-inline">Referrals</h1>
            <?php $this->renderNav('rl-referrers-referrals'); ?>

            <?php if (! empty($_GET['status_updated'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Referral status updated.</p></div>
            <?php } ?>

            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=rl-referrers-referrals')); ?>" class="<?php echo $statusFilter === '' ? 'current' : ''; ?>">All</a> |</li>
                <?php foreach (self::REFERRAL_STATUSES as $i => $status) { ?>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-referrers-referrals&status='.$status)); ?>" class="<?php echo $statusFilter === $status ? 'current' : ''; ?>">
                            <?php echo esc_html(ucfirst($status)); ?>
                        </a><?php echo $i < count(self::REFERRAL_STATUSES) - 1 ? ' |' : ''; ?>
                    </li>
                <?php } ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Referrer</th>
                        <th>Lead Name</th>
                        <th>Lead Email</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Update Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($referrals->isEmpty()) { ?>
                        <tr><td colspan="7">No referrals found.</td></tr>
                    <?php } else { ?>
                        <?php foreach ($referrals as $referral) { ?>
                            <tr>
                                <td><?php echo esc_html($referral->referrer?->name ?? '—'); ?></td>
                                <td><?php echo esc_html($referral->lead_name ?: '—'); ?></td>
                                <td><?php echo esc_html($referral->lead_email ?: '—'); ?></td>
                                <td><?php echo esc_html($referral->source ?: '—'); ?></td>
                                <td><?php echo esc_html(ucfirst($referral->status)); ?></td>
                                <td><?php echo esc_html($referral->created_at?->format('M j, Y') ?? ''); ?></td>
                                <td>
                                    <form method="post" style="display:flex;gap:4px;">
                                        <?php wp_nonce_field('rl_update_referral_status_nonce'); ?>
                                        <input type="hidden" name="rl_action" value="update_referral_status" />
                                        <input type="hidden" name="referral_id" value="<?php echo esc_attr((string) $referral->id); ?>" />
                                        <select name="new_status">
                                            <?php foreach (self::REFERRAL_STATUSES as $status) { ?>
                                                <option value="<?php echo esc_attr($status); ?>" <?php selected($referral->status, $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                                            <?php } ?>
                                        </select>
                                        <button type="submit" class="button button-small">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

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
        <div class="wrap">
            <h1 class="wp-heading-inline">Rewards & Payouts</h1>
            <?php $this->renderNav('rl-referrers-rewards'); ?>

            <?php if (! empty($_GET['reward_issued'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Reward marked as issued.</p></div>
            <?php } ?>
            <?php if (! empty($_GET['payout_sent'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Payout processed. Rewards with a successful transfer were marked issued.</p></div>
            <?php } ?>

            <p>
                <strong>Due:</strong> $<?php echo esc_html(number_format($dueTotal, 2)); ?>
                &nbsp;|&nbsp;
                <strong>Issued:</strong> $<?php echo esc_html(number_format($issuedTotal, 2)); ?>
            </p>

            <?php if ($referrersWithDue->isNotEmpty()) { ?>
                <h2>Send Stripe Payout</h2>
                <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:20px;">
                    <?php wp_nonce_field('rl_send_payout_nonce'); ?>
                    <input type="hidden" name="rl_action" value="send_payout" />
                    <select name="referrer_id">
                        <?php foreach ($referrersWithDue as $referrer) { ?>
                            <option value="<?php echo esc_attr((string) $referrer->id); ?>"><?php echo esc_html($referrer->name.' ('.$referrer->referral_code.')'); ?></option>
                        <?php } ?>
                    </select>
                    <button type="submit" class="button button-primary">Send Payout for Due Rewards</button>
                </form>
            <?php } ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Referrer</th>
                        <th>Referral</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rewards->isEmpty()) { ?>
                        <tr><td colspan="7">No rewards found.</td></tr>
                    <?php } else { ?>
                        <?php foreach ($rewards as $reward) { ?>
                            <tr>
                                <td><?php echo esc_html($reward->referral?->referrer?->name ?? '—'); ?></td>
                                <td>#<?php echo esc_html((string) $reward->referral_id); ?></td>
                                <td><?php echo esc_html(ucfirst($reward->reward_type)); ?></td>
                                <td>$<?php echo esc_html(number_format((float) $reward->amount, 2)); ?> <?php echo esc_html($reward->currency); ?></td>
                                <td><?php echo esc_html(ucfirst($reward->status)); ?></td>
                                <td><?php echo esc_html($reward->created_at?->format('M j, Y') ?? ''); ?></td>
                                <td>
                                    <?php if ($reward->status === 'due') { ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-referrers-rewards&rl_action=issue_reward&reward_id='.$reward->id), 'rl_issue_reward_nonce')); ?>" class="button button-small">Mark Issued</a>
                                    <?php } else { ?>
                                        &mdash;
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

            <?php $this->renderPagination($total, $perPage, $page, 'rl-referrers-rewards'); ?>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        $settingsService = app(ReferralSettingsService::class);
        $settings = $settingsService->get();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Referral Program Settings</h1>
            <?php $this->renderNav('rl-referrers-settings'); ?>

            <?php if (! empty($_GET['settings_saved'])) { ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php } elseif (! empty($_GET['settings_error'])) { ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['settings_error'])); ?></p></div>
            <?php } ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-referrers-settings')); ?>">
                <?php wp_nonce_field('rl_save_referral_settings_nonce'); ?>
                <input type="hidden" name="rl_action" value="save_referral_settings" />

                <h2>Default Reward</h2>
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

                <h2>Attribution</h2>
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

                <h2>Landing Pages</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="landing_pages">Referral link destinations</label></th>
                        <td>
                            <textarea id="landing_pages" name="landing_pages" rows="6" class="large-text code"><?php echo esc_textarea($settingsService->landingPagesToText($settings['landing_pages'])); ?></textarea>
                            <p class="description">One per line, format: <code>Name = https://url</code>. Populates the link generator in the Referrer Portal.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
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
            'rl-referrers' => 'All Referrers',
            'rl-referrers-referrals' => 'Referrals',
            'rl-referrers-rewards' => 'Rewards & Payouts',
            'rl-referrers-settings' => 'Settings',
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $class = $slug === $activeSlug ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="'.esc_url(admin_url('admin.php?page='.$slug)).'" class="'.esc_attr($class).'">'.esc_html($label).'</a>';
        }
        echo '</h2>';
    }

    protected function renderPagination(int $total, int $perPage, int $currentPage, string $pageSlug): void
    {
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages <= 1) {
            return;
        }

        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $url = admin_url('admin.php?page='.$pageSlug.'&paged='.$i);
            $class = $i === $currentPage ? 'page-numbers current' : 'page-numbers';
            echo '<a class="'.esc_attr($class).'" href="'.esc_url($url).'">'.esc_html((string) $i).'</a> ';
        }
        echo '</div></div>';
    }
}
