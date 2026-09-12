<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Domains\Sync\SyncEnvironment;
use Throwable;

/**
 * Settings → Environment Sync.
 *
 * Step 1 of docs/environment-sync.md: provisioning only. The dataset transfer
 * UI lands here too, but the credential has to exist before any of it can run.
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

    public function __construct(private readonly SyncCredentialProvisioner $provisioner) {}

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
        $this->renderNextSteps();

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

    private function renderNextSteps(): void
    {
        echo '<h2>Transfer</h2>';
        echo '<p>Dataset selection, push/pull and media sync are not built yet — ';
        echo 'see <code>docs/environment-sync.md</code> for the design and build order. ';
        echo 'Credentials are step 1, and also unblock the existing ';
        echo '<code>wp acorn rl:sync:settings</code> and <code>rl:sync:page</code> commands.</p>';
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
