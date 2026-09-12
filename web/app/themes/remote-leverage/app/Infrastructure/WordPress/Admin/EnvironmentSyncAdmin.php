<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Sync\Datasets\Dataset;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferPusher;
use Throwable;

/**
 * Settings → Environment Sync.
 *
 * Credentials, a push form, and the history of transfers this environment has
 * received. The credential has to exist before any transfer can run, which is
 * why provisioning sits above the transfer form rather than beside it.
 *
 * The screen is never registered in production — see register(). That is the
 * outermost of the four gates in the design doc; the abilities enforce their
 * own independently, so removing this one would still not make production
 * reachable.
 */
class EnvironmentSyncAdmin
{
    public const SLUG = 'rl-environment-sync';

    /**
     * Transient holding the one-time plaintext application password between the
     * provisioning request and the redirect that displays it. Keyed per user so
     * two admins provisioning at once can't read each other's.
     */
    private const PASSWORD_TRANSIENT = 'rl_env_sync_password_';

    public function __construct(
        private readonly SyncCredentialProvisioner $provisioner,
        private readonly DatasetRegistry $registry,
        private readonly SessionStore $sessions,
        private readonly TransferPusher $pusher,
    ) {}

    public function register(): void
    {
        if (! SyncEnvironment::syncEnabled()) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'handleActions']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            page_title: 'Environment Sync',
            menu_title: 'Environment Sync',
            capability: 'manage_options',
            menu_slug: self::SLUG,
            callback: [$this, 'render'],
        );
    }

    public function handleActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['rl_sync_action'] ?? ''));

        if ($action === '') {
            return;
        }

        check_admin_referer(self::SLUG);

        try {
            match ($action) {
                'provision' => $this->handleProvision(),
                'revoke' => $this->handleRevoke(),
                'push' => $this->handlePush(),
                'rollback' => $this->handleRollback(),
                default => null,
            };
        } catch (Throwable $e) {
            set_transient($this->noticeKey('error'), $e->getMessage(), 60);
        }

        wp_safe_redirect(admin_url('options-general.php?page='.self::SLUG));
        exit;
    }

    private function handleProvision(): void
    {
        $result = $this->provisioner->provision();

        set_transient(
            self::PASSWORD_TRANSIENT.get_current_user_id(),
            $result,
            5 * MINUTE_IN_SECONDS,
        );
    }

    private function handleRevoke(): void
    {
        $this->provisioner->revoke();
        set_transient($this->noticeKey('success'), 'Sync credentials revoked.', 60);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        $status = $this->provisioner->status();
        $issued = get_transient(self::PASSWORD_TRANSIENT.get_current_user_id());
        delete_transient(self::PASSWORD_TRANSIENT.get_current_user_id());

        echo '<div class="wrap">';
        echo '<h1>Environment Sync</h1>';

        $this->renderNotices();
        $this->renderEnvironmentRow();

        if (is_array($issued)) {
            $this->renderIssuedPassword($issued);
        }

        $this->renderCredentialSection($status);
        $this->renderTransferSection();
        $this->renderHistory();

        echo '</div>';
    }

    private function renderEnvironmentRow(): void
    {
        printf(
            '<p>Running in <code>%s</code>. This screen is not registered in production.</p>',
            esc_html(SyncEnvironment::current()),
        );
    }

    private function renderNotices(): void
    {
        foreach (['error', 'success'] as $type) {
            $message = get_transient($this->noticeKey($type));

            if ($message === false) {
                continue;
            }

            delete_transient($this->noticeKey($type));

            printf(
                '<div class="notice notice-%s"><p>%s</p></div>',
                esc_attr($type === 'error' ? 'error' : 'success'),
                esc_html((string) $message),
            );
        }
    }

    /**
     * @param  array{user_login: string, password: string}  $issued
     */
    private function renderIssuedPassword(array $issued): void
    {
        $envLines = sprintf(
            "SYNC_URL=%s\nSYNC_USER=%s\nSYNC_APP_PASSWORD=%s",
            home_url(),
            $issued['user_login'],
            $issued['password'],
        );

        echo '<div class="notice notice-success"><p><strong>Credentials created.</strong> ';
        echo 'The password below is shown once and cannot be retrieved again.</p>';
        echo '<p>Add these to the <em>other</em> environment&rsquo;s <code>.env</code>, ';
        echo 'prefixed for this environment (e.g. <code>STAGING_SYNC_URL</code>):</p>';
        printf(
            '<textarea readonly rows="3" style="width:100%%;font-family:monospace;">%s</textarea>',
            esc_textarea($envLines),
        );
        echo '</div>';
    }

    /**
     * @param  array{exists: bool, has_capability: bool, password_count: int, available: bool, unavailable_reason: ?string}  $status
     */
    private function renderCredentialSection(array $status): void
    {
        echo '<h2>Sync credentials</h2>';
        echo '<p>The <code>sync-service</code> user this environment accepts sync calls as. ';
        echo 'Creating it here does what <code>wp user create</code> and ';
        echo '<code>wp acorn rl:sync:grant</code> do, so no shell access is needed.</p>';

        echo '<table class="widefat striped" style="max-width:40rem;"><tbody>';
        $this->statusRow('User exists', $status['exists']);
        $this->statusRow('Has sync capability', $status['has_capability']);
        printf(
            '<tr><td>Active application passwords</td><td>%d</td></tr>',
            (int) $status['password_count'],
        );
        echo '</tbody></table>';

        if (! $status['available']) {
            printf(
                '<div class="notice notice-error inline"><p>%s</p></div>',
                esc_html((string) $status['unavailable_reason']),
            );

            return;
        }

        echo '<p>';
        $this->actionButton(
            'provision',
            $status['password_count'] > 0 ? 'Regenerate credentials' : 'Generate sync credentials',
            'button button-primary',
            $status['password_count'] > 0
                ? 'This revokes the current password. Any environment using it will stop syncing until updated. Continue?'
                : null,
        );

        if ($status['exists'] && ($status['has_capability'] || $status['password_count'] > 0)) {
            echo ' ';
            $this->actionButton(
                'revoke',
                'Revoke',
                'button',
                'This revokes the password and removes the sync capability. Continue?',
            );
        }

        echo '</p>';
    }

    /**
     * Run a push in-request.
     *
     * A full push is ~28 round trips to the target, so the time limit is lifted.
     * For a large selection the CLI (`wp acorn rl:sync:push`) is the more
     * reliable path — it is not bound by the web server's own request timeout,
     * which no amount of set_time_limit() can raise.
     */
    private function handlePush(): void
    {
        $manifest = TransferManifest::fromArray([
            'direction' => TransferManifest::PUSH,
            'datasets' => array_map('sanitize_key', (array) ($_POST['datasets'] ?? [])),
            'excluded_post_types' => $this->csvField('excluded_post_types'),
            'excluded_post_ids' => $this->csvField('excluded_post_ids'),
            'clean_before_import' => array_map('sanitize_key', (array) ($_POST['clean'] ?? [])),
        ], $this->registry);

        $target = sanitize_key(wp_unslash($_POST['target'] ?? 'staging'));

        @set_time_limit(0);

        $result = $this->pusher->push($manifest, $target);

        set_transient(
            $this->noticeKey('success'),
            sprintf(
                'Pushed %d posts and %d meta rows to %s. Session %s, %d undo entries.',
                $result['sent']['posts'],
                $result['sent']['meta'],
                $target,
                $result['session_id'],
                $result['undo_entries'],
            ),
            120,
        );
    }

    private function handleRollback(): void
    {
        $sessionId = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
        $target = sanitize_key(wp_unslash($_POST['target'] ?? 'staging'));

        $result = (new SyncClient($target))->run('app/rollback-transfer', ['session_id' => $sessionId]);

        if (($result['ok'] ?? false) !== true) {
            set_transient($this->noticeKey('error'), (string) ($result['error'] ?? 'Rollback failed.'), 120);

            return;
        }

        set_transient(
            $this->noticeKey('success'),
            sprintf('Reverted %d change(s) on %s.', (int) ($result['reverted'] ?? 0), $target),
            120,
        );
    }

    /**
     * @return array<int, string>
     */
    private function csvField(string $name): array
    {
        $raw = sanitize_text_field(wp_unslash($_POST[$name] ?? ''));

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }

    private function renderTransferSection(): void
    {
        echo '<h2>Transfer</h2>';

        $targets = array_keys(array_filter(
            (array) config('rl-sync.environments', []),
            fn ($env) => ! empty($env['url']),
        ));

        if ($targets === []) {
            echo '<div class="notice notice-warning inline"><p>No remote environments are configured. '
                .'Add <code>STAGING_SYNC_URL</code>, <code>STAGING_SYNC_USER</code> and '
                .'<code>STAGING_SYNC_APP_PASSWORD</code> to this environment&rsquo;s <code>.env</code>.</p></div>';

            return;
        }

        echo '<form method="post" onsubmit="return confirm(\'This overwrites data on the target. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="push">';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">Target</th><td><select name="target">';
        foreach ($targets as $target) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $target));
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Datasets</th><td>';
        foreach ($this->registry->transferable() as $dataset) {
            $this->datasetRow($dataset);
        }
        echo '<p class="description">Leads, referrals, scheduling and users are never transferred. '
            .'Content without media leaves posts pointing at attachments the target does not have.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Skip post types</th><td>'
            .'<input type="text" name="excluded_post_types" class="regular-text" placeholder="case_study, page">'
            .'<p class="description">Comma separated.</p></td></tr>';

        echo '<tr><th scope="row">Skip post IDs</th><td>'
            .'<input type="text" name="excluded_post_ids" class="regular-text" placeholder="7, 12">'
            .'<p class="description">Comma separated.</p></td></tr>';

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Push to target</button></p>';
        echo '</form>';

        echo '<p class="description">A large push is many round trips and can outrun the web server&rsquo;s '
            .'request timeout. For a full sync prefer <code>wp acorn rl:sync:push --target=staging</code>, '
            .'which is not bound by it.</p>';
    }

    private function datasetRow(Dataset $dataset): void
    {
        printf(
            '<p><label><input type="checkbox" name="datasets[]" value="%s"%s> <strong>%s</strong></label>'
            .' &nbsp; <label><input type="checkbox" name="clean[]" value="%s"> empty on target first</label>'
            .'<br><span class="description">%s</span></p>',
            esc_attr($dataset->key),
            $dataset->defaultSelected ? ' checked' : '',
            esc_html($dataset->label),
            esc_attr($dataset->key),
            esc_html($dataset->description),
        );
    }

    private function renderHistory(): void
    {
        $sessions = $this->sessions->recent(10);

        echo '<h2>Recent transfers</h2>';

        if ($sessions === []) {
            echo '<p>No transfers have been received by this environment yet.</p>';
            echo '<p class="description">This list shows sessions this environment <em>received</em>. '
                .'A push you send from here is recorded on the target, not locally.</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>'
            .'<th>Session</th><th>State</th><th>Datasets</th><th>Rows</th><th>Remapped</th><th></th>'
            .'</tr></thead><tbody>';

        foreach ($sessions as $session) {
            $status = $session->toStatusArray();
            printf(
                '<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%s</td></tr>',
                esc_html(substr($status['id'], 0, 8)),
                esc_html($status['state']),
                esc_html(implode(', ', $status['datasets'])),
                (int) $status['total_rows'],
                (int) $status['remapped_attachments'],
                $status['error'] ? '<span class="description">'.esc_html($status['error']).'</span>' : '',
            );
        }

        echo '</tbody></table>';
    }

    private function statusRow(string $label, bool $value): void
    {
        printf(
            '<tr><td>%s</td><td>%s</td></tr>',
            esc_html($label),
            $value
                ? '<span style="color:#008a20;">&#10003; yes</span>'
                : '<span style="color:#b32d2e;">&#10007; no</span>',
        );
    }

    private function actionButton(string $action, string $label, string $class, ?string $confirm): void
    {
        echo '<form method="post" style="display:inline;"';

        if ($confirm !== null) {
            printf(' onsubmit="return confirm(%s);"', esc_attr(wp_json_encode($confirm)));
        }

        echo '>';
        wp_nonce_field(self::SLUG);
        printf('<input type="hidden" name="rl_sync_action" value="%s">', esc_attr($action));
        printf(
            '<button type="submit" class="%s">%s</button>',
            esc_attr($class),
            esc_html($label),
        );
        echo '</form>';
    }

    private function noticeKey(string $type): string
    {
        return 'rl_env_sync_notice_'.$type.'_'.get_current_user_id();
    }
}
