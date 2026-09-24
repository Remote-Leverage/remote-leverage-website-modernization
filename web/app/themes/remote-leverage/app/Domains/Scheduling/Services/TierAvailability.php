<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Actions\FetchAvailableSlotsAction;
use App\Infrastructure\Queue\Deferred;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A tier's open slots for the next seven days, as UTC instants, cached once per event type and
 * shared by every visitor.
 *
 * The booking calendar relabels these in the browser (resources/js/booking-calendar.js), so
 * nothing here depends on a visitor's zone. It used to: availability was fetched and cached per
 * zone, per month and per day, so a zone nobody had used in ten minutes was a Calendly call, and
 * so was every date click. The 2026-09-23 campaign send filled every PHP worker with those calls.
 *
 * The window is seven days because Calendly refuses a longer one on this endpoint (see
 * TierUtilizationProbe), and these event types only publish a rolling four.
 */
class TierAvailability
{
    /**
     * How long a populated answer is reused. See ttl().
     *
     * A minute, not the ten it used to be. Every visitor on a tier shares this one answer, so a
     * slot booked inside the window stays on everyone's calendar until it expires — and a
     * campaign burst is exactly when that happens, with everyone reaching for the earliest
     * times. Sharing is also what makes a minute affordable: at most one Calendly call per tier
     * per minute, where the old per-zone, per-day keys needed ten minutes to keep calls down.
     */
    public const TTL_SECONDS = 60;

    /** How long an empty one is. Never longer than a populated one — see ttl(). */
    public const EMPTY_TTL_SECONDS = 60;

    /**
     * How long a visitor entering the calendar waits on a fetch another request is already
     * making — most often the warm-up their own revenue click started — before making their own.
     * A little over CalendlyClient's 15s request timeout would be the ceiling; waiting that long
     * in front of a visitor is not, so this gives the usual sub-second fetch plenty of room.
     */
    protected const WAIT_SECONDS = 8;

    /** Outlives any single fetch, so a worker that dies mid-fetch cannot hold it for good. */
    protected const LOCK_SECONDS = 30;

    public function __construct(protected FetchAvailableSlotsAction $fetcher) {}

    public static function cacheKey(string $eventTypeUri): string
    {
        return 'rl_avail_window_'.md5($eventTypeUri);
    }

    /**
     * The open slots, from the cache when it has them.
     *
     * @return array<int, string>
     */
    public function slots(string $eventTypeUri, string $role, bool $fresh = false): array
    {
        $key = self::cacheKey($eventTypeUri);

        if ($fresh) {
            Cache::forget($key);
        } elseif (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $lock = Cache::lock($key.'_lock', self::LOCK_SECONDS);

        try {
            $lock->block(self::WAIT_SECONDS);
        } catch (LockTimeoutException) {
            // The other fetch is taking too long to keep a visitor waiting on it. A second call
            // costs one Calendly request; an empty calendar costs the booking.
            return $this->fetch($eventTypeUri, $role);
        }

        try {
            $cached = $fresh ? null : Cache::get($key);

            return is_array($cached) ? $cached : $this->fetch($eventTypeUri, $role);
        } finally {
            $lock->release();
        }
    }

    /**
     * Fill the cache ahead of the calendar, so entering it is a cache hit.
     *
     * Runs after the response of the request that asked for it (the revenue click), never in
     * front of it, and does nothing when the window is already cached or another request is
     * already fetching it — a burst of visitors warms it once, not once each.
     */
    public function warm(string $eventTypeUri, string $role): void
    {
        $key = self::cacheKey($eventTypeUri);

        if (is_array(Cache::get($key))) {
            return;
        }

        $lock = Cache::lock($key.'_lock', self::LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        try {
            if (! is_array(Cache::get($key))) {
                $this->fetch($eventTypeUri, $role);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * How long an availability answer may be reused.
     *
     * Both are a minute today, and kept apart on purpose: an empty answer is the perishable
     * one. Availability on these calendars is consumed and released continuously — twelve
     * bookings landed on `t10` during the five hours it was reporting empty on 2026-09-17 — so
     * a long hold on "nothing here" converts every momentary sell-out into an outage for
     * everyone routed to that tier. A stale populated answer costs a visitor one rejected slot
     * at submit time, which the booking path handles, so if one of these is ever raised it is
     * that one, never the empty one.
     *
     * @param  array<int, string>  $slots
     */
    public static function ttl(array $slots): int
    {
        return empty($slots) ? self::EMPTY_TTL_SECONDS : self::TTL_SECONDS;
    }

    /**
     * @return array<int, string>
     */
    protected function fetch(string $eventTypeUri, string $role): array
    {
        try {
            $start = Carbon::now('UTC')->addMinutes(5);
            $end = $start->copy()->addDays(7);

            $found = $this->fetcher->execute(
                $start->format('Y-m-d\TH:i:s\Z'),
                $end->format('Y-m-d\TH:i:s\Z'),
                'UTC',
                $eventTypeUri
            );

            $slots = [];
            foreach ($found as $slot) {
                if ($slot->available) {
                    $slots[] = Carbon::parse($slot->startTime)->utc()->format('Y-m-d\TH:i:s\Z');
                }
            }

            $slots = array_values(array_unique($slots));
            sort($slots);

            Cache::put(self::cacheKey($eventTypeUri), $slots, self::ttl($slots));
        } catch (\Throwable $e) {
            Log::warning('Failed to load availability: '.$e->getMessage());

            return [];
        }

        // Its own guard: telemetry that throws must not blank a calendar that loaded fine.
        try {
            $this->recordHealth($eventTypeUri, $role, $slots);
        } catch (\Throwable $e) {
            Log::warning('Failed to record availability health: '.$e->getMessage());
        }

        return $slots;
    }

    /**
     * Report what the tier looked like on a real fetch — never on a cache hit, so a cached empty
     * does not re-report a sell-out on every pageview.
     *
     * The monitor's dates are read in New York, the business's own zone: it is counting whether
     * the tier is bookable, not what any one visitor sees, and a sell-out is the same sell-out
     * in every zone.
     *
     * @param  array<int, string>  $slots
     */
    protected function recordHealth(string $eventTypeUri, string $role, array $slots): void
    {
        $zone = 'America/New_York';
        $dates = [];

        foreach ($slots as $iso) {
            $dates[Carbon::parse($iso)->setTimezone($zone)->format('Y-m-d')] = true;
        }

        $dates = array_keys($dates);

        app(AvailabilityHealthMonitor::class)->record(
            $role,
            $eventTypeUri,
            $dates,
            Carbon::now($zone)->format('Y-m'),
            $dates[0] ?? null
        );

        /*
         * The leading indicator, measured off the same visit but never in front of it.
         * Counting how full the window is means paging Calendly's scheduled_events, and
         * the booking widget is the last request on the site that should wait on one —
         * so it goes after the response, like the live-call Slack alerts do. The probe
         * throttles itself, so a busy hour measures once rather than once per visitor.
         *
         * `Deferred::call` rather than a closure: it queues where a queue is configured
         * and falls back to the same after-response dispatch everywhere else. Only the
         * role string crosses the boundary.
         */
        Deferred::call(TierUtilizationProbe::class, 'probe', [$role]);
    }
}
