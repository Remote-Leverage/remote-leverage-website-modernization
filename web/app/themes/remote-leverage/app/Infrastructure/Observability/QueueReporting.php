<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Event;
use Sentry\CheckInStatus;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\MonitorScheduleUnit;
use Sentry\SentrySdk;
use Throwable;

/**
 * What Sentry knows about the queue worker (WR-106).
 *
 * ## Why this is not "report the exception to Sentry"
 *
 * It already is. `Worker::runJob()` hands a job's exception to the `ExceptionHandler`, and
 * `SentryReporting::captureUnhandledExceptions()` has a `reportable` callback on that handler —
 * so a failing job has been producing a Sentry issue since the DSN was set. Capturing it again
 * here would raise every queue failure twice.
 *
 * What was missing is everything *around* the exception:
 *
 *  - **Which job.** A queue failure and a web request failure arrive looking identical. Without
 *    the job class, connection and queue on the event there is no way to tell them apart in
 *    Sentry, let alone group them.
 *  - **The replay handle.** A failed job writes a row to `failed_jobs` keyed by uuid, and that
 *    uuid is what `queue:retry` takes. An issue that does not carry it leaves whoever reads it
 *    grepping a database table to act on it.
 *  - **Whether a worker is alive at all.** The failure mode that costs the most is not a job
 *    that throws; it is a worker that stopped, which produces no events by definition.
 *
 * The first two are set on the Sentry scope in `JobProcessing`, i.e. *before* the job runs, so
 * they are already attached when the handler reports. Setting them in `JobFailed` would be too
 * late — the event has been sent by then.
 *
 * ## Why the heartbeat is a check-in
 *
 * Same reasoning as `CronHeartbeat`, and the same 2026-09-21 outage behind it: a monitor written
 * as code has to run to complain, and the state being monitored is "nothing of ours is running".
 * Sentry Crons inverts that — the worker says "I started", and Sentry raises the alert when a
 * start stops arriving. `WorkerStarting` fires once per worker process, and the entrypoint
 * recycles the process hourly via `--max-time=3600`, so a healthy deployment checks in every
 * hour without anything extra having to be scheduled.
 *
 * Note what this does and does not catch. It catches the worker dying, the restart loop dying,
 * and the container running without one. It does not catch a worker that is alive but wedged on
 * a single job — `--timeout=60` is what bounds that, and a job killed by it throws, which is the
 * path above.
 *
 * ## Configuration
 *
 * `observability.queue.sentry_monitor` is the monitor slug and an empty value switches the
 * heartbeat off entirely. It is off by default, because the heartbeat must not fire in an
 * environment with no worker: `QUEUE_CONNECTION` is unset everywhere but local docker-compose,
 * and a monitor that alerts about the absence of something nobody asked for is a monitor people
 * learn to ignore.
 */
class QueueReporting
{
    /**
     * Guards against a second registration in the same process.
     */
    protected static bool $registered = false;

    public function register(): void
    {
        if (self::$registered || ! $this->sentryIsActive()) {
            return;
        }

        self::$registered = true;

        Event::listen(JobProcessing::class, fn (JobProcessing $event) => $this->describeJob($event));
        Event::listen(JobFailed::class, fn (JobFailed $event) => $this->flushFailure($event));
        Event::listen(WorkerStarting::class, fn (WorkerStarting $event) => $this->checkIn($event));
    }

    /**
     * Put the job on the scope before it runs, so an exception carries it.
     */
    protected function describeJob(JobProcessing $event): void
    {
        try {
            \Sentry\configureScope(function ($scope) use ($event): void {
                $scope->setTag('queue.connection', (string) $event->connectionName);
                $scope->setTag('queue.name', (string) $event->job->getQueue());
                $scope->setTag('queue.job', (string) $event->job->resolveName());

                $scope->setContext('queue', [
                    /*
                     * The `failed_jobs` primary key, and the argument `queue:retry` takes. This
                     * is the single most useful thing on the event: without it, acting on a
                     * failure means finding the row by hand.
                     */
                    'uuid' => $event->job->uuid(),
                    'attempts' => $event->job->attempts(),
                    'queue' => $event->job->getQueue(),
                    'connection' => $event->connectionName,
                ]);
            });
        } catch (Throwable) {
            // Describing the job must never be the reason the job does not run.
        }
    }

    /**
     * Push the event out of a long-running process.
     *
     * A web request flushes when it ends. A worker does not end — it sits in `--max-time=3600`
     * of polling, and a buffered event waits there with it, or dies with the process when the
     * hour is up. That delay is the difference between an alert and an autopsy.
     */
    protected function flushFailure(JobFailed $event): void
    {
        try {
            SentrySdk::getCurrentHub()->getClient()?->flush();
        } catch (Throwable) {
            // Same as above: observing the failure must not become a second failure.
        }
    }

    /**
     * Tell Sentry a worker process has started.
     */
    protected function checkIn(WorkerStarting $event): void
    {
        $slug = $this->slug();

        if ($slug === '' || ! function_exists('Sentry\captureCheckIn')) {
            return;
        }

        try {
            \Sentry\captureCheckIn(
                slug: $slug,
                status: CheckInStatus::ok(),
                monitorConfig: new MonitorConfig(
                    /*
                     * An interval rather than a crontab: worker starts are not aligned to the
                     * hour. The first one happens whenever the container boots, and every one
                     * after it lands an hour plus however long the last job took.
                     */
                    MonitorSchedule::interval(1, MonitorScheduleUnit::hour()),

                    /*
                     * Fifteen minutes. The recycle is `--max-time=3600`, which is the time the
                     * worker spends *looking* for jobs — a job already in hand when the hour
                     * expires runs to completion first, and `--timeout=60` bounds how long that
                     * can add. A rolling deploy can also delay a restart. None of that is worth
                     * paging about; an hour of silence is.
                     */
                    checkinMargin: 15,
                    maxRuntime: 5,
                ),
            );
        } catch (Throwable) {
            // The worker must start whether or not Sentry hears about it.
        }
    }

    protected function slug(): string
    {
        return trim((string) config('observability.queue.sentry_monitor', ''));
    }

    /**
     * Whether there is a Sentry client to report to at all.
     *
     * Without this the listeners would still be registered on an environment with no DSN, doing
     * scope work on every job for nobody.
     */
    protected function sentryIsActive(): bool
    {
        try {
            return SentrySdk::getCurrentHub()->getClient() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * For tests, which register against more than one container.
     */
    public static function flushRegistration(): void
    {
        self::$registered = false;
    }
}
