<?php

declare(strict_types=1);

namespace App\Domains\Referral\Services;

class ReferralSettingsService
{
    public const OPTION_KEY = 'rl_referral_settings';

    public const DEFAULT_COOKIE_DAYS = AttributionEngine::DEFAULT_COOKIE_DAYS;

    public const DEFAULT_REWARD_TYPE = 'cash';

    public const DEFAULT_REWARD_CURRENCY = 'USD';

    /**
     * Default settings applied when no admin-configured value exists.
     * Reward amount falls back to the env-configured value (config/services.php)
     * so a fresh install keeps working before any admin visits the settings screen.
     */
    public static function defaults(): array
    {
        return [
            'default_reward_amount' => (float) config('services.referral.default_reward_amount', 14.00),
            'default_reward_currency' => self::DEFAULT_REWARD_CURRENCY,
            'default_reward_type' => self::DEFAULT_REWARD_TYPE,
            'cookie_days' => self::DEFAULT_COOKIE_DAYS,
            'landing_pages' => self::defaultLandingPages(),
        ];
    }

    /**
     * Fallback landing pages ported from the original hardcoded list.
     */
    public static function defaultLandingPages(): array
    {
        return [
            ['name' => 'Main Homepage', 'base_url' => 'https://remoteleverage.com'],
            ['name' => 'Hire Executive Assistants', 'base_url' => 'https://remoteleverage.com/services/executive-assistants'],
            ['name' => 'Hire Real Estate Assistants', 'base_url' => 'https://remoteleverage.com/services/real-estate'],
            ['name' => 'Strategy Consultation Funnel', 'base_url' => 'https://remoteleverage.com/book-consultation'],
        ];
    }

    /**
     * Read the current admin-configured Referral settings, merged over defaults.
     */
    public function get(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        if (! is_array($stored)) {
            $stored = [];
        }

        $settings = array_merge(self::defaults(), $stored);

        if (empty($settings['landing_pages']) || ! is_array($settings['landing_pages'])) {
            $settings['landing_pages'] = self::defaultLandingPages();
        }

        return $settings;
    }

    /**
     * Validate, sanitize, and persist Referral settings.
     *
     * @return array{success: bool, errors: array<string>}
     */
    public function save(array $input): array
    {
        $errors = [];

        $amount = (float) ($input['default_reward_amount'] ?? 0);
        if ($amount <= 0) {
            $errors[] = 'Default reward amount must be greater than zero.';
        }

        $currency = strtoupper(trim((string) ($input['default_reward_currency'] ?? self::DEFAULT_REWARD_CURRENCY)));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = 'Reward currency must be a 3-letter ISO code (e.g. USD).';
        }

        $cookieDays = (int) ($input['cookie_days'] ?? self::DEFAULT_COOKIE_DAYS);
        if ($cookieDays < 1) {
            $errors[] = 'Cookie lifetime must be at least 1 day.';
        }

        $landingPages = $this->parseLandingPages((string) ($input['landing_pages'] ?? ''));
        if (empty($landingPages)) {
            $errors[] = 'At least one landing page is required (format: "Name = https://url", one per line).';
        }

        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $settings = [
            'default_reward_amount' => round($amount, 2),
            'default_reward_currency' => $currency,
            'default_reward_type' => trim((string) ($input['default_reward_type'] ?? self::DEFAULT_REWARD_TYPE)) ?: self::DEFAULT_REWARD_TYPE,
            'cookie_days' => $cookieDays,
            'landing_pages' => $landingPages,
        ];

        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $settings);
            // Kept in sync for AttributionEngine::getCookieMaxAge()'s existing option lookup (pre-dates this settings screen).
            update_option('rl_ref_cookie_days', $cookieDays);
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Serialize landing pages back into the "Name = URL" textarea format for the settings form.
     */
    public function landingPagesToText(array $landingPages): string
    {
        return implode("\n", array_map(fn (array $page) => "{$page['name']} = {$page['base_url']}", $landingPages));
    }

    /**
     * Parse a "Name = https://url" per-line textarea into landing page entries.
     */
    protected function parseLandingPages(string $raw): array
    {
        $pages = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $url] = array_map('trim', explode('=', $line, 2));
            $url = function_exists('esc_url_raw') ? esc_url_raw($url) : $url;

            if ($name === '' || $url === '') {
                continue;
            }

            $pages[] = ['name' => $name, 'base_url' => $url];
        }

        return $pages;
    }
}
