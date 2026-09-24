<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Ai\InsightsCapability;
use App\Domains\Lead\Provisioning\DataApiCredentialProvisioner;
use Throwable;

/**
 * Settings → Data API. Issues and revokes the credentials the data team's
 * tooling reads rl-data/v1 with, for an admin who has no shell.
 *
 * There is no other way to issue one. The routes check rl_read_business_data,
 * which is otherwise granted only by `wp acorn rl:ai:grant-insights`, and
 * neither staging nor production has WP-CLI to run it with.
 *
 * Every credential issued here reads every lead and booking, customer contact
 * details included. That is why they are named per consumer and revocable one
 * at a time — see DataApiCredentialProvisioner — and why this screen never
 * displays one again: the plaintext is hashed on save and exists exactly once,
 * in the response to the request that created it.
 */
class DataApiAdmin
{
    public const SLUG = 'rl-data-api';

    /** Holds the one-time plaintext between the POST and the redirect. */
    private const ISSUED_TRANSIENT = 'rl_data_api_issued_';

    public function __construct(private readonly DataApiCredentialProvisioner $provisioner) {}

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
            page_title: 'Data API',
            menu_title: 'Data API',
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

        $action = sanitize_text_field(wp_unslash($_POST['rl_data_api_action'] ?? ''));

        if ($action === '') {
            return;
        }

        check_admin_referer(self::SLUG);

        try {
            match ($action) {
                'issue' => $this->handleIssue(),
                'revoke_one' => $this->handleRevokeOne(),
                'revoke_all' => $this->handleRevokeAll(),
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
            ? set_transient($this->noticeKey('success'), 'Credential revoked. Whatever used it stops working immediately; every other one is unaffected.', 60)
            : set_transient($this->noticeKey('error'), 'That credential could not be found — it may already have been revoked.', 60);
    }

    private function handleRevokeAll(): void
    {
        $this->provisioner->revokeAll();

        set_transient(
            $this->noticeKey('success'),
            'Every Data API credential revoked and the capability removed. Nothing can read leads or bookings '
                .'through the Data API until a new credential is issued.',
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
        echo '<h1>Data API</h1>';
        echo '<p class="rl-card-sub">Credentials for the <code>'.esc_html($status['login']).'</code> user that the data '
            .'team&rsquo;s tools authenticate as, to export leads and bookings from this site.</p>';

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

        $this->explanation($status);
        $this->issueForm();
        $this->existingCredentials();
        $this->connectionDetails();

        echo '</div>';
    }

    /**
     * The plaintext, shown once. Presented as a ready header and a working
     * request rather than a bare password, because the base64 of
     * "user:password" is what every client actually wants and hand-assembling
     * it is where this goes wrong.
     *
     * @param  array{user_login: string, password: string, name: string}  $issued
     */
    private function issuedCredential(array $issued): void
    {
        $header = 'Authorization: Basic '.base64_encode($issued['user_login'].':'.$issued['password']);

        echo '<div class="rl-card" style="border-color:#16a34a;">';
        printf('<p class="rl-card-title">Credential &ldquo;%s&rdquo; created</p>', esc_html($issued['name']));
        echo '<p class="rl-card-sub"><strong>Copy it now.</strong> It is hashed on save and cannot be shown again. '
            .'If it is lost, revoke it below and issue another.</p>';

        $this->field('Username', $issued['user_login']);
        $this->field('Application password', $issued['password']);
        $this->field('Authorization header', $header);

        echo '<p class="rl-card-sub" style="margin-top:14px;">A request to check it works, for the last 30 days of leads:</p>';

        // `to` is exclusive, so an example ending today would silently leave
        // out today's leads — the ones someone checking a new credential is
        // most likely to go looking for.
        $url = $this->endpoint('leads').'?'.http_build_query([
            'from' => wp_date('Y-m-d', (int) strtotime('-30 days')),
            'to' => wp_date('Y-m-d', (int) strtotime('+1 day')),
        ]);

        printf(
            '<textarea readonly rows="3" class="rl-sync-code" style="width:100%%;font-family:monospace;font-size:12px;" onclick="this.select()">%s</textarea>',
            esc_textarea("curl -H '{$header}' \\\n  '{$url}'"),
        );

        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function explanation(array $status): void
    {
        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">What a credential can read</p>';
        echo '<p class="rl-card-sub">Every lead and every booking on this site, including customer names, email addresses '
            .'and phone numbers. The endpoints are read-only, but treat a credential as the customer data itself leaving '
            .'the building: '
            .'issue one per consumer, named after it, and revoke it when that consumer no longer needs it.</p>';

        printf(
            '<table class="widefat striped"><tbody><tr><td>Read leads and bookings<br><code style="font-size:11px;color:#71717a;">%s</code></td>'
            .'<td style="width:90px;"><span class="rl-badge %s">%s</span></td></tr></tbody></table>',
            esc_html(InsightsCapability::NAME),
            $status['has_capability'] ? 'rl-badge-ok' : 'rl-badge-bad',
            $status['has_capability'] ? 'yes' : 'no',
        );

        if (! $status['has_capability']) {
            echo '<p class="rl-card-sub" style="margin-top:8px;">Not currently granted, so no credential works. '
                .'Issuing one grants it.</p>';
        }

        echo '</div>';
    }

    private function issueForm(): void
    {
        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Issue a credential</p>';
        echo '<p class="rl-card-sub">One per tool or person, named after it. Naming them individually is what lets you '
            .'cut one off later without making every other consumer reconnect.</p>';

        echo '<form method="post">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_data_api_action" value="issue">';
        printf(
            '<input type="text" name="credential_name" required maxlength="80" placeholder="%s" style="min-width:260px;margin-right:8px;">',
            esc_attr('warehouse-loader'),
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
            printf('<tr><td><strong>%s</strong></td>', esc_html($password['name']));
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
                sprintf('Revoke "%s"? Whatever uses it loses access immediately.', $password['name']),
            );
            echo '</td></tr>';
        }

        echo '</tbody></table>';

        // The kill switch for a suspected leak, when working out which
        // credential it was matters less than stopping the reads now.
        echo '<p style="margin-top:12px;">';
        $this->form(
            'revoke_all',
            'Revoke all',
            'rl-btn rl-btn-destructive',
            [],
            'Revoke every Data API credential and remove the capability? Every consumer loses access immediately.',
        );
        echo '</p>';
        echo '</div>';
    }

    private function connectionDetails(): void
    {
        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Connection details</p>';
        echo '<p class="rl-card-sub">The same for every consumer; only the credential differs. Authenticate with HTTP Basic '
            .'(the username and an application password) and pass <code>from</code> and <code>to</code> to bound the range.</p>';
        $this->field('Leads endpoint', $this->endpoint('leads'));
        $this->field('Bookings endpoint', $this->endpoint('bookings'));
        echo '</div>';
    }

    /**
     * Built from home_url() rather than hardcoded so this screen is correct on
     * whichever environment it is being read on — the commonest way to hand
     * somebody a working credential for the wrong site.
     */
    private function endpoint(string $dataset): string
    {
        return home_url('/wp-json/rl-data/v1/'.$dataset);
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
        printf('<input type="hidden" name="rl_data_api_action" value="%s">', esc_attr($action));

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
        return 'rl_data_api_notice_'.$type.'_'.get_current_user_id();
    }
}
