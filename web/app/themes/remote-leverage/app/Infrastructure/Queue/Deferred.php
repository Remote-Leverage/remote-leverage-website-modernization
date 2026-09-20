<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;

/**
 * Run a handler method away from the response, on the queue when there is one.
 *
 * ## The trap this exists to avoid
 *
 * Converting a call site to a `ShouldQueue` job looks like it can be done on its own. It cannot,
 * because of what `sync` means. `QUEUE_CONNECTION` is unset in every environment today, so the
 * connection is `sync`, and dispatching a queued job on `sync` runs it **inline, during the
 * request** — before the response, not after it.
 *
 * That is strictly worse than the `dispatch(...)->afterResponse()` it would replace. The booking
 * wizard's utilisation probe pages Calendly's `scheduled_events`; its own comment calls the
 * booking widget "the last request on the site that should wait on one". A straight conversion
 * would put that call back in front of the visitor everywhere the queue is not yet switched on.
 *
 * Nor does `->afterResponse()` rescue it: `Dispatcher::dispatchAfterResponse()` registers a
 * terminating callback that runs the job through `dispatchNow()`. It never reaches a queue,
 * however the connection is configured, so a job dispatched that way would never be queued at
 * all.
 *
 * ## So the call site asks for the outcome, not the mechanism
 *
 * With no queue configured this defers exactly as the code does today. With one configured it
 * queues. That is what makes the rollout a per-environment switch — staging on `database` while
 * production stays `sync` — rather than a deploy that flips every call site at once.
 *
 * Arguments must be scalars and identifiers, never models or events carrying them. See
 * {@see CallHandlerJob} for why.
 */
final class Deferred
{
    /**
     * @param  class-string  $handler
     * @param  array<int, mixed>  $arguments
     */
    public static function call(string $handler, string $method, array $arguments = []): void
    {
        $job = new CallHandlerJob($handler, $method, $arguments);

        if (self::queueConfigured()) {
            self::bus()->dispatch($job);

            return;
        }

        /*
         * The pre-queue path: a terminating callback in this process, which `dispatchAfterResponse`
         * runs through `dispatchNow()` — so the job's `handle()` is called inline after the
         * response, ignoring `ShouldQueue`. Behaviourally identical to the
         * `dispatch(closure)->afterResponse()` this replaces, and it only runs at all because
         * `ThemeServiceProvider::ensureApplicationTerminates()` forces Acorn to terminate on
         * WordPress-rendered requests.
         *
         * The same job object on both paths, rather than a closure here and a job there. One
         * class to reason about, one place a converted call site can go wrong, and no reliance
         * on serialisable closures for work that already has a perfectly good named shape.
         */
        self::bus()->dispatchAfterResponse($job);
    }

    /**
     * The command bus, resolved through the container rather than the `dispatch()` helper.
     *
     * The helper is a global function Acorn loads at runtime; the test harness is a standalone
     * container that does not, so reaching for the contract keeps this path exercisable by the
     * suite instead of only in a browser.
     */
    private static function bus(): DispatcherContract
    {
        return app(DispatcherContract::class);
    }

    /** Is there somewhere for a job to go other than straight back into this request? */
    private static function queueConfigured(): bool
    {
        return config('queue.default', 'sync') !== 'sync';
    }
}
