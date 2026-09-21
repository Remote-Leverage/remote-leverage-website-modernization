<?php

declare(strict_types=1);

use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * Cancelling the meeting a rebooking supersedes.
 *
 * `POST /scheduled_events/{uuid}/cancellation` had never once worked, and the reason was not a
 * credential. `meeting_id` is stored in two shapes — the dedupe path logs a scheduled event uri
 * because `findExistingInvitee()` returns an event, and the ordinary booking path logs the
 * invitee uri `createInvitee()` answers with. `basename()` took the last segment, so almost every
 * cancellation was addressed with an *invitee* uuid, which is a real uuid that is not an event.
 *
 * What made it read as a permissions problem: Calendly answers 404, `sendWithFailover` treats 404
 * as "this token's account does not own this event" and rotates, and every token answers the same
 * way — so the log filled with "may belong to a different account" once per pooled token.
 */
beforeEach(function () {
    // See CalendlyPreflightTest: the Http factory outlives a single test and must be swapped.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);
});

/** @return array<int, string> Every cancellation URL the client asked for. */
function cancellationUrls(): array
{
    $urls = [];

    foreach (Http::recorded() as [$request]) {
        /** @var Request $request */
        if (str_contains($request->url(), '/cancellation')) {
            $urls[] = $request->url();
        }
    }

    return $urls;
}

function cancellingClient(string $suffix): CalendlyClient
{
    return new CalendlyClient(new CalendlyTokenPool([
        ['label' => 'Pool 1', 'token' => 'tok-cancel-'.$suffix, 'enabled' => true],
    ]));
}

test('an invitee uri is cancelled by its scheduled event, not by the invitee id', function () {
    Http::fake(['api.calendly.com/*' => Http::response([], 201)]);

    $cancelled = cancellingClient('invitee')->cancelScheduledEvent(
        'https://api.calendly.com/scheduled_events/e7037ad9-c137-41cf-a605-868193d3b29d'
        .'/invitees/28a07316-ebcb-41cc-8d3a-375d34642c7e'
    );

    expect($cancelled)->toBeTrue()
        ->and(cancellationUrls())->toBe([
            'https://api.calendly.com/scheduled_events/e7037ad9-c137-41cf-a605-868193d3b29d/cancellation',
        ]);
});

test('a scheduled event uri still cancels, since the dedupe path logs that shape', function () {
    Http::fake(['api.calendly.com/*' => Http::response([], 201)]);

    $cancelled = cancellingClient('event')->cancelScheduledEvent(
        'https://api.calendly.com/scheduled_events/b66ff256-8b57-498a-81a6-0028a8458480'
    );

    expect($cancelled)->toBeTrue()
        ->and(cancellationUrls())->toBe([
            'https://api.calendly.com/scheduled_events/b66ff256-8b57-498a-81a6-0028a8458480/cancellation',
        ]);
});

test('a bare uuid is passed through as given', function () {
    Http::fake(['api.calendly.com/*' => Http::response([], 201)]);

    cancellingClient('bare')->cancelScheduledEvent('330b0fc2-72ee-414c-b78a-8241a9b861e9');

    expect(cancellationUrls())->toBe([
        'https://api.calendly.com/scheduled_events/330b0fc2-72ee-414c-b78a-8241a9b861e9/cancellation',
    ]);
});

test('a reference naming no scheduled event is refused without calling Calendly', function () {
    Http::fake(['api.calendly.com/*' => Http::response([], 201)]);

    // The `gcal_…` ids the Google fallback used to mint are still in the log, and a rebooking
    // will read one. Asking Calendly to cancel a meeting it never had is not a request worth
    // making, and the 404 it would answer with reads as a credential fault.
    $cancelled = cancellingClient('gcal')->cancelScheduledEvent('https://meet.google.com/rl-consult');

    expect($cancelled)->toBeFalse()
        ->and(cancellationUrls())->toBe([]);
});
