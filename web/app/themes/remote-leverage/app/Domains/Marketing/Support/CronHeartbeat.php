<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;

/**
 * Tell Sentry the hourly cron tick happened, so Sentry can say when it stops.
 *
 * ## Why a check-in rather than a monitor of our own
 *
 * On 2026-09-21 the scheduler stopped at 16:25 UTC and nothing reported it for ten hours. Any
 * monitor written here shares one hole: it is code, and code has to run to complain, so anything
 * riding on the cron tick is as dead as the tick. Sentry Crons inverts that — we say "this ran",
 * and Sentry raises the alarm when the saying stops. Nothing of ours needs to be alive at the
 * moment of failure, which matters because the failure mode is that nothing of ours is alive.
 *
 * ## One check-in, sent after the work
 *
 * The first version opened an `in_progress` check-in and closed it with `ok`. That produced a
 * "timeout check-in was detected" failure every hour with `Last Successful Check-In: Never` — the
 * open arrived, the close did not, and Sentry correctly reported a run that never finished. The
 * alert it raised was about the monitoring, not about cron, which is the worst kind of false alarm:
 * it looks exactly like the outage it was built to catch.
 *
 * So there is no open/close pair any more. One terminal check-in goes out once the tick has done
 * its work. A tick that never happens sends nothing and Sentry reports a missed check-in, which is
 * the whole requirement. A tick that throws sends `error`.
 *
 * The cost of dropping the pair is `max_runtime` — Sentry can no longer notice a run that hangs
 * forever, because a hung run and a dead scheduler now look the same from outside. That is an
 * acceptable trade for a monitor that does not cry wolf hourly, and the hung case is covered
 * anyway: the next tick is an hour later and will check in on its own.
 *
 * ## What it measures
 *
 * The tick, not the send. The alert legitimately posts nothing outside its window, and a monitor
 * treating that as failure would page nightly. It is attached to the WP-Cron callback alone — a
 * human firing a card by hand must not silence a monitor whose job is noticing the scheduler is
 * dead, which is exactly the state somebody firing cards by hand is in.
 *
 * `marketing.cost_alert.sentry_monitor` is the slug; empty switches it off with no Sentry traffic.
 */
class CronHeartbeat
{
    /**
     * Record that a tick ran. `$ok` is about the tick, never about whether a card posted.
     *
     * Swallows everything: the monitor observes the work, it does not become a new way for the
     * work to die.
     */
    public function ran(bool $ok = true, ?float $seconds = null): void
    {
        $slug = trim((string) config('marketing.cost_alert.sentry_monitor', ''));

        if ($slug === '' || ! function_exists('Sentry\captureCheckIn')) {
            return;
        }

        try {
            \Sentry\captureCheckIn(
                slug: $slug,
                status: $ok ? CheckInStatus::ok() : CheckInStatus::error(),
                duration: $seconds,
                monitorConfig: new MonitorConfig(
                    MonitorSchedule::crontab('0 * * * *'),

                    /*
                     * Twenty minutes of grace. WP-Cron fires on the tick following the hour rather
                     * than on the hour itself, and a rolling deploy can delay one. Ten hours of
                     * silence is what is worth paging about; ninety seconds of lateness is not.
                     */
                    checkinMargin: 20,

                    // No max_runtime: without an in_progress open there is no run for Sentry to
                    // time out, which is the point of the rewrite.
                    maxRuntime: null,
                    timezone: (string) config('marketing.cost_alert.timezone', 'UTC'),
                ),
            );
        } catch (\Throwable) {
            // Deliberately silent — see the class docblock.
        }
    }
}
