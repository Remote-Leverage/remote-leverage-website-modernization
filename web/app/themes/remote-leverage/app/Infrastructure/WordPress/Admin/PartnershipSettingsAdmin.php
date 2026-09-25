<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\PartnerHub\Support\PartnershipCallCalendar;
use App\Domains\PartnerHub\Support\PartnershipSettings;
use App\Infrastructure\Slack\SlackCredentials;

/**
 * Partners Hub → Partnership Settings: the Slack channel and Calendly event behind
 * `/become-a-partner/`. See PartnershipSettings for what each value switches, and why this is a
 * screen and not the environment.
 *
 * Beside Prospects, under the `rl_partner` menu, for the same reason that list is there: this is
 * the partnerships team's page, not a site-wide setting. It is the last of the partnership
 * area's tabs, after the Overview and the list (PartnershipAdminChrome::nav()). It posts back to
 * itself rather than to options.php so the screen can stay in the Partners Hub menu and show the
 * reason a value was refused, and it says what is actually in force — a channel is no use
 * without the bot token, and that is the thing nobody can see from the form alone.
 */
class PartnershipSettingsAdmin
{
    public const SLUG = 'rl-partnership-settings';

    private const NONCE = 'rl_partnership_settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'handleSave']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public static function url(array $args = []): string
    {
        return PartnershipAdminChrome::url(self::SLUG, $args);
    }

