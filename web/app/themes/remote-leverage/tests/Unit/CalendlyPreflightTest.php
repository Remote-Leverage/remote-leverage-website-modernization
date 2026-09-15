<?php

declare(strict_types=1);

use App\Domains\Scheduling\Gateways\CalendlyClient;
use App\Domains\Scheduling\Gateways\CalendlyTokenPool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * Coverage for the rewritten duplicate-booking preflight
 * (CalendlyClient::findExistingInvitee), which used to cost 8 sequential
 * round-trips — 2 per pooled token — inside the user's booking submit.
 *
 * The three behaviours these tests exist to protect:
 *   1. The 429 failover the token pool exists for still works.
 *   2. A cached `users/me` identity is not re-fetched.
 *   3. The common "no prior booking" case short-circuits after one account.
 */

/** Unique per test so the process-wide stub cache cannot leak identity between tests. */
function preflightTokens(string $suffix, int $count = 4): CalendlyTokenPool
{
    $rows = [];

    for ($i = 1; $i <= $count; $i++) {
        $rows[] = ['label' => "Pool {$i}", 'token' => "tok{$i}-{$suffix}", 'enabled' => true];
    }

    return new CalendlyTokenPool($rows);
}

function usersMeResponse(string $suffix): array
{
    return ['resource' => [
        'uri' => "https://api.calendly.com/users/user-{$suffix}",
        'current_organization' => "https://api.calendly.com/organizations/org-{$suffix}",
    ]];
}

function scheduledEvent(string $eventTypeUri, string $startTime): array
{
    return [
        'uri' => 'https://api.calendly.com/scheduled_events/evt-1',
        'event_type' => $eventTypeUri,
        'start_time' => $startTime,
        'status' => 'active',
    ];
}

beforeEach(function () {
    // The Http facade caches its Factory for the life of the process, and
    // clearing only the facade's cache is not enough — fake() binds the factory
    // into the container too. Without a hard swap, every test would inherit the
    // previous test's stub closures and request log.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);
});

it('short-circuits on the first account that answers when there is no prior booking', function () {
    $suffix = 'short';

    Http::fake(function (Request $request) use ($suffix) {
        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($suffix), 200);
        }

        return Http::response(['collection' => []], 200);
    });

    $client = new CalendlyClient(preflightTokens($suffix));

    $result = $client->findExistingInvitee(
        'nobody@example.com',
        'https://api.calendly.com/event_types/abc',
        '2026-10-01T15:00:00Z'
    );

    expect($result)->toBeNull();

    // Cold identity cache: one users/me + one scheduled_events. NOT 8 — the other
    // three pooled accounts are never touched once one of them answers cleanly.
    Http::assertSentCount(2);
});

it('does not re-fetch users/me once the token identity is cached', function () {
    $suffix = 'cached';

    Http::fake(function (Request $request) use ($suffix) {
        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($suffix), 200);
        }

        return Http::response(['collection' => []], 200);
    });

    $client = new CalendlyClient(preflightTokens($suffix));

    $client->findExistingInvitee('a@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z');
    Http::assertSentCount(2);

    // Second submission: identity comes from cache, so the preflight is a single
    // round-trip. This is the difference between ~4s and ~0.5s in production.
    $client->findExistingInvitee('b@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z');
    Http::assertSentCount(3);

    // A fresh client instance shares the cache — the win survives across requests.
    (new CalendlyClient(preflightTokens($suffix)))
        ->findExistingInvitee('c@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z');
    Http::assertSentCount(4);

    expect(Http::recorded(fn (Request $r) => str_contains($r->url(), '/users/me')))->toHaveCount(1);
});

it('caches the token identity under a key derived from the token itself', function () {
    $suffix = 'identity';

    Http::fake(['*' => Http::response(usersMeResponse($suffix), 200)]);

    $client = new CalendlyClient(preflightTokens($suffix, 1));

    $identity = $client->userIdentity("tok1-{$suffix}");

    expect($identity)->toBe([
        'organization' => "https://api.calendly.com/organizations/org-{$suffix}",
        'user' => "https://api.calendly.com/users/user-{$suffix}",
    ]);

    expect($client->userIdentity("tok1-{$suffix}"))->toBe($identity);
    Http::assertSentCount(1);

    $client->forgetUserIdentity("tok1-{$suffix}");
    $client->userIdentity("tok1-{$suffix}");
    Http::assertSentCount(2);
});

it('fails over to the next pooled token on a 429 and marks the first rate-limited', function () {
    $suffix = 'ratelimit';
    $eventType = 'https://api.calendly.com/event_types/abc';
    $start = '2026-10-01T15:00:00Z';

    Http::fake(function (Request $request) use ($suffix, $eventType, $start) {
        $token = str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '');

        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($token), 200);
        }

        if ($token === "tok1-{$suffix}") {
            return Http::response(['message' => 'Too many requests'], 429);
        }

        return Http::response(['collection' => [scheduledEvent($eventType, $start)]], 200);
    });

    $pool = preflightTokens($suffix);
    $client = new CalendlyClient($pool);

    $result = $client->findExistingInvitee('dupe@example.com', $eventType, $start);

    // The pool's whole reason for existing: a rate-limited primary must not lose
    // the answer, it must hand off to the next account.
    expect($result)->not->toBeNull()
        ->and($result['uri'])->toBe('https://api.calendly.com/scheduled_events/evt-1');

    expect($pool->isRateLimited("tok1-{$suffix}"))->toBeTrue()
        ->and($pool->isRateLimited("tok2-{$suffix}"))->toBeFalse();

    // Token 1: users/me + 429. Token 2: users/me + success. Tokens 3 and 4 untouched.
    Http::assertSentCount(4);

    // And a rate-limited token drops straight out of the eligible set next time.
    Cache::forget('none');
    expect(array_column($pool->getEligibleTokens(null), 'token'))
        ->not->toContain("tok1-{$suffix}");
});

