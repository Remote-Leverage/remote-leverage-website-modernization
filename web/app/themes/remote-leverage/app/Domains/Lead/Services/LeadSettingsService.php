<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Marketing\Support\AdPlatformCredentials;

class LeadSettingsService
{
    public const OPTION_KEY = 'rl_lead_settings';

    public const MINIMUM_RETENTION_DAYS = 30;

    public const OPTIONAL_FIELDS = ['company', 'notes', 'phone_country'];

    /**
     * Default settings applied when no admin-configured value exists.
     */
    public static function defaults(): array
    {
        return [
            'notification_emails' => [],
            'optional_fields' => array_fill_keys(self::OPTIONAL_FIELDS, true),
            'retention_days' => self::MINIMUM_RETENTION_DAYS,
            'hubspot_access_token' => '',
            'hubspot_portal_id' => '',

            /*
             * Which HubSpot contact property represents "where this deal stands", and the
             * value of it that means the deal closed.
             *
             * Configurable rather than hard-coded because the answer is a portal convention,
             * not a fact about the code: a team that works `hs_lead_status` and leaves
             * `lifecyclestage` on its default needs a different property here, and getting it
             * wrong means referrers are never paid. A settings change is the right size of fix
             * for that; a redeploy is not.
             *
             * Read by SyncHubSpotLifecycleAction, which mirrors the value onto the lead and
             * fulfils the attached referral when it reaches the value below.
             */
            'hubspot_lifecycle_property' => 'lifecyclestage',
            'hubspot_lifecycle_fulfilled_value' => 'customer',
            /*
             * Slack bot token and channel.
             *
             * Genuinely secret, unlike the Sentry DSN and Customer.io write key which are now
             * committed config defaults. Settable here so an environment can be wired before
             * the ECS task definition exposes SLACK_BOT_TOKEN — the env value still wins.
             */
            'slack_bot_token' => '',
            'slack_channel' => '',
            'slack_webhook_url' => '',

            /*
             * Verifies inbound Slack interactions, and doubles as the on switch for the lead
             * alert's action buttons — see SlackInteractionController. Here for the same reason
             * as the bot token above, and more urgently: the buttons are useless in an
             * environment that cannot receive a button press, and a task-definition change is
             * the slowest way to get a secret to one.
             */
            'slack_signing_secret' => '',
            'lead_webhook_url' => '',

            /*
             * Ad platform read credentials, for the marketing cost alert.
             *
             * Meta's pair is live — set both and the cost alert reports Meta spend. Google and
             * Microsoft have no client yet and are skipped while unset; their keys are here so
             * the tokens can be pasted early, because Google issues its developer token through
             * an MCC and approves it by hand and the wait is days. Merged in from
             * AdPlatformCredentials so a credential added there does not need a second edit here.
             */
            ...self::adCredentialDefaults(),

            /*
             * Email gatekeeping, ported from three Gravity Forms plugins over one field.
             * See EmailValidationService for why the order and the fail-open behaviour matter.
             */
            /*
             * On by default. Off here meant a valid key with credits verified nothing while
             * looking exactly like verification passing — the checkbox exists to switch it off
             * without deleting the credential, not to be a second thing to remember.
             */
            'zerobounce_enabled' => true,
            'zerobounce_api_key' => '',
            'domain_validator_mode' => 'none',   // none | allow | block
            'email_domains' => '',               // one per line
            'blacklisted_emails' => '',          // comma separated
            'email_validation_message' => '',
        ];
    }

    /**
     * Every ad platform credential key, defaulting to empty.
     *
     * @return array<string, string>
     */
    private static function adCredentialDefaults(): array
    {
        $defaults = [];

        foreach (AdPlatformCredentials::settingKeys() as $keys) {
            foreach ($keys as $key) {
                $defaults[$key] = '';
            }
        }

        return $defaults;
    }

    /**
     * Coerce the domain validator mode to one this code understands.
     *
     * Anything unrecognised — including absent — becomes `none`. A validator that silently
     * falls back to blocking would reject every lead the moment a form posted a typo.
     */
    protected function validatorMode(mixed $mode): string
    {
        $mode = is_string($mode) ? strtolower(trim($mode)) : '';

        return in_array($mode, ['none', 'allow', 'block'], true) ? $mode : 'none';
    }

    /**
     * Read the current admin-configured Lead settings, merged over defaults.
     */
    public function get(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        if (! is_array($stored)) {
            $stored = [];
        }

        $settings = array_merge(self::defaults(), $stored);
        $settings['optional_fields'] = array_merge(self::defaults()['optional_fields'], $stored['optional_fields'] ?? []);

        return $settings;
    }

