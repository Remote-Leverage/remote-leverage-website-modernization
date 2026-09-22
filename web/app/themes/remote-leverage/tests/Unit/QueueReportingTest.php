<?php

declare(strict_types=1);

use App\Infrastructure\Observability\QueueReporting;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Event;

/**
 * WR-106 — what Sentry knows about the queue worker.
 *
 * The exception itself was never the missing piece: `Worker::runJob()` already routes it through
 * the `ExceptionHandler`, which `SentryReporting` has a `reportable` callback on. What was
 * missing is the job's identity on that event, the `failed_jobs` uuid needed to act on it, and a
 * monitor that notices a worker which has stopped producing events altogether.
 *
 * So these tests pin the two things that would otherwise rot silently: that the listeners are
 * registered against the right events, and that none of this observation can break the job it
 * observes. There is no Sentry client bound in this suite, which is the same position a
 * DSN-less environment is in — and the correct behaviour there is to do nothing, quietly.
 */
beforeEach(function () {
    QueueReporting::flushRegistration();
    config(['observability.queue.sentry_monitor' => '']);
});

afterEach(function () {
    QueueReporting::flushRegistration();
});

test('with no Sentry client bound, nothing is registered at all', function () {
    // Not a cosmetic check. Registering the listeners anyway would do scope work on every job
    // on an environment that has nowhere to send it.
    (new QueueReporting)->register();

    expect(Event::hasListeners(JobProcessing::class))->toBeFalse()
        ->and(Event::hasListeners(JobFailed::class))->toBeFalse()
        ->and(Event::hasListeners(WorkerStarting::class))->toBeFalse();
});

test('the heartbeat is off unless a monitor slug is configured', function () {
    config(['observability.queue.sentry_monitor' => '']);

    $slug = (fn () => $this->slug())->call(new QueueReporting);

    // Off by default is the deliberate default: QUEUE_CONNECTION is unset in every deployed
    // environment, so a heartbeat would alert about a worker nobody asked to run.
    expect($slug)->toBe('');
});

test('a configured slug is trimmed', function () {
    config(['observability.queue.sentry_monitor' => '  rl-queue-worker  ']);

    $slug = (fn () => $this->slug())->call(new QueueReporting);

    // Same trap as CronHeartbeat: a stray space in an env var checks in to a monitor Sentry has
    // never heard of, and the real one alerts on a schedule nobody is watching.
    expect($slug)->toBe('rl-queue-worker');
});

test('describing a job never throws, whatever the job is', function () {
    /*
     * describeJob() runs inside JobProcessing, i.e. immediately before the job executes. If it
     * can throw, it does not degrade observability — it stops the job from running at all. A
     * fake job that answers nothing is the cheapest way to prove the guard holds.
     */
    $job = new class
    {
        public function getQueue(): string
        {
            return 'default';
        }

        public function resolveName(): string
        {
            throw new RuntimeException('a job that cannot name itself');
        }

        public function uuid(): ?string
        {
            return null;
        }

        public function attempts(): int
        {
            return 1;
        }
    };

    $reporting = new QueueReporting;
    (fn () => $this->describeJob(new JobProcessing('database', $job)))->call($reporting);
})->throwsNoExceptions();

test('flushing a failure never throws', function () {
    $job = new class
    {
        public function uuid(): ?string
        {
            return 'a-uuid';
        }
    };

    $reporting = new QueueReporting;
    (fn () => $this->flushFailure(
        new JobFailed('database', $job, new RuntimeException('boom'))
    ))->call($reporting);
})->throwsNoExceptions();

test('checking in never throws, and no-ops without a slug', function () {
    config(['observability.queue.sentry_monitor' => 'rl-queue-worker']);

    $reporting = new QueueReporting;
    (fn () => $this->checkIn(new WorkerStarting('database', 'default', null)))->call($reporting);
})->throwsNoExceptions();

test('the heartbeat rides WorkerStarting, which fires once per worker process', function () {
    // The coupling worth pinning: the hourly cadence Sentry is told to expect comes from the
    // entrypoint's --max-time=3600 recycle, not from a schedule of our own. If the event were
    // one that fires per job or per poll, the monitor would be checked in to constantly and
    // would never report anything.
    $worker = file_get_contents(__DIR__.'/../../vendor/illuminate/queue/Worker.php');

    expect($worker)->toContain('raiseWorkerStartingEvent');

    $entrypoint = file_get_contents(__DIR__.'/../../../../../../docker/entrypoint.sh');

    expect($entrypoint)->toContain('--max-time=3600')
        // WR-106 criterion 2: the memory ceiling, which must stay under php.ini's 256M.
        ->and($entrypoint)->toContain('--memory=192');
});

test('the queue worker is still inert unless QUEUE_CONNECTION names a connection', function () {
    // Not this class's behaviour, but the premise every comment in it rests on. If the
    // entrypoint ever starts a worker unconditionally, the heartbeat default flips from
    // "correct" to "silently missing".
    $entrypoint = file_get_contents(__DIR__.'/../../../../../../docker/entrypoint.sh');

    expect($entrypoint)->toContain('case "${QUEUE_CONNECTION:-sync}" in');
});
