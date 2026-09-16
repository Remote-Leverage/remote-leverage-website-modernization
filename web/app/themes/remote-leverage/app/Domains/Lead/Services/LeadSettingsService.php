<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

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
             * Slack bot token and channel.
             *
             * Genuinely secret, unlike the Sentry DSN and Customer.io write key which are now
             * committed config defaults. Settable here so an environment can be wired before
             * the ECS task definition exposes SLACK_BOT_TOKEN — the env value still wins.
             */
            'slack_bot_token' => '',
            'slack_channel' => '',
            'slack_webhook_url' => '',
            'lead_webhook_url' => '',

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

        $settings = [
            'notification_emails' => array_values(array_map([$this, 'sanitizeEmail'], $emails)),
            'optional_fields' => $optionalFields,
            'retention_days' => $retentionDays,
            'hubspot_access_token' => trim((string) ($input['hubspot_access_token'] ?? '')),
            'hubspot_portal_id' => trim((string) ($input['hubspot_portal_id'] ?? '')),
            'zerobounce_enabled' => ! empty($input['zerobounce_enabled']),
            'zerobounce_api_key' => trim((string) ($input['zerobounce_api_key'] ?? '')),
            'domain_validator_mode' => $this->validatorMode($input['domain_validator_mode'] ?? null),
            // Stored as typed, normalised on read: the admin pastes a list and should get the
            // same list back, not a re-sorted, de-duplicated version of it.
            'email_domains' => trim((string) ($input['email_domains'] ?? '')),
            'blacklisted_emails' => trim((string) ($input['blacklisted_emails'] ?? '')),
            'email_validation_message' => trim((string) ($input['email_validation_message'] ?? '')),
            'slack_bot_token' => trim((string) ($input['slack_bot_token'] ?? '')),
            'slack_channel' => trim((string) ($input['slack_channel'] ?? '')),
            'slack_webhook_url' => $this->sanitizeUrl((string) ($input['slack_webhook_url'] ?? '')),
            'lead_webhook_url' => $this->sanitizeUrl((string) ($input['lead_webhook_url'] ?? '')),
        ];

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
