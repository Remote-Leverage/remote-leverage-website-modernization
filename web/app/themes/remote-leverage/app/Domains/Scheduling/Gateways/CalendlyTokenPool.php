<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use Illuminate\Support\Facades\Cache;

class CalendlyTokenPool
{
    public const OPTION_KEY = 'rl_calendly_token_pool';

    protected const RATE_LIMIT_PREFIX = 'rl_calendly_token_rate_limited_';

    protected const FAILURE_PREFIX = 'rl_calendly_token_failures_';

    protected const FAILURE_TTL_SECONDS = 7200; // 2 hours, matches legacy

    protected const CIRCUIT_BREAKER_THRESHOLD = 3;

    /** @var array<int, array{label: string, token: string, enabled: bool}> */
    protected array $rows;

    public function __construct(?array $rows = null)
    {
        $this->rows = $rows !== null ? $this->normalizeRows($rows) : $this->loadFromOptionWithMigration();
    }

    /**
     * All rows (enabled and disabled), for the admin repeater.
     *
     * @return array<int, array{label: string, token: string, enabled: bool}>
     */
    public function allRows(): array
    {
        return $this->rows;
    }

    /**
     * Enabled tokens, excluding currently rate-limited ones. When $context is
     * 'metadata', additionally excludes tokens whose metadata circuit breaker is open.
     *
     * @return array<int, array{label: string, token: string}>
     */
    public function getEligibleTokens(?string $context = null): array
    {
        $eligible = [];

        foreach ($this->rows as $row) {
            if (empty($row['enabled']) || empty($row['token'])) {
                continue;
            }

            if ($this->isRateLimited($row['token'])) {
                continue;
            }

            if ($context === 'metadata' && $this->isCircuitOpen($row['token'], 'metadata')) {
                continue;
            }

            $eligible[] = ['label' => $row['label'], 'token' => $row['token']];
        }

        return $eligible;
    }

    public function markRateLimited(string $token, int $cooldownSeconds = 60): void
    {
        Cache::put(self::rateLimitKey($token), true, now()->addSeconds($cooldownSeconds));
    }

    public function isRateLimited(string $token): bool
    {
        return Cache::has(self::rateLimitKey($token));
    }

    public function recordFailure(string $token, string $context = 'metadata'): void
    {
        $key = self::failureKey($token, $context);
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addSeconds(self::FAILURE_TTL_SECONDS));
    }

    public function failureCount(string $token, string $context = 'metadata'): int
    {
        return (int) Cache::get(self::failureKey($token, $context), 0);
    }

    public function isCircuitOpen(string $token, string $context = 'metadata'): bool
    {
        return $this->failureCount($token, $context) >= self::CIRCUIT_BREAKER_THRESHOLD;
    }

    public function clearFailures(string $token, ?string $context = null): void
    {
        $contexts = $context !== null ? [$context] : ['metadata', 'booking'];
        foreach ($contexts as $ctx) {
            Cache::forget(self::failureKey($token, $ctx));
        }
    }

    /**
     * Reset the circuit breaker for every enabled token. Called before a scheduled
     * booking retry, mirroring legacy's handle_retry_booking_cron behavior.
     */
    public function clearAllFailures(?string $context = 'metadata'): void
    {
        foreach ($this->rows as $row) {
            if (! empty($row['token'])) {
                $this->clearFailures($row['token'], $context);
            }
        }
    }

    public function addToken(string $label, string $token, bool $enabled = true): void
    {
        $this->rows[] = ['label' => $label, 'token' => $token, 'enabled' => $enabled];
        $this->persist();
    }

    public function setEnabled(int $index, bool $enabled): void
    {
        if (isset($this->rows[$index])) {
            $this->rows[$index]['enabled'] = $enabled;
            $this->persist();
        }
    }

    public function removeToken(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->persist();
    }

    /**
     * Bulk-replace the entire pool (used by the admin repeater form save).
     *
     * @param  array<int, array{label?: string, token?: string, enabled?: mixed}>  $rows
     */
    public function replaceAll(array $rows): void
    {
        $this->rows = $this->normalizeRows($rows);
        $this->persist();
    }

    public static function maskToken(string $token): string
    {
        if (strlen($token) <= 10) {
            return str_repeat('•', strlen($token));
        }

        return substr($token, 0, 6).'…'.substr($token, -4);
    }

    /**
     * Load the pool from its WP option, seeding it once from the legacy env vars
     * if the option has genuinely never been set. An explicitly-emptied pool
     * (option value `[]`) must NOT be re-seeded, so the option default is `null`,
     * not `[]`.
     *
     * @return array<int, array{label: string, token: string, enabled: bool}>
     */
    protected function loadFromOptionWithMigration(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, null) : null;

        if (is_array($stored)) {
            return $this->normalizeRows($stored);
        }

        $seeded = $this->rowsFromEnvSeed();

        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $seeded);
        }

        return $seeded;
    }

    /**
     * @return array<int, array{label: string, token: string, enabled: bool}>
     */
    protected function rowsFromEnvSeed(): array
    {
        $labels = ['Primary', 'Pool 2', 'Pool 3', 'Pool 4'];
        $seeded = [];

        foreach (config('services.calendly.api_keys', []) as $i => $token) {
            if (empty($token)) {
                continue;
            }

            $seeded[] = [
                'label' => $labels[$i] ?? ('Pool '.($i + 1)),
                'token' => $token,
                'enabled' => true,
            ];
        }

        return $seeded;
    }

    /**
     * @return array<int, array{label: string, token: string, enabled: bool}>
     */
    protected function normalizeRows(array $rows): array
    {
        $normalized = [];

        foreach (array_values($rows) as $row) {
            if (! is_array($row) || empty($row['token'])) {
                continue;
            }

            $normalized[] = [
                'label' => (string) ($row['label'] ?? 'Untitled'),
                'token' => (string) $row['token'],
                'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $normalized;
    }

    protected function persist(): void
    {
        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $this->rows);
        }
    }

    protected static function rateLimitKey(string $token): string
    {
        return self::RATE_LIMIT_PREFIX.substr(hash('sha256', $token), 0, 16);
    }

    protected static function failureKey(string $token, string $context): string
    {
        return self::FAILURE_PREFIX.substr(hash('sha256', $token), 0, 16).'_'.$context;
    }
}
