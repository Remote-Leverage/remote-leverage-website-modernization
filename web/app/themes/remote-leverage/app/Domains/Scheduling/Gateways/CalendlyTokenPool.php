<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use Illuminate\Support\Facades\Cache;

class CalendlyTokenPool
{
    protected const CACHE_KEY_PREFIX = 'rl_calendly_token_rate_limited_';

    /** @var string[] */
    protected array $tokens;

    public function __construct(array $tokens = [])
    {
        $this->tokens = array_values(array_filter($tokens));
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->tokens;
    }

    /**
     * Return the first token in the pool that isn't currently flagged as rate-limited.
     * Falls back to the first token if every token is flagged, so requests still attempt to go through.
     */
    public function getToken(): ?string
    {
        foreach ($this->tokens as $token) {
            if (! Cache::has(self::rateLimitKey($token))) {
                return $token;
            }
        }

        return $this->tokens[0] ?? null;
    }

    /**
     * Flag a token as rate-limited so subsequent lookups skip it until the cooldown expires.
     */
    public function markRateLimited(string $token, int $cooldownSeconds = 60): void
    {
        Cache::put(self::rateLimitKey($token), true, now()->addSeconds($cooldownSeconds));
    }

    protected static function rateLimitKey(string $token): string
    {
        return self::CACHE_KEY_PREFIX.substr(hash('sha256', $token), 0, 16);
    }
}
