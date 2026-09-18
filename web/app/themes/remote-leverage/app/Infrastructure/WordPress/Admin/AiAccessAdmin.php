<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Ai\Provisioning\ContentAgentProvisioner;
use Throwable;

/**
 * Settings → AI Access. Issues and revokes the credentials an MCP client
 * authenticates with, for an admin who has no shell.
 *
 * `wp acorn rl:ai:agent --rotate` is the equivalent, and on production it is not
 * reachable: WP-CLI there means ECS Exec, which is an AWS permission that the
 * person who actually needs to onboard a colleague does not have and should not
 * need. A credential nobody can issue is a feature nobody can use.
 *
 * Deliberately issues *named, individually revocable* passwords rather than the
 * shared one. The shared credential is swept as a set by --rotate, so handing a
 * colleague one means a later rotation silently ends their access, with no event
 * and no obvious cause — see ContentAgentProvisioner::issuePassword().
 *
 * This screen never displays an existing credential, because it cannot: the
 * plaintext is hashed on save and exists exactly once, in the response to the
 * request that created it.
 */
class AiAccessAdmin
{
    public const SLUG = 'rl-ai-access';

    /** Holds the one-time plaintext between the POST and the redirect. */
    private const ISSUED_TRANSIENT = 'rl_ai_issued_';

    public function __construct(private readonly ContentAgentProvisioner $provisioner) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public function enqueueStyles(string $hook): void
    {
        if (str_contains($hook, self::SLUG)) {
            AdminDesignSystem::enqueue();
        }
    }

