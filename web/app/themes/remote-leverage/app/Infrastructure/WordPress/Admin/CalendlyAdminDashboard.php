<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use App\Domains\Scheduling\Services\CalendlyEventTypeDiscoveryService;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;

class CalendlyAdminDashboard
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
        add_action('admin_init', [$this, 'handleAdminActions']);
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            page_title: 'Calendly Integration',
            menu_title: 'Calendly',
            capability: 'manage_options',
            menu_slug: 'rl-calendly',
            callback: [$this, 'renderTokenPool'],
            icon_url: 'dashicons-calendar-alt',
            position: 31,
        );

        add_submenu_page(
            parent_slug: 'rl-calendly',
            page_title: 'Token Pool',
            menu_title: 'Token Pool',
            capability: 'manage_options',
            menu_slug: 'rl-calendly',
            callback: [$this, 'renderTokenPool'],
        );

        add_submenu_page(
            parent_slug: 'rl-calendly',
            page_title: 'Event Types',
            menu_title: 'Event Types',
            capability: 'manage_options',
            menu_slug: 'rl-calendly-event-types',
            callback: [$this, 'renderEventTypes'],
        );
    }

    public function handleAdminActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_REQUEST['rl_action'] ?? '');

        if ($action === 'save_calendly_tokens') {
            check_admin_referer('rl_save_calendly_tokens_nonce');

            $rows = [];
            foreach ((array) ($_POST['tokens'] ?? []) as $row) {
                $rows[] = [
                    'label' => sanitize_text_field($row['label'] ?? ''),
                    'token' => trim((string) ($row['token'] ?? '')),
                    'enabled' => ! empty($row['enabled']),
                ];
            }

            app(CalendlyTokenPool::class)->replaceAll($rows);
            wp_safe_redirect(admin_url('admin.php?page=rl-calendly&tokens_saved=1'));
            exit;
        }

        if ($action === 'toggle_calendly_token') {
            check_admin_referer('rl_toggle_calendly_token_nonce');
            $index = absint($_GET['index'] ?? -1);
            $enabled = ($_GET['enabled'] ?? '') === '1';
            app(CalendlyTokenPool::class)->setEnabled($index, $enabled);
            wp_safe_redirect(admin_url('admin.php?page=rl-calendly&token_toggled=1'));
            exit;
        }

        if ($action === 'remove_calendly_token') {
            check_admin_referer('rl_remove_calendly_token_nonce');
            $index = absint($_GET['index'] ?? -1);
            app(CalendlyTokenPool::class)->removeToken($index);
            wp_safe_redirect(admin_url('admin.php?page=rl-calendly&token_removed=1'));
            exit;
        }

        if ($action === 'reset_calendly_token_health') {
            check_admin_referer('rl_reset_calendly_token_health_nonce');
            $index = absint($_GET['index'] ?? -1);
            $rows = app(CalendlyTokenPool::class)->allRows();
            if (isset($rows[$index]['token'])) {
                app(CalendlyTokenPool::class)->clearFailures($rows[$index]['token']);
            }
            wp_safe_redirect(admin_url('admin.php?page=rl-calendly&health_reset=1'));
            exit;
        }

        if ($action === 'refresh_calendly_event_types') {
            check_admin_referer('rl_refresh_calendly_event_types_nonce');
            $discovered = app(CalendlyEventTypeDiscoveryService::class)->discoverEventTypes();
            wp_safe_redirect(admin_url('admin.php?page=rl-calendly-event-types&refreshed=1&count='.count($discovered)));
            exit;
        }

        if ($action === 'save_calendly_event_type_roles') {
            check_admin_referer('rl_save_calendly_event_type_roles_nonce');

            $map = [];
            foreach (CalendlyEventTypeRoleResolver::ROLES as $role) {
                $value = trim((string) ($_POST[$role] ?? ''));
                if ($value !== '') {
                    $map[$role] = esc_url_raw($value);
                }
            }

            app(CalendlyEventTypeRoleResolver::class)->save($map);
            wp_safe_redirect(admin_url('admin.php?page=rl-calendly-event-types&roles_saved=1'));
            exit;
        }
    }

    public function renderTokenPool(): void
    {
        $pool = app(CalendlyTokenPool::class);
        $rows = $pool->allRows();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Calendly Token Pool</h1>
                    <p class="rl-admin-subtitle">Manage the pooled Calendly personal access tokens used for booking failover and rate-limit rotation.</p>
                </div>
            </div>

            <?php if (! empty($_GET['tokens_saved'])) { ?>
                <div class="notice notice-success"><p>Token pool saved.</p></div>
            <?php } ?>
            <?php if (! empty($_GET['health_reset'])) { ?>
                <div class="notice notice-success"><p>Token health reset.</p></div>
            <?php } ?>

            <div class="rl-detail-card">
                <h2 class="rl-detail-title">Health</h2>
                <table class="widefat rl-key-value-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Token</th>
                            <th>Enabled</th>
                            <th>Rate Limited</th>
                            <th>Circuit Breaker</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $index => $row) {
                            $failures = $pool->failureCount($row['token'], 'metadata');
                            $open = $pool->isCircuitOpen($row['token'], 'metadata');
                            $limited = $pool->isRateLimited($row['token']);
                            ?>
                            <tr>
                                <td><?php echo esc_html($row['label']); ?></td>
                                <td><code><?php echo esc_html(CalendlyTokenPool::maskToken($row['token'])); ?></code></td>
                                <td>
                                    <span class="rl-badge <?php echo $row['enabled'] ? 'rl-badge-succeeded' : 'rl-badge-failed'; ?>">
                                        <span class="rl-status-dot"></span><?php echo $row['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rl-badge <?php echo $limited ? 'rl-badge-failed' : 'rl-badge-succeeded'; ?>">
                                        <span class="rl-status-dot"></span><?php echo $limited ? 'Cooling down' : 'Clear'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rl-badge <?php echo $open ? 'rl-badge-failed' : 'rl-badge-succeeded'; ?>">
                                        <span class="rl-status-dot"></span><?php echo $open ? 'OPEN' : 'CLOSED'; ?> (<?php echo esc_html((string) $failures); ?>/3)
                                    </span>
                                </td>
                                <td>
                                    <a class="rl-btn rl-btn-outline rl-btn-sm" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-calendly&rl_action=reset_calendly_token_health&index='.$index), 'rl_reset_calendly_token_health_nonce')); ?>">Reset Health</a>
                                    <a class="rl-btn rl-btn-destructive rl-btn-sm" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-calendly&rl_action=remove_calendly_token&index='.$index), 'rl_remove_calendly_token_nonce')); ?>" onclick="return confirm('Remove this token from the pool?');">Remove</a>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($rows)) { ?>
                            <tr><td colspan="6">No tokens configured yet.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="rl-detail-card">
                <h2 class="rl-detail-title">Edit Pool</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-calendly')); ?>">
                    <?php wp_nonce_field('rl_save_calendly_tokens_nonce'); ?>
                    <input type="hidden" name="rl_action" value="save_calendly_tokens" />
                    <div id="rl-calendly-token-repeater">
                        <?php foreach ($rows as $index => $row) { ?>
                            <div class="rl-token-row" style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                                <input type="checkbox" name="tokens[<?php echo $index; ?>][enabled]" value="1" <?php checked($row['enabled']); ?> />
                                <input type="text" name="tokens[<?php echo $index; ?>][label]" value="<?php echo esc_attr($row['label']); ?>" placeholder="Label (e.g. Primary Account)" />
                                <input type="password" name="tokens[<?php echo $index; ?>][token]" value="<?php echo esc_attr($row['token']); ?>" placeholder="Paste Calendly PAT here..." style="flex:1;" />
                            </div>
                        <?php } ?>
                    </div>
                    <p>
                        <button type="button" class="rl-btn rl-btn-outline" id="rl-calendly-token-add-row">Add Row</button>
                    </p>
                    <p>
                        <button type="submit" class="rl-btn rl-btn-primary">Save Token Pool</button>
                    </p>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('rl-calendly-token-add-row').addEventListener('click', function () {
                var container = document.getElementById('rl-calendly-token-repeater');
                var index = container.children.length;
                var row = document.createElement('div');
                row.className = 'rl-token-row';
                row.style.cssText = 'display:flex; gap:8px; align-items:center; margin-bottom:8px;';
                row.innerHTML = '<input type="checkbox" name="tokens[' + index + '][enabled]" value="1" checked />' +
                    '<input type="text" name="tokens[' + index + '][label]" placeholder="Label" />' +
                    '<input type="password" name="tokens[' + index + '][token]" placeholder="Paste Calendly PAT here..." style="flex:1;" />';
                container.appendChild(row);
            });
        </script>
        <?php
    }

    public function renderEventTypes(): void
    {
        $discovery = app(CalendlyEventTypeDiscoveryService::class);
        $roleResolver = app(CalendlyEventTypeRoleResolver::class);
        $discovered = $discovery->backup();
        $roles = $roleResolver->all();

        ?>
        <div class="wrap rl-admin-wrap">
            <div class="rl-admin-header">
                <div>
                    <h1 class="rl-admin-title">Calendly Event Types</h1>
                    <p class="rl-admin-subtitle">Discover event types across every pooled account and assign them to booking roles.</p>
                </div>
                <div class="rl-actions-group">
                    <a class="rl-btn rl-btn-outline" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-calendly-event-types&rl_action=refresh_calendly_event_types'), 'rl_refresh_calendly_event_types_nonce')); ?>">Refresh Event Types</a>
                </div>
            </div>

            <?php if (! empty($_GET['refreshed'])) { ?>
                <div class="notice notice-success"><p>Discovered <?php echo esc_html((string) absint($_GET['count'] ?? 0)); ?> event type(s).</p></div>
            <?php } ?>
            <?php if (! empty($_GET['roles_saved'])) { ?>
                <div class="notice notice-success"><p>Event type roles saved.</p></div>
            <?php } ?>

            <div class="rl-detail-card">
                <h2 class="rl-detail-title">Role Assignment</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=rl-calendly-event-types')); ?>">
                    <?php wp_nonce_field('rl_save_calendly_event_type_roles_nonce'); ?>
                    <input type="hidden" name="rl_action" value="save_calendly_event_type_roles" />
                    <table class="widefat rl-key-value-table">
                        <?php foreach (CalendlyEventTypeRoleResolver::ROLES as $role) {
                            $currentUri = $roles[$role] ?? '';
                            $currentLabel = $discovered[$currentUri]['label'] ?? '';
                            ?>
                            <tr>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $role))); ?></td>
                                <td>
                                    <div class="rl-combobox">
                                        <input type="text" class="rl-combobox-input" autocomplete="off" placeholder="Search event types..." value="<?php echo esc_attr($currentLabel); ?>" />
                                        <input type="hidden" class="rl-combobox-value" name="<?php echo esc_attr($role); ?>" value="<?php echo esc_attr($currentUri); ?>" />
                                        <div class="rl-combobox-options">
                                            <div class="rl-combobox-option" data-value="" data-label="">Select an event type</div>
                                            <?php foreach ($discovered as $uri => $meta) { ?>
                                                <div class="rl-combobox-option" data-value="<?php echo esc_attr($uri); ?>" data-label="<?php echo esc_attr($meta['label']); ?>"><?php echo esc_html($meta['label']); ?></div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                    <p><button type="submit" class="rl-btn rl-btn-primary">Save Roles</button></p>
                </form>
            </div>
            <script>
                document.querySelectorAll('.rl-combobox').forEach(function (box) {
                    var input = box.querySelector('.rl-combobox-input');
                    var hidden = box.querySelector('.rl-combobox-value');
                    var options = box.querySelector('.rl-combobox-options');
                    var optionEls = Array.prototype.slice.call(box.querySelectorAll('.rl-combobox-option'));

                    // Detach the options list from the table cell and append it
                    // directly to <body>, positioned with `fixed` coordinates
                    // computed from the input's own bounding rect. A table cell's
                    // overflow (or border-collapse rendering) clips an absolutely
                    // positioned descendant, so this is the only reliable way to
                    // keep the dropdown visible regardless of ancestor styling.
                    options.classList.add('rl-combobox-options-detached');
                    document.body.appendChild(options);

                    function reposition() {
                        var rect = input.getBoundingClientRect();
                        options.style.top = (rect.bottom + 4) + 'px';
                        options.style.left = rect.left + 'px';
                        options.style.width = rect.width + 'px';
                    }

                    function filter() {
                        var term = input.value.trim().toLowerCase();
                        optionEls.forEach(function (opt) {
                            var matches = term === '' || opt.dataset.label.toLowerCase().indexOf(term) !== -1 || opt.dataset.value === '';
                            opt.style.display = matches ? '' : 'none';
                        });
                    }

                    function open() {
                        filter();
                        reposition();
                        options.style.display = 'block';
                        window.addEventListener('scroll', reposition, true);
                        window.addEventListener('resize', reposition);
                    }

                    function close() {
                        options.style.display = 'none';
                        window.removeEventListener('scroll', reposition, true);
                        window.removeEventListener('resize', reposition);
                    }

                    input.addEventListener('focus', open);
                    input.addEventListener('input', open);
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') { close(); input.blur(); }
                    });

                    optionEls.forEach(function (opt) {
                        opt.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            input.value = opt.dataset.label;
                            hidden.value = opt.dataset.value;
                            close();
                        });
                    });

                    document.addEventListener('click', function (e) {
                        if (!box.contains(e.target)) { close(); }
                    });
                });
            </script>

            <div class="rl-detail-card">
                <h2 class="rl-detail-title">Discovered Event Types (<?php echo esc_html((string) count($discovered)); ?>)</h2>
                <table class="widefat rl-key-value-table">
                    <thead><tr><th>Name</th><th>URI</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($discovered as $uri => $meta) { ?>
                            <tr>
                                <td><?php echo esc_html($meta['name']); ?></td>
                                <td><code><?php echo esc_html($uri); ?></code></td>
                                <td><?php echo $meta['active'] ? 'Active' : 'Inactive'; ?></td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($discovered)) { ?>
                            <tr><td colspan="3">No event types discovered yet — click "Refresh Event Types".</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function enqueueAdminStyles(string $hook): void
    {
        if (! str_contains($hook, 'rl-calendly')) {
            return;
        }

        wp_add_inline_style('wp-admin', '
            .rl-admin-wrap {
                max-width: 1360px;
                margin: 28px auto 64px auto !important;
                padding: 0 20px !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                color: #09090b;
                -webkit-font-smoothing: antialiased;
            }
            .rl-admin-wrap * { box-sizing: border-box; }
            .rl-admin-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
            .rl-admin-title { font-size: 24px; font-weight: 700; letter-spacing: -0.025em; color: #09090b; margin: 0; line-height: 1.25; }
            .rl-admin-subtitle { margin: 4px 0 0; color: #71717a; font-size: 13px; line-height: 1.4; }
            .rl-actions-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .rl-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 7px 14px; border-radius: 6px; text-decoration: none; cursor: pointer; transition: all 0.15s ease; line-height: 1.4; white-space: nowrap; border: 1px solid transparent; }
            .rl-btn-primary { background: #18181b; color: #fafafa !important; border-color: #18181b; }
            .rl-btn-primary:hover { background: #27272a; border-color: #27272a; color: #fafafa !important; }
            .rl-btn-outline { background: #ffffff; color: #09090b !important; border-color: #e4e4e7; }
            .rl-btn-outline:hover { background: #f4f4f5; border-color: #d4d4d8; color: #09090b !important; }
            .rl-btn-destructive { background: #ffffff; color: #ef4444 !important; border-color: #fecaca; }
            .rl-btn-destructive:hover { background: #fef2f2; border-color: #f87171; color: #dc2626 !important; }
            .rl-btn-sm { padding: 5px 10px; font-size: 12px; }
            .rl-badge { display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; line-height: 1.4; white-space: nowrap; background: #ffffff; color: #09090b; border: 1px solid #e4e4e7; }
            .rl-status-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; background: #71717a; }
            .rl-badge-succeeded .rl-status-dot { background: #18181b; }
            .rl-badge-failed .rl-status-dot { background: #dc2626; }
            .rl-detail-card { background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03); margin-bottom: 16px; }
            .rl-detail-title { margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #09090b; border-bottom: 1px solid #f4f4f5; padding-bottom: 8px; }
            .rl-key-value-table { width: 100%; font-size: 12px; line-height: 1.8; border-collapse: collapse; }
            .rl-combobox { position: relative; min-width: 420px; max-width: 520px; }
            .rl-combobox-input { width: 100%; padding: 6px 10px; border: 1px solid #e4e4e7; border-radius: 6px; font-size: 13px; }
            .rl-combobox-input:focus { outline: none; border-color: #18181b; }
            .rl-combobox-options { display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 20; max-height: 260px; overflow-y: auto; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; margin-top: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
            .rl-combobox-options-detached { position: fixed !important; z-index: 99999; margin-top: 0; }
            .rl-combobox-option { padding: 6px 10px; font-size: 12px; cursor: pointer; }
            .rl-combobox-option:hover { background: #f4f4f5; }
            .rl-admin-wrap .notice { background: #ffffff !important; border: 1px solid #e4e4e7 !important; border-left: 3px solid #18181b !important; border-radius: 8px !important; padding: 12px 16px !important; margin: 16px 0 !important; color: #09090b !important; font-size: 13px !important; }
            .rl-admin-wrap .notice-success { border-left-color: #18181b !important; }
            .rl-admin-wrap .notice-error { border-left-color: #dc2626 !important; }
        ');
    }
}
