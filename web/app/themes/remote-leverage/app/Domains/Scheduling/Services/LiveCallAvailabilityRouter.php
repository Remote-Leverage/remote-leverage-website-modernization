<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use Illuminate\Support\Facades\Cache;

class LiveCallAvailabilityRouter
{
    public const CACHE_KEY = 'rl_live_call_availability';

    public const DEFAULT_MEET_URL = 'https://meet.google.com/rl-instant-consult';

    /**
     * Determine if a live sales consultant is currently online and available.
     */
    public function isAvailable(): bool
    {
        return (bool) Cache::get(self::CACHE_KEY, true);
    }

    /**
     * Set consultant availability status (e.g., via Slack webhook or admin toggle).
     */
    public function setAvailability(bool $available, int $ttlMinutes = 30): void
    {
        Cache::put(self::CACHE_KEY, $available, now()->addMinutes($ttlMinutes));
    }

    /**
     * Get active consultant count or status message.
     */
    public function getStatus(): array
    {
        $available = $this->isAvailable();

        return [
            'available' => $available,
            'status_label' => $available ? 'Consultants Online Now' : 'Leave a Message',
            'online_count' => $available ? 2 : 0,
            'meet_url' => $available ? env('LIVE_CALL_MEET_URL', self::DEFAULT_MEET_URL) : null,
        ];
    }
}
