<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Events;

use App\Domains\Lead\Models\Lead;

/**
 * Somebody asked to talk to a consultant right now.
 *
 * Both outcomes are dispatched, and the declined one is the reason this event exists. A routed
 * call already reaches Slack through `LeadBookingCompleted`; a declined one reached nothing at
 * all — `RouteInstantCallAction` returned a polite message to the browser, logged nothing
 * outside its own activity row, and the visitor went away. That is the highest-intent moment on
 * the site, and it was the only one nobody could see.
 *
 * One event with an outcome rather than two classes: every consumer so far wants both, and the
 * interesting question is always "what happened when someone asked", not "was it a success".
 */
class LiveCallRequested
{
    public const ROUTED = 'routed';

    public const DECLINED = 'declined';

    /**
     * @param  string  $outcome  self::ROUTED or self::DECLINED.
     * @param  string  $reason  Why, as a stable slug — see the constants on
     *                          `RouteInstantCallAction`. Empty when routed.
     * @param  array<string, mixed>  $visitor  Whatever the form collected: name, email, phone.
     */
    public function __construct(
        public string $outcome,
        public string $reason,
        public string $sessionId,
        public array $visitor = [],
        public ?Lead $lead = null,
        public ?string $meetUrl = null,
    ) {}

    public function wasDeclined(): bool
    {
        return $this->outcome === self::DECLINED;
    }
}