    public function enqueueStyles(string $hook): void
    {
        if (str_contains($hook, self::SLUG)) {
            PartnershipAdminChrome::enqueue();
        }
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            parent_slug: PartnershipAdminChrome::PARENT,
            page_title: 'Partnership Settings',
            menu_title: 'Partnership Settings',
            capability: 'manage_options',
            menu_slug: self::SLUG,
            callback: [$this, 'render'],
        );
    }

    public function handleSave(): void
    {
        if (sanitize_text_field(wp_unslash($_POST['rl_partnership_settings_action'] ?? '')) !== 'save') {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to change these settings.');
        }

        check_admin_referer(self::NONCE);

        $input = [];

        foreach (['slack_channel', 'calendly_event_type'] as $key) {
            $input[$key] = sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
        }

        // Not sanitize_text_field(): it strips every %XX, so a link carrying a preset answer
        // (`?a1=Agency%20owner`) would be saved as `?a1=Agencyowner`. PartnershipSettings
        // still vets the result as an https calendly.com page.
        $input['calendly_url'] = esc_url_raw(trim((string) wp_unslash($_POST['calendly_url'] ?? '')));

        ['settings' => $settings, 'errors' => $errors] = PartnershipSettings::sanitize($input);

        update_option(PartnershipSettings::OPTION, $settings, false);

        if ($errors !== []) {
            set_transient(self::errorsKey(), $errors, MINUTE_IN_SECONDS);
        }

        wp_safe_redirect(self::url(['updated' => 1]));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to view this page.');
        }

        $settings = PartnershipSettings::all();
        $errors = get_transient(self::errorsKey());
        delete_transient(self::errorsKey());

        echo '<div class="wrap rl-admin-wrap rl-partnerships rl-partnerships-narrow rl-partnership-settings">';
        echo '<div class="rl-admin-header">';
        echo '<h1 class="rl-admin-title">Partnership settings</h1>';
        echo '<p class="rl-admin-subtitle">Where a /become-a-partner/ submission is announced, and the calendar the '
            .'prospect books their call on. Changes apply to the next submission; no deploy needed.</p>';
        echo '</div>';

        PartnershipAdminChrome::nav(self::SLUG);

        if (is_array($errors) && $errors !== []) {
            echo '<div class="notice notice-error"><p>'.implode('<br>', array_map('esc_html', $errors))
                .'</p><p>The other fields were saved; these kept their previous value.</p></div>';
        } elseif (! empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $this->renderStatus($settings);

        echo '<form method="post" class="rl-card">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="rl_partnership_settings_action" value="save">';
        echo '<p class="rl-card-title">Settings</p>';

        $this->field(
            'slack_channel',
            'Slack channel',
            $settings['slack_channel'],
            '#partnerships or C0123456789',
            'A channel id or a name. Leave blank and prospects are saved and listed under Prospects, but not '
                .'announced. There is deliberately no fallback to the sales channel. Invite the Remote Leverage app '
                .'to the channel first, or Slack will refuse the post.',
        );

        $this->field(
            'calendly_url',
            'Calendly page',
            $settings['calendly_url'],
            'https://calendly.com/remoteleverage/partnership-call',
            'The public link of the partnership call, shown straight after the form with the prospect’s name and '
                .'email filled in. Leave blank and the form shows a thank-you instead.',
        );

        $this->field(
            'calendly_event_type',
            'Calendly event type',
            $settings['calendly_event_type'],
            'https://api.calendly.com/event_types/…',
            'The same event’s API URI. The Calendly webhook uses it to leave leads alone when a partnership call is '
                .'booked or cancelled — without it, a prospect who was once a lead could have that lead marked booked.',
        );

        echo '<button type="submit" class="rl-btn rl-btn-primary">Save settings</button>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * What each value does right now, as a visitor or the webhook would meet it.
     *
     * @param  array{slack_channel: string, calendly_url: string, calendly_event_type: string}  $settings
     */
    protected function renderStatus(array $settings): void
    {
        $hasToken = SlackCredentials::botToken() !== '';
        $channel = $settings['slack_channel'];

        $rows = [
            'Slack' => match (true) {
                $channel === '' => ['off', 'Prospects are saved and listed, not announced.'],
                ! $hasToken => ['bad', 'The Slack bot token is missing on this environment, and a webhook cannot post to '.$channel.'.'],
                default => ['ok', 'Posting each prospect to '.$channel.'.'],
            },
            'Calendar' => PartnershipCallCalendar::url() !== null
                ? ['ok', 'The form opens the Calendly page after submit.']
                : ['off', 'The form shows a thank-you after submit.'],
            'Webhook guard' => match (true) {
                $settings['calendly_event_type'] !== '' => ['ok', 'Partnership bookings are recognised and never touch a lead.'],
                $settings['calendly_url'] !== '' => ['busy', 'Recognised by the page’s slug on v1 webhooks only; add the event type for v2.'],
                default => ['off', 'There is no partnership event to recognise.'],
            },
        ];

        echo '<div class="rl-card"><p class="rl-card-title">In force now</p>';
        echo '<table class="rl-table"><tbody>';

        foreach ($rows as $label => [$tone, $text]) {
            $badge = ['ok' => 'rl-badge-ok', 'bad' => 'rl-badge-bad', 'busy' => 'rl-badge-busy', 'off' => ''][$tone];

            printf(
                '<tr><th scope="row" style="width:140px">%s</th><td><span class="rl-badge %s">%s</span> %s</td></tr>',
                esc_html($label),
                esc_attr($badge),
                esc_html(['ok' => 'On', 'bad' => 'Blocked', 'busy' => 'Partial', 'off' => 'Off'][$tone]),
                esc_html($text),
            );
        }

        echo '</tbody></table></div>';
    }

    protected function field(string $name, string $label, string $value, string $placeholder, string $hint): void
    {
        printf(
            '<div class="rl-field"><label class="rl-label" for="%1$s">%2$s</label>'
            .'<input type="text" class="rl-input" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s" autocomplete="off" spellcheck="false">'
            .'<span class="rl-hint">%5$s</span></div>',
            esc_attr($name),
            esc_html($label),
            esc_attr($value),
            esc_attr($placeholder),
            esc_html($hint),
        );
    }

    protected static function errorsKey(): string
    {
        return 'rl_partnership_settings_errors_'.get_current_user_id();
    }
}
