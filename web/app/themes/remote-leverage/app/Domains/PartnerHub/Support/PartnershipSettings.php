<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Support;

/**
 * Where a `/become-a-partner/` prospect is announced, and which calendar follows the form.
 *
 * Owned by Partners Hub → Partnership Settings (`PartnershipSettingsAdmin`), not the
 * environment. The three values were `RL_PARTNERSHIP_*` variables for the length of one branch:
 * the same GitHub secret → Secrets Manager → entrypoint → config-cache path that shipped
 * production with no pixels deferred (see PixelDeferral), for values that are not secrets and
 * that the partnerships team, not a deploy, should be able to change.
 *
 * Every value is off until set, and "off" is deliberate for each:
 *
 *  - **No channel** means no Slack post at all — never a fallback to the default channel,
 *    which is `#new-appts`, the per-lead stream the sales team works all day. A prospect there
 *    is exactly what this form exists to prevent. The prospect is still saved and listed.
 *  - **No calendar** means a short thank-you in place of the form.
 *  - **No event type** means the Calendly webhook cannot tell a v2 partnership booking from a
 *    sales one, so it cannot keep the invitee's lead from being marked booked. v1 bodies are
 *    still matched on the calendar link's slug. See PartnershipCallCalendar.
 */
final class PartnershipSettings
{
    public const OPTION = 'rl_partnership_settings';

    private const DEFAULTS = [
        'slack_channel' => '',
        'calendly_url' => '',
        'calendly_event_type' => '',
    ];

    /** A Slack channel id: public (C), private (G) or shared (D is a DM, so not accepted). */
    private const CHANNEL_ID = '/^[CG][A-Z0-9]{8,}$/';

    /** A Slack channel name as Slack allows it: lower case, digits, `-`, `_` and `.`, up to 80. */
    private const CHANNEL_NAME = '/^#?[a-z0-9][a-z0-9._-]{0,79}$/';

    private const EVENT_TYPE = '#^https://api\.calendly\.com/event_types/[A-Za-z0-9-]{1,64}$#';

    /**
     * @return array{slack_channel: string, calendly_url: string, calendly_event_type: string}
     */
    public static function all(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION, []) : [];
        $stored = is_array($stored) ? $stored : [];

        $settings = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $key) {
            $settings[$key] = trim((string) ($stored[$key] ?? ''));
        }

        return $settings;
    }

    /** A channel id (`C0…`) or a `#name`, or '' when prospects are not announced. */
    public static function slackChannel(): string
    {
        return self::all()['slack_channel'];
    }

    /** The public scheduling page as saved. PartnershipCallCalendar::url() vets it before use. */
    public static function calendlyUrl(): string
    {
        return self::all()['calendly_url'];
    }

    /** The same event's `https://api.calendly.com/event_types/…` URI, or ''. */
    public static function calendlyEventType(): string
    {
        return self::all()['calendly_event_type'];
    }

    /**
     * Validate a submission from the settings screen.
     *
     * A value that fails is not saved: the field keeps what it had, and the reason is returned
     * for the screen to show. Saving a malformed calendar link would put a broken iframe on a
     * public page, and a malformed channel would fail silently in Slack, so neither is worth
     * storing "as typed".
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, string>|null  $current  what is saved now; read when omitted
     * @return array{settings: array{slack_channel: string, calendly_url: string, calendly_event_type: string}, errors: list<string>}
     */
    public static function sanitize(array $input, ?array $current = null): array
    {
        $current ??= self::all();
        $settings = [];
        $errors = [];

        $channel = trim((string) ($input['slack_channel'] ?? ''));

        if ($channel === '' || preg_match(self::CHANNEL_ID, $channel) === 1) {
            $settings['slack_channel'] = $channel;
        } elseif (preg_match(self::CHANNEL_NAME, strtolower($channel)) === 1) {
            // Names are stored with their `#`, which is how SlackTransport's override expects
            // them and how anyone reading the screen back recognises one.
            $settings['slack_channel'] = '#'.ltrim(strtolower($channel), '#');
        } else {
            $settings['slack_channel'] = $current['slack_channel'] ?? '';
            $errors[] = 'Slack channel: use a channel id (C0…) or a channel name such as #partnerships.';
        }

        $url = trim((string) ($input['calendly_url'] ?? ''));

        if ($url === '' || self::isSchedulingPage($url)) {
            $settings['calendly_url'] = $url;
        } else {
            $settings['calendly_url'] = $current['calendly_url'] ?? '';
            $errors[] = 'Calendly page: use the public https://calendly.com/… link for the partnership call.';
        }

        $eventType = rtrim(trim((string) ($input['calendly_event_type'] ?? '')), '/');

        if ($eventType === '' || preg_match(self::EVENT_TYPE, $eventType) === 1) {
            $settings['calendly_event_type'] = $eventType;
        } else {
            $settings['calendly_event_type'] = $current['calendly_event_type'] ?? '';
            $errors[] = 'Calendly event type: use the event’s https://api.calendly.com/event_types/… URI.';
        }

        return ['settings' => $settings, 'errors' => $errors];
    }

    /**
     * An https calendly.com page with a path — all Calendly serves scheduling pages from. The
     * value goes into an iframe on a public page, so nothing else may pass.
     */
    public static function isSchedulingPage(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'calendly.com'
            && trim((string) ($parts['path'] ?? ''), '/') !== '';
    }
}
