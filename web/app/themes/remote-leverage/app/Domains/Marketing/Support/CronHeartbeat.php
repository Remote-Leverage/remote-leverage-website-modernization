<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Support;

use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;

/**
 * Tell Sentry the hourly cron tick happened, so Sentry can say when it stops.
 *
 * ## Why a check-in rather than an alert we raise ourselves
 *
 * On 2026-09-21 the scheduler stopped at 16:25 UTC and nothing said so for ten hours. Not Sentry,
 * not Slack, not the dashboard. The outage was discovered because a Slack card failed to arrive,
 * and the day after went into working out why.
 *
 * Every monitor we could have written has the same hole: it is code, and code has to be *run* to
 * complain. A check that rides on the cron tick cannot report that the cron tick stopped, and one
 * that rides on web traffic is a second scheduler to get wrong — which is exactly the fallback that
 * was tried and removed the same night for double-posting.
 *
 * Sentry Crons inverts it. We say "this ran" each time it runs, and Sentry raises the alert when a
 * check-in fails to arrive inside the window. Nothing of ours has to be alive at the moment of
 * failure, which is the whole point: the failure mode is that nothing of ours is alive.
 *
 * ## Why it wraps the tick, not the send
 *
 * The question being monitored is "is the scheduler running", not "did a card post". The alert
 * legitimately posts nothing outside its window or on an environment it is switched off in, and a
 * monitor that treated those as failures would cry wolf nightly. So the check-in is `ok` whenever
 * the tick *ran*, whatever the send decided, and `error` only when the tick threw.
 *
 * It is attached to the WP-Cron callback specifically, and not to the console command or the
 * dashboard's "send now" button: a human firing a card by hand must not silence a monitor that
 * exists to notice the scheduler is dead, which is precisely the state somebody firing cards by
 * hand is in.
 *
 * ## Configuration
 *
 * `marketing.cost_alert.sentry_monitor` is the monitor slug, and an empty value switches this off
 * entirely — no check-in, no Sentry traffic — for environments that should not be monitored. The
 * schedule is declared here rather than in Sentry's UI so that the window Sentry judges against
 * and the schedule WordPress actually runs on are changed in the same commit.
 */
class CronHeartbeat
{
    /**
     * Let Sentry know a tick has begun; returns the id to close it with, or null when disabled.
     *
     * Failing to check in must never break the tick — the monitor is there to observe the work,
     * not to be a new way for it to die.
     */
    public function start(): ?string
    {
        $slug = $this->slug();

        if ($slug === '' || ! function_exists('Sentry\captureCheckIn')) {
            return null;
        }

        try {
            return \Sentry\captureCheckIn(
                slug: $slug,
                status: CheckInStatus::inProgress(),
                monitorConfig: new MonitorConfig(
                    MonitorSchedule::crontab('0 * * * *'),

                    /*
                     * Twenty minutes of grace. WP-Cron fires on the tick that follows the hour
                     * rather than on the hour itself, a rolling deploy can delay one, and the
                     * send itself took 13.5s on the last healthy run. Ten hours of silence is
                     * the thing worth paging about; ninety seconds of lateness is not.
                     */
                    checkinMargin: 20,

                    /*
                     * The snapshot is roughly twenty-five queries and two or three warehouse
                     * round trips. Five minutes is far beyond its worst observed run and still
                     * well short of the next tick, so a hung run is reported before the one
                     * after it starts.
                     */
                    maxRuntime: 5,
                    timezone: (string) config('marketing.cost_alert.timezone', 'UTC'),
                ),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /** Close the tick. `$ok` is about the tick running, never about whether a card posted. */
    public function finish(?string $checkInId, bool $ok, ?float $seconds = null): void
    {
        $slug = $this->slug();

        if ($checkInId === null || $slug === '' || ! function_exists('Sentry\captureCheckIn')) {
            return;
        }

        try {
            \Sentry\captureCheckIn(
                slug: $slug,
                status: $ok ? CheckInStatus::ok() : CheckInStatus::error(),
                duration: $seconds,
                checkInId: $checkInId,
            );
        } catch (\Throwable) {
            // Same reasoning as start(): observing the work must not endanger it.
        }
    }

    private function slug(): string
    {
        return trim((string) config('marketing.cost_alert.sentry_monitor', ''));
    }
}