    public function addMenuPage(): void
    {
        add_options_page(
            page_title: 'AI Access',
            menu_title: 'AI Access',
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

        $action = sanitize_text_field(wp_unslash($_POST['rl_ai_action'] ?? ''));

        if ($action === '') {
            return;
        }

        check_admin_referer(self::SLUG);

        try {
            match ($action) {
                'issue' => $this->handleIssue(),
                'revoke_one' => $this->handleRevokeOne(),
                'reconcile' => $this->handleReconcile(),
                default => null,
            };
        } catch (Throwable $e) {
            set_transient($this->noticeKey('error'), $e->getMessage(), 60);
        }

        wp_safe_redirect(admin_url('options-general.php?page='.self::SLUG));
        exit;
    }

    private function handleIssue(): void
    {
        $name = sanitize_text_field(wp_unslash($_POST['credential_name'] ?? ''));

        $issued = $this->provisioner->issuePassword($name);

        // The plaintext survives exactly one redirect, keyed to the admin who
        // asked for it — so it cannot surface in another user's session, and it
        // is gone shortly after whether or not they copied it.
        set_transient(self::ISSUED_TRANSIENT.get_current_user_id(), $issued, 5 * MINUTE_IN_SECONDS);
    }

    private function handleRevokeOne(): void
    {
        $uuid = sanitize_text_field(wp_unslash($_POST['uuid'] ?? ''));

        $this->provisioner->revokePassword($uuid)
            ? set_transient($this->noticeKey('success'), 'Credential revoked. That client stops working immediately; every other one is unaffected.', 60)
            : set_transient($this->noticeKey('error'), 'That credential could not be found — it may already have been revoked.', 60);
    }

    private function handleReconcile(): void
    {
        $result = $this->provisioner->ensure();

        $changed = array_merge(
            array_map(fn (string $c) => "granted {$c}", $result['granted']),
            array_map(fn (string $c) => "revoked {$c}", $result['revoked']),
        );

        set_transient(
            $this->noticeKey('success'),
            $changed === []
                ? 'Already in line with configuration — nothing changed.'
                : 'Reconciled: '.implode(', ', $changed).'.',
            60,
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to view this page.');
        }

        $status = $this->provisioner->status();
        $issuedKey = self::ISSUED_TRANSIENT.get_current_user_id();
        $issued = get_transient($issuedKey);

        if ($issued) {
            delete_transient($issuedKey);
        }

        echo '<div class="wrap rl-admin-wrap">';
        echo '<h1>AI Access</h1>';
        echo '<p class="rl-card-sub">Credentials for the <code>'.esc_html($status['login']).'</code> user that '
            .'Claude authenticates as. Anyone holding one can do everything listed under Capabilities below, '
            .'on this site, as that user.</p>';

        $this->notices();

        if (is_array($issued)) {
            $this->issuedCredential($issued);
        }

        if (! $status['available']) {
            printf(
                '<div class="rl-card"><p class="rl-card-title">Application passwords are unavailable</p><p class="rl-badge rl-badge-bad">%s</p>'
                .'<p class="rl-card-sub">Nothing can authenticate until this is resolved, and every failure will look like a wrong password.</p></div>',
                esc_html((string) ($status['unavailable_reason'] ?? 'Unknown reason.')),
            );
            echo '</div>';

            return;
        }

        $this->capabilities($status);
        $this->issueForm();
        $this->existingCredentials();
        $this->connectionDetails();

        echo '</div>';
    }

    /**
     * The plaintext, shown once. Presented as a whole connector value rather
     * than a bare password because the base64 of "user:password" is what every
     * client actually wants, and hand-assembling it is where this goes wrong.
     *
     * @param  array{user_login: string, password: string, name: string}  $issued
     */
    private function issuedCredential(array $issued): void
    {
        $basic = base64_encode($issued['user_login'].':'.$issued['password']);

        echo '<div class="rl-card" style="border-color:#16a34a;">';
        printf('<p class="rl-card-title">Credential &ldquo;%s&rdquo; created</p>', esc_html($issued['name']));
        echo '<p class="rl-card-sub"><strong>Copy it now.</strong> It is hashed on save and cannot be shown again. '
            .'If it is lost, revoke it below and issue another.</p>';

        $this->field('Username', $issued['user_login']);
        $this->field('Application password', $issued['password']);

        echo '<p class="rl-card-sub" style="margin-top:14px;">For a claude.ai custom connector, set the connector to '
            .'<strong>No sign-in</strong>, then add a request header named <code>Authorization</code> with this value:</p>';
        $this->field('Authorization header', 'Basic '.$basic);

        echo '<p class="rl-card-sub" style="margin-top:14px;">For Claude Desktop, paste this into '
            .'Settings &rarr; Developer &rarr; Edit Config:</p>';

        $config = wp_json_encode([
            'mcpServers' => [
                'remote-leverage' => [
                    'command' => 'npx',
                    'args' => ['-y', '@automattic/mcp-wordpress-remote@latest'],
                    'env' => [
                        'WP_API_URL' => $this->endpoint(),
                        'WP_API_USERNAME' => $issued['user_login'],
                        'WP_API_PASSWORD' => $issued['password'],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        printf(
            '<textarea readonly rows="14" class="rl-sync-code" style="width:100%%;font-family:monospace;font-size:12px;" onclick="this.select()">%s</textarea>',
            esc_textarea((string) $config),
        );

        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function capabilities(array $status): void
    {
        $labels = [
            'publish_pages' => 'Publish new pages directly',
            'edit_published_pages' => 'Change pages that are already live',
            'rl_read_business_data' => 'Read enquiries, including customer contact details',
            'upload_files' => 'Upload files to the media library',
        ];

        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Capabilities</p>';
        echo '<p class="rl-card-sub">What a credential can do. These come from configuration and are reapplied on '
            .'every deploy, so changing them here is not possible &mdash; set the environment variable and deploy.</p>';

        echo '<table class="widefat striped"><tbody>';

        foreach ($status['capabilities'] as $capability => $granted) {
            printf(
                '<tr><td>%s<br><code style="font-size:11px;color:#71717a;">%s</code></td><td style="width:90px;"><span class="rl-badge %s">%s</span></td></tr>',
                esc_html($labels[$capability] ?? $capability),
                esc_html((string) $capability),
                $granted ? 'rl-badge-ok' : 'rl-badge-bad',
                $granted ? 'yes' : 'no',
            );
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:12px;">';
        $this->form('reconcile', 'Re-apply from configuration', 'rl-btn rl-btn-outline');
        echo '</p>';
        echo '</div>';
    }

    private function issueForm(): void
    {
        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Issue a credential</p>';
        echo '<p class="rl-card-sub">One per person, named after them. Naming them individually is what lets you cut '
            .'off one person later without making everyone else reconnect.</p>';

        echo '<form method="post">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_ai_action" value="issue">';
        printf(
            '<input type="text" name="credential_name" required maxlength="80" placeholder="%s" style="min-width:260px;margin-right:8px;">',
            esc_attr('claude-jane'),
        );
        echo '<button type="submit" class="rl-btn rl-btn-primary">Issue credential</button>';
        echo '</form>';
        echo '</div>';
    }

    private function existingCredentials(): void
    {
        $passwords = $this->provisioner->passwords();

        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Existing credentials</p>';

        if ($passwords === []) {
            echo '<p class="rl-card-sub">None yet. Nothing can connect until one is issued.</p></div>';

            return;
        }

        echo '<p class="rl-card-sub">The password itself can never be shown again. Revoking takes effect immediately.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Created</th><th>Last used</th><th></th></tr></thead><tbody>';

        foreach ($passwords as $password) {
            echo '<tr><td><strong>'.esc_html($password['name']).'</strong>';

            if ($password['managed']) {
                echo '<br><span class="rl-card-sub" style="font-size:11px;">shared credential &mdash; '
                    .'<code>--rotate</code> revokes this one</span>';
            }

            echo '</td>';
            printf('<td>%s</td>', esc_html($this->when($password['created'])));
            printf(
                '<td>%s</td>',
                $password['last_used'] === null
                    ? '<span class="rl-badge rl-badge-busy">never</span>'
                    : esc_html($this->when($password['last_used'])),
            );
            echo '<td style="width:100px;">';
            $this->form(
                'revoke_one',
                'Revoke',
                'rl-btn rl-btn-destructive rl-btn-sm',
                ['uuid' => $password['uuid']],
                sprintf('Revoke "%s"? Whoever holds it loses access immediately.', $password['name']),
            );
            echo '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    private function connectionDetails(): void
    {
        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Connection details</p>';
        echo '<p class="rl-card-sub">The same for everyone. Only the credential differs.</p>';
        $this->field('MCP endpoint', $this->endpoint());
        echo '</div>';
    }

    /**
     * The MCP server URL. Built from home_url() rather than hardcoded so this
     * screen is correct on whichever environment it is being read on — the
     * commonest way to hand somebody a working credential for the wrong site.
     */
    private function endpoint(): string
    {
        return home_url('/wp-json/mcp/mcp-adapter-default-server');
    }

    private function field(string $label, string $value): void
    {
        printf(
            '<p style="margin:6px 0;"><label style="display:block;font-size:12px;color:#71717a;margin-bottom:2px;">%s</label>'
            .'<input type="text" readonly value="%s" onclick="this.select()" style="width:100%%;font-family:monospace;font-size:12px;"></p>',
            esc_html($label),
            esc_attr($value),
        );
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function form(string $action, string $label, string $class, array $fields = [], ?string $confirm = null): void
    {
        echo '<form method="post" style="display:inline;"';

        if ($confirm !== null) {
            printf(' onsubmit="return confirm(%s);"', esc_attr((string) wp_json_encode($confirm)));
        }

        echo '>';
        wp_nonce_field(self::SLUG);
        printf('<input type="hidden" name="rl_ai_action" value="%s">', esc_attr($action));

        foreach ($fields as $name => $value) {
            printf('<input type="hidden" name="%s" value="%s">', esc_attr($name), esc_attr($value));
        }

        printf('<button type="submit" class="%s">%s</button>', esc_attr($class), esc_html($label));
        echo '</form>';
    }

    private function when(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return '—';
        }

        return wp_date((string) get_option('date_format'), $timestamp) ?: '—';
    }

    private function notices(): void
    {
        foreach (['success' => 'notice-success', 'error' => 'notice-error'] as $type => $class) {
            $key = $this->noticeKey($type);
            $message = get_transient($key);

            if (! $message) {
                continue;
            }

            delete_transient($key);
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html((string) $message));
        }
    }

    private function noticeKey(string $type): string
    {
        return 'rl_ai_notice_'.$type.'_'.get_current_user_id();
    }
}