it('rotates on 401/403, records a booking failure and drops the stale identity', function () {
    $suffix = 'unauthorized';
    $eventType = 'https://api.calendly.com/event_types/abc';
    $start = '2026-10-01T15:00:00Z';

    Http::fake(function (Request $request) use ($suffix, $eventType, $start) {
        $token = str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '');

        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($token), 200);
        }

        // Token 1 is scoped out of BOTH organization and user reads.
        if ($token === "tok1-{$suffix}") {
            return Http::response(['message' => 'Forbidden'], 403);
        }

        return Http::response(['collection' => [scheduledEvent($eventType, $start)]], 200);
    });

    $pool = preflightTokens($suffix);
    $client = new CalendlyClient($pool);

    $result = $client->findExistingInvitee('dupe@example.com', $eventType, $start);

    expect($result)->not->toBeNull();
    expect($pool->failureCount("tok1-{$suffix}", 'booking'))->toBe(1);

    // Token 1's identity is evicted, since a 403 is what a re-scoped token looks like.
    expect(Cache::get('rl_calendly_user_identity_'.substr(hash('sha256', "tok1-{$suffix}"), 0, 16)))->toBeNull();
});

it('returns the matching scheduled event when the lead really did already book', function () {
    $suffix = 'hit';
    $eventType = 'https://api.calendly.com/event_types/abc';

    Http::fake(function (Request $request) use ($suffix, $eventType) {
        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($suffix), 200);
        }

        return Http::response(['collection' => [
            // Right slot, wrong event type.
            scheduledEvent('https://api.calendly.com/event_types/other', '2026-10-01T15:00:00.000000Z'),
            // Right event type, wrong slot.
            scheduledEvent($eventType, '2026-10-01T16:00:00.000000Z'),
            // The duplicate — note the differing ISO-8601 spelling of the same instant.
            scheduledEvent($eventType, '2026-10-01T15:00:00.000000Z'),
        ]], 200);
    });

    $client = new CalendlyClient(preflightTokens($suffix));

    $result = $client->findExistingInvitee('dupe@example.com', $eventType, '2026-10-01T15:00:00Z');

    expect($result)->not->toBeNull()
        ->and($result['event_type'])->toBe($eventType)
        ->and($result['start_time'])->toBe('2026-10-01T15:00:00.000000Z');
});

it('narrows the scheduled_events query to a window around the requested slot', function () {
    $suffix = 'window';

    Http::fake(function (Request $request) use ($suffix) {
        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($suffix), 200);
        }

        return Http::response(['collection' => []], 200);
    });

    (new CalendlyClient(preflightTokens($suffix)))
        ->findExistingInvitee('a@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z');

    // recorded() preserves the original keys, so take first() rather than [0].
    $query = Http::recorded(fn (Request $r) => str_contains($r->url(), '/scheduled_events'))->first()[0]->url();

    expect($query)
        ->toContain('min_start_time=2026-10-01T14%3A59%3A00Z')
        ->toContain('max_start_time=2026-10-01T15%3A01%3A00Z')
        ->toContain('status=active')
        ->toContain('invitee_email=a%40example.com');
});

it('treats an unreachable Calendly as no prior booking without walking the pool', function () {
    $suffix = 'down';
    $attempts = 0;

    // A thrown ConnectionException is never recorded by the fake, so count the
    // attempts directly rather than through assertSentCount().
    Http::fake(function (Request $request) use ($suffix, &$attempts) {
        $attempts++;

        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($suffix), 200);
        }

        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    $client = new CalendlyClient(preflightTokens($suffix));

    $result = $client->findExistingInvitee('a@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z');

    // Fail open: a Calendly outage must not block the booking submit, and it must
    // not pay the timeout four more times on the way out — one users/me (cached
    // from here on) plus one failed lookup, then stop.
    expect($result)->toBeNull()
        ->and($attempts)->toBe(2);
});

it('stops on a 5xx rather than rotating through every remaining account', function () {
    $suffix = 'servererror';

    Http::fake(function (Request $request) use ($suffix) {
        if (str_contains($request->url(), '/users/me')) {
            return Http::response(usersMeResponse($suffix), 200);
        }

        return Http::response(['message' => 'Internal Server Error'], 503);
    });

    $client = new CalendlyClient(preflightTokens($suffix));

    expect($client->findExistingInvitee('a@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z'))
        ->toBeNull();

    Http::assertSentCount(2);
});

it('returns null without any network call when the pool is empty', function () {
    Http::fake();

    $client = new CalendlyClient(new CalendlyTokenPool([]));

    expect($client->findExistingInvitee('a@example.com', 'https://api.calendly.com/event_types/abc', '2026-10-01T15:00:00Z'))
        ->toBeNull();

    Http::assertNothingSent();
});
