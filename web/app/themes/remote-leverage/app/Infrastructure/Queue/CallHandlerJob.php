<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Calls one method on one container-resolved handler, on the queue.
 *
 * ## One job, not one per call site
 *
 * Every deferred integration in this theme is dispatched the same way:
 *
 *     dispatch(static fn () => app(Handler::class)->method($event))->afterResponse();
 *
 * Twelve of those exist, across the lead, tracking, referral, scheduling and payment providers.
 * Twelve near-identical job classes would be twelve places for the pattern to drift, so this is
 * the one job all of them become — the handler class, the method, and the arguments.
 *
 * ## Arguments must be scalars, not models
 *
 * `$arguments` is serialised into the payload and rebuilt in a different process, potentially
 * minutes later. Passing an Eloquent model, or an event object carrying one, freezes that row's
 * attributes at dispatch time: `SerializesModels` only re-fetches models that are the job's own
 * properties, so a model *nested inside* an event is stored whole and comes back stale.
 *
 * So call sites pass identifiers and scalars, and the handler loads what it needs. That keeps
 * the payload small and the data fresh, and it is why the migration from `afterResponse()` is
 * per-site work rather than a search and replace.
 *
 * ## Why it does not retry by default
 *
 * `$tries = 1`. Today nothing retries — `afterResponse()` runs a closure once and drops it — so
 * retrying by default would change behaviour in the one direction that is hard to undo: a Slack
 * post that succeeded and then timed out reading the response would be posted twice, and the
 * outgoing lead webhook would fire twice into someone else's system.
 *
 * One attempt is therefore the same delivery guarantee as today, with the failure written to
 * `failed_jobs` instead of vanishing — which is the whole point of the change. Retries are
 * opted into per call site, once that handler is known to be idempotent.
 */
class CallHandlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @see the class docblock — deliberately not a retry, because the handlers are not all idempotent. */
    public int $tries = 1;

    /**
     * @param  class-string  $handler  Resolved through the container, so constructor injection still applies.
     * @param  array<int, mixed>  $arguments  Scalars and identifiers only. See the class docblock.
     */
    public function __construct(
        public readonly string $handler,
        public readonly string $method,
        public readonly array $arguments = [],
    ) {}

    public function handle(): void
    {
        app($this->handler)->{$this->method}(...$this->arguments);
    }

    /**
     * What this job is called in `failed_jobs`, `queue:failed` and the worker's log line.
     *
     * Without this every row reads `CallHandlerJob`, which would make the failed-jobs table
     * useless the moment more than one call site is converted.
     */
    public function displayName(): string
    {
        return sprintf('%s::%s', class_basename($this->handler), $this->method);
    }

    public function failed(\Throwable $e): void
    {
        /*
         * `failed_jobs` already has the payload and the stack trace. This is for CloudWatch,
         * where the alarm can actually be attached — a row in a table nobody queries is only
         * marginally better than the silence this replaces.
         */
        Log::error('CallHandlerJob: '.$this->displayName().' failed', [
            'handler' => $this->handler,
            'method' => $this->method,
            'error' => $e->getMessage(),
        ]);
    }
}
