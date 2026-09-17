<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Gateways\CalendlyClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Measures how full a revenue tier's rolling booking window is, and hands the numbers to
 * AvailabilityHealthMonitor.
 *
 * ## Why the window is four days and not a month
 *
 * These event types publish a rolling four-day booking window, so a month is the wrong unit in
 * both directions: most of it is not offered yet, and the part that is has already been measured
 * by the time a visitor pages forward to it. Measuring the month someone happens to be looking at
 * is the same mistake the sell-out alert had to be corrected for once already — a legitimately
 * empty December reads as 100% full.
 *
 * Calendly does not expose an event type's date-range rule through the API, so the window length
 * is config rather than something that can be read back. `booking.availability.window_days`.
 *
 * ## Why this is not in the booking request
 *
 * Counting the booked side means paging `/scheduled_events`, and the booking widget is the last
 * request in the site that should be waiting on one. The probe runs after the response on a real
 * availability fetch and on an hourly cron, and self-throttles so a traffic spike measures once
 * rather than once per visitor.
 */
class TierUtilizationProbe
{
    protected const THROTTLE_PREFIX = 'rl_utilization_probe_';

    public function __construct(
        protected CalendlyClient $client,
        protected CalendlyEventTypeRoleResolver $roles,
        protected AvailabilityHealthMonitor $monitor,
    ) {}

    /**
     * Measure every tier that has an event type mapped to it.
     */
    public function probeAll(): void
    {
        foreach (['t10', 't0'] as $role) {
            $this->probe($role);
        }
    }

    /**
     * Measure one tier, unless it was measured recently enough.
     *
     * @return float|null The fill fraction, or null when it was throttled, unmapped, or unmeasurable
     */
    public function probe(string $role, bool $force = false): ?float
    {
        if (! (bool) config('booking.availability.enabled', true)) {
            return null;
        }

        $eventTypeUri = $this->roles->get($role);

        if (empty($eventTypeUri)) {
            return null;
        }

        $throttleKey = self::THROTTLE_PREFIX.$role;

        if (! $force && Cache::has($throttleKey)) {
            return null;
        }

        $ttl = max(60, (int) config('booking.availability.probe_ttl', 300));
        Cache::put($throttleKey, true, now()->addSeconds($ttl));

        $windowDays = max(1, (int) config('booking.availability.window_days', 4));

        /*
         * Calendly rejects a start_time in the past, and a window longer than seven days, so the
         * near edge is nudged forward and the length is clamped. Zulu format, strictly — the API
         * is fussy about it, which is why FetchAvailableSlotsAction spells it out the same way.
         */
        $start = Carbon::now('UTC')->addMinutes(5);
        $end = $start->copy()->addDays(min(7, $windowDays));

        try {
            $open = $this->client->getAvailableSlots(
                $eventTypeUri,
                $start->format('Y-m-d\TH:i:s\Z'),
                $end->format('Y-m-d\TH:i:s\Z'),
            );

            $booked = $this->client->countBookedEvents(
                $eventTypeUri,
                $start->format('Y-m-d\TH:i:s\Z'),
                $end->format('Y-m-d\TH:i:s\Z'),
            );

            return $this->monitor->recordUtilization(
                $role,
                $eventTypeUri,
                count($open),
                $booked,
                $windowDays,
            );
        } catch (\Throwable $e) {
            // Best effort by construction: this runs after the response or on cron, and a health
            // check that throws is worse than one that misses a reading.
            Log::warning("TierUtilizationProbe: could not measure {$role}: ".$e->getMessage());

            return null;
        }
    }
}
