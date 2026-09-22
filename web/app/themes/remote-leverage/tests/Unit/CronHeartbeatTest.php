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
    expect((new CronHeartbeat)->start())->toBeNull();
});

test('finishing without a check-in id is a no-op', function () {
    config(['marketing.cost_alert.sentry_monitor' => 'marketing-cost-alert']);

    // start() returns null whenever it could not check in. finish() must cope with that rather
    // than assume a happy path, or a monitor outage becomes a tick outage.
    (new CronHeartbeat)->finish(null, true, 1.0);
})->throwsNoExceptions();

test('observing the tick never breaks the tick', function () {
    config(['marketing.cost_alert.sentry_monitor' => 'marketing-cost-alert']);

    /*
     * The whole point of the guard clauses. This suite has no Sentry client bound, so the SDK
     * call either no-ops or throws depending on the environment — and in neither case may the
     * heartbeat propagate anything to the cron callback wrapping it.
     */
    $heartbeat = new CronHeartbeat;
    $heartbeat->finish($heartbeat->start(), false, 0.5);
})->throwsNoExceptions();

test('the configured slug is what gets used', function () {
    config(['marketing.cost_alert.sentry_monitor' => '  spaced-slug  ']);

    $slug = (fn () => $this->slug())->call(new CronHeartbeat);

    // Trimmed, because an env var with a stray space would otherwise check in to a monitor
    // Sentry has never heard of and alert on a schedule nobody is watching.
    expect($slug)->toBe('spaced-slug');
});
