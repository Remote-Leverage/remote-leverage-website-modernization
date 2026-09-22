<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use App\Domains\Marketing\Actions\SendCostAlertAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Send the hourly cost alert from ordinary traffic when WP-Cron has not.
 *
 * ## Why
 *
 * On 2026-09-21 the hourly event froze at 17:00 UTC and never advanced again, while
 * `wp-cron.php` was being called every five minutes and answering 200. Nothing in this repo
 * touches that event, the gate was open (`will_post: true`), and no scheduled hook of any kind ran
 * for nine hours. Whatever the cause, a reporting job should not be the thing that discovers the
 * scheduler has stopped — and somebody should not be firing every card by hand overnight.
 *
 * Traffic is already a clock here: `TierUtilizationProbe` rides on real availability fetches for
 * the same reason, and it kept working throughout. A site taking bookings all day has far more
 * requests than it needs to notice an hour has turned over.
 *
 * This is a fallback, not a replacement. WP-Cron stays the intended trigger; both paths call the
 * same action with the same gates, so neither can post anything the other would not.
 *
 * ## Cheap, and once per hour
 *
 * One option read on a request that is already loading options, and an early return on all but the
 * first request of an hour. When it does fire it takes a lock keyed on the hour before doing any
 * work, so of several simultaneous requests exactly one sends — a lock rather than a flag because
 * the containers share no memory.
 *
 * The hour is claimed *before* sending. A send that fails half way has still posted or not posted;
 * retrying it on the next request risks two contradictory cards, and a missing card is the smaller
 * problem.
 */
class TrafficTrigger
{
    /** The last hour this fired, `Y-m-d-H` in the alert's own timezone. */
    public const OPTION = 'rl_marketing_cost_alert_last_hour';

    /** Whether traffic may stand in for WP-Cron at all. */
    public const ENABLED_OPTION = 'rl_marketing_cost_alert_traffic_trigger';

    public function enabled(): bool
    {
        return (bool) get_option(self::ENABLED_OPTION, true);
    }

    /** The hour we would fire for, or null when this hour is already handled. */
    public function pendingHour(CarbonImmutable $now): ?string
    {
        $hour = $now->format('Y-m-d-H');

        return get_option(self::OPTION) === $hour ? null : $hour;
    }

    public function lastHour(): ?string
    {
        $stored = get_option(self::OPTION);

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    /**
     * Swallows everything: this runs off a visitor's request, and a reporting job is never worth a
     * 500 on a page somebody was reading. Same reasoning as the WP-Cron callback.
     */
    public function fire(?CarbonImmutable $now = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $timezone = (string) config('marketing.cost_alert.timezone', 'UTC');
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);
        $hour = $this->pendingHour($now);

        if ($hour === null) {
            return false;
        }

        // Before claiming: outside the window or the wrong environment there is nothing to send,
        // and claiming would mark the hour handled having sent nothing.
        if (! AlertWindow::enabledHere() || ! AlertWindow::contains($now)) {
            return false;
        }

        try {
            $lock = Cache::lock('rl_cost_alert_hour_'.$hour, 300);

            if (! $lock->get()) {
                return false;
            }

            try {
                update_option(self::OPTION, $hour, false);

                Log::info('TrafficTrigger: WP-Cron has not sent this hour\'s cost alert; sending from traffic.', [
                    'hour' => $hour,
                ]);

                return app(SendCostAlertAction::class)->execute($now);
            } finally {
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::error('TrafficTrigger: could not send the hourly cost alert from traffic.', [
                'hour' => $hour,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
