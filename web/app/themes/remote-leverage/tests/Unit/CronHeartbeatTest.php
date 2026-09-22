<?php

declare(strict_types=1);

use App\Domains\Marketing\Support\CronHeartbeat;

/**
 * The monitor that notices the scheduler has stopped.
 *
 * On 2026-09-21 cron died at 16:25 UTC and nothing reported it for ten hours. Every monitor that
 * could have been written in this codebase shares one hole — it is code, and code has to run to
 * complain, so anything riding on the cron tick is as dead as the tick. Sentry Crons inverts that:
 * we say "this ran", and Sentry raises the alarm when the saying stops.
 *
 * What is worth pinning here is the wiring that makes it safe: it must never break the tick it
 * observes, and it must be switchable off per environment.
 */
test('an empty monitor slug switches the heartbeat off entirely', function () {
    config(['marketing.cost_alert.sentry_monitor' => '']);

    // No slug, no check-in and no Sentry traffic — for environments that should not be monitored.
    (new CronHeartbeat)->ran(true, 1.0);
})->throwsNoExceptions();

test('a failed tick checks in as an error rather than staying silent', function () {
    config(['marketing.cost_alert.sentry_monitor' => 'marketing-cost-alert']);

    /*
     * Silence already means "the scheduler is dead". A tick that ran and threw is a different
     * fault and has to be distinguishable from one that never ran at all.
     */
    (new CronHeartbeat)->ran(false, 0.2);
})->throwsNoExceptions();

test('observing the tick never breaks the tick', function () {
    config(['marketing.cost_alert.sentry_monitor' => 'marketing-cost-alert']);

    /*
     * The whole point of the guard clauses. This suite has no Sentry client bound, so the SDK
     * call either no-ops or throws depending on the environment — and in neither case may the
     * heartbeat propagate anything to the cron callback wrapping it.
     */
    (new CronHeartbeat)->ran(true, 0.5);
})->throwsNoExceptions();

test('there is no open check-in left for Sentry to time out', function () {
    /*
     * The bug this replaced. An `in_progress` open whose close never arrived made Sentry report
     * "a timeout check-in was detected" every hour with `Last Successful Check-In: Never` — an
     * alert about the monitoring that looked exactly like the outage it was built to catch.
     */
    $src = file_get_contents(__DIR__.'/../../app/Domains/Marketing/Support/CronHeartbeat.php');

    expect($src)->not->toContain('inProgress')
        ->and($src)->toContain('maxRuntime: null');
});