    /**
     * Validate, sanitize, and persist Lead settings.
     *
     * @return array{success: bool, errors: array<string>}
     */
    public function save(array $input): array
    {
        $errors = [];

        $retentionDays = (int) ($input['retention_days'] ?? self::MINIMUM_RETENTION_DAYS);
        if ($retentionDays < self::MINIMUM_RETENTION_DAYS) {
            $errors[] = 'Retention policy violation: retention days cannot be less than the 30-day minimum floor (ADR-0008).';
        }

        $emails = array_filter(array_map('trim', explode(',', (string) ($input['notification_emails'] ?? ''))));
        $invalidEmails = array_filter($emails, fn (string $email) => function_exists('is_email') ? ! is_email($email) : ! filter_var($email, FILTER_VALIDATE_EMAIL));
        if (! empty($invalidEmails)) {
            $errors[] = 'One or more notification email addresses are invalid: '.implode(', ', $invalidEmails);
        }

        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $optionalFields = [];
        foreach (self::OPTIONAL_FIELDS as $field) {
            $optionalFields[$field] = ! empty($input['optional_fields'][$field]);
        }

        /*
         * A credential the caller did not mention keeps the value it already had.
         *
         * This array replaces the stored blob wholesale, so `?? ''` on a key the settings form
         * does not render silently wipes it on every save. That is not hypothetical: the Slack
         * bot token and channel have never been on the form — they arrive by environment sync —
         * and saving the screen for an unrelated reason blanked them, after which alerts
         * quietly fell back to the incoming webhook with nothing to say why.
         *
         * `array_key_exists` rather than `isset` or `??` is the whole fix: submitted-but-empty
         * still clears the value, which is how a credential is meant to be removed. Only
         * *absent* means "not on this form, leave it alone".
         */
        $current = $this->get();

        $keep = fn (string $key): string => array_key_exists($key, $input)
            ? trim((string) $input[$key])
            : trim((string) ($current[$key] ?? ''));

        $settings = [
            'notification_emails' => array_values(array_map([$this, 'sanitizeEmail'], $emails)),
            'optional_fields' => $optionalFields,
            'retention_days' => $retentionDays,
            'hubspot_access_token' => $keep('hubspot_access_token'),
            'hubspot_portal_id' => $keep('hubspot_portal_id'),
            // Falling back to the default rather than storing '' matters: an empty property
            // name would make every batch read return nothing, which presents as "no lead has
            // moved in HubSpot for days" — indistinguishable from the thing staleness is
            // supposed to detect.
            'hubspot_lifecycle_property' => $keep('hubspot_lifecycle_property') ?: 'lifecyclestage',
            'hubspot_lifecycle_fulfilled_value' => $keep('hubspot_lifecycle_fulfilled_value') ?: 'customer',
            'zerobounce_enabled' => ! empty($input['zerobounce_enabled']),
            'zerobounce_api_key' => $keep('zerobounce_api_key'),
            'domain_validator_mode' => $this->validatorMode($input['domain_validator_mode'] ?? null),
            // Stored as typed, normalised on read: the admin pastes a list and should get the
            // same list back, not a re-sorted, de-duplicated version of it.
            'email_domains' => trim((string) ($input['email_domains'] ?? '')),
            'blacklisted_emails' => trim((string) ($input['blacklisted_emails'] ?? '')),
            'email_validation_message' => trim((string) ($input['email_validation_message'] ?? '')),
            'slack_bot_token' => $keep('slack_bot_token'),
            'slack_channel' => $keep('slack_channel'),
            'slack_signing_secret' => $keep('slack_signing_secret'),
            'slack_webhook_url' => $this->sanitizeUrl($keep('slack_webhook_url')),
            'lead_webhook_url' => $this->sanitizeUrl($keep('lead_webhook_url')),
        ];

        /*
         * Ad credentials go through the same `$keep` rule as every other secret here: submitted
         * empty clears it, absent leaves it alone. That distinction is why the Slack bot token
         * survived a settings save it was never on the form for, and these will not be on every
         * form either.
         */
        foreach (AdPlatformCredentials::settingKeys() as $keys) {
            foreach ($keys as $key) {
                $settings[$key] = $keep($key);
            }
        }

        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $settings);
            // Kept in sync for PurgeOldLeadsAction's existing retention lookup (pre-dates this settings screen).
            update_option('rl_lead_retention_days', $retentionDays);
            update_option('rl_slack_webhook_url', $settings['slack_webhook_url']);
            update_option('rl_lead_webhook_url', $settings['lead_webhook_url']);
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Whether an optional lead-capture field is enabled for display.
     */
    public function isFieldEnabled(string $field): bool
    {
        return (bool) ($this->get()['optional_fields'][$field] ?? true);
    }

    protected function sanitizeEmail(string $email): string
    {
        return function_exists('sanitize_email') ? sanitize_email($email) : $email;
    }

    protected function sanitizeUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        return function_exists('esc_url_raw') ? esc_url_raw($url) : $url;
    }
}
