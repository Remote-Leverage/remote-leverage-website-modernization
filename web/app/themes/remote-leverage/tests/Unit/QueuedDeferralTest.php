<?php

declare(strict_types=1);

use App\Infrastructure\Queue\CallHandlerJob;
use App\Infrastructure\Queue\Deferred;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Support\Facades\Facade;

/*
 * Moving the deferred integrations onto a real queue.
 *
 * The behaviour under test is mostly about *which* of two paths a call site takes, because the
 * wrong one is silently worse rather than broken. Dispatching a queued job while the connection
 * is `sync` runs it inline, in front of the response — so a conversion that ignores the
 * connection makes the booking wizard wait on Calendly on every environment that has not
 * switched the queue on yet. `Deferred` exists to make that impossible; these pin it.
 */

/** Records what the framework was asked to do, without needing a real bus. */
final class RecordingDispatcher implements DispatcherContract
{
    /** @var array<int, array{mode: string, job: mixed}> */
    public array $dispatched = [];

    public function dispatch($command)
    {
        $this->dispatched[] = ['mode' => 'queued', 'job' => $command];
    }

    public function dispatchAfterResponse($command, $handler = null)
    {
        $this->dispatched[] = ['mode' => 'after_response', 'job' => $command];
    }

    public function dispatchSync($command, $handler = null)
    {
        $this->dispatched[] = ['mode' => 'sync', 'job' => $command];
    }

    public function dispatchNow($command, $handler = null)
    {
        $this->dispatched[] = ['mode' => 'now', 'job' => $command];
    }

    public function hasCommandHandler($command)
    {
        return false;
    }

    public function getCommandHandler($command)
    {
        return false;
    }

    public function pipeThrough(array $pipes)
    {
        return $this;
    }

    public function map(array $map)
    {
        return $this;
    }

    public function chain($jobs = null)
    {
        return $this;
    }
}

function queueSpy(): RecordingDispatcher
{
    $app = Facade::getFacadeApplication();
    $spy = new RecordingDispatcher;
    $app->instance(DispatcherContract::class, $spy);

    return $spy;
}

describe('Deferred picks the path the environment can actually honour', function () {
    test('with no queue configured it defers in-process, exactly as the pre-queue code did', function () {
        config(['queue.default' => 'sync']);
        $spy = queueSpy();

        Deferred::call('Some\\Handler', 'method', ['a']);

        expect($spy->dispatched)->toHaveCount(1)
            ->and($spy->dispatched[0]['mode'])->toBe('after_response');

        // Same job object as the queued path takes, so there is only one thing to reason about.
        $job = $spy->dispatched[0]['job'];

        expect($job)->toBeInstanceOf(CallHandlerJob::class)
            ->and($job->handler)->toBe('Some\\Handler')
            ->and($job->method)->toBe('method')
            ->and($job->arguments)->toBe(['a']);
    });

    test('with a queue configured it queues a CallHandlerJob carrying the call', function () {
        config(['queue.default' => 'database']);
        $spy = queueSpy();

        Deferred::call('Some\\Handler', 'method', ['a', 2]);

        expect($spy->dispatched)->toHaveCount(1)
            ->and($spy->dispatched[0]['mode'])->toBe('queued');

        $job = $spy->dispatched[0]['job'];

        expect($job)->toBeInstanceOf(CallHandlerJob::class)
            ->and($job->handler)->toBe('Some\\Handler')
            ->and($job->method)->toBe('method')
            ->and($job->arguments)->toBe(['a', 2]);
    });
});

describe('CallHandlerJob', function () {
    test('resolves the handler from the container and calls it with the arguments', function () {
        $app = Facade::getFacadeApplication();

        $spy = new class
        {
            public array $calls = [];

            public function probe(string $role, bool $force = false): string
            {
                $this->calls[] = [$role, $force];

                return 'done';
            }
        };

        $app->instance('test.handler.spy', $spy);

        (new CallHandlerJob('test.handler.spy', 'probe', ['t10']))->handle();

        expect($spy->calls)->toBe([['t10', false]]);
    });

    test('does not retry, because the handlers it wraps are not all idempotent', function () {
        // A retry would re-post a Slack message or re-fire the outgoing lead webhook. Opting
        // into retries is a per-handler decision, never a default.
        expect((new CallHandlerJob('X', 'y'))->tries)->toBe(1);
    });

    test('names itself by handler and method, so failed_jobs stays readable', function () {
        $job = new CallHandlerJob('App\\Domains\\Lead\\Listeners\\HandleLeadEventsForSlack', 'handleCreated');

        expect($job->displayName())->toBe('HandleLeadEventsForSlack::handleCreated');
    });
});
