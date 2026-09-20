<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use App\Infrastructure\Cache\SoftCache;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendlyClient
{
    /**
     * Cache key prefix for the per-token `users/me` identity (org URI + user URI).
     * That response is effectively static for the life of a token, so re-fetching
     * it on every booking submit was pure latency — see findExistingInvitee().
     */
    /*
     * Versioned. Entries cached before the account email was captured satisfy the
     * "has organization or user" guard below, so without a new prefix they would be
     * returned for the next twelve hours with no email and the admin would show
     * blanks for tokens that are working fine.
     */
    protected const USER_IDENTITY_PREFIX = 'rl_calendly_user_identity_v2_';

    protected const USER_IDENTITY_TTL_SECONDS = 43200; // 12h

    /** Per-request budget for the duplicate-booking preflight. */
    protected const PREFLIGHT_TIMEOUT_SECONDS = 5;

    protected const PREFLIGHT_CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * Whole-preflight budget. Bounds the worst case (every pooled token rotating
     * on 429/403) so a degraded Calendly cannot hold the booking submit open for
     * tokens x timeout seconds.
     */
    protected const PREFLIGHT_BUDGET_SECONDS = 8.0;

    /**
     * The same three budgets, for the background utilization count.
     *
     * countBookedEvents() used the PREFLIGHT_* values until 2026-09-20, which meant a cron job
     * inherited a budget tuned for a visitor sitting on a booking submit: five seconds a request
     * and eight for the whole pool, so one unresponsive Calendly left room for exactly one more
     * token before the count gave up. Nothing is waiting on this one — a timed-out count is not a
     * slow page, it is a missing figure on the next card — so it can afford to be patient.
     *
     * The budget is deliberately only a little over one timeout rather than several. It is sized
     * to buy exactly one clean failover after an unresponsive token, which is the failure that
     * actually happens; sizing it for four would multiply by the six counts a single cost-alert
     * snapshot makes, and turn a Calendly outage into a cron tick measured in minutes.
     */
    public const UTILIZATION_TIMEOUT_SECONDS = 15;

    public const UTILIZATION_CONNECT_TIMEOUT_SECONDS = 4;

    public const UTILIZATION_BUDGET_SECONDS = 20.0;

    /**
     * How many pages of `/scheduled_events` a utilization count will walk before giving up.
     *
     * A four-day window on this account is one page. The cap exists so a mis-set window cannot
     * turn a background health check into a walk of the account's entire booking history.
     */
    protected const SCHEDULED_EVENTS_PAGE_CAP = 5;

    protected CalendlyTokenPool $tokenPool;

    protected ?string $userUri;

    /**
     * Calendly's own error code from the last createInvitee() refusal — `already_filled`,
     * `invalid_argument` and friends — or null when the last call succeeded, or failed for a
     * reason Calendly never named (a timeout, an exhausted pool).
     *
     * Read it immediately after createInvitee() and nowhere else. It is per-call state on a
     * container singleton, and "immediately after" is the whole contract.
     *
     * It exists for one distinction the caller cannot otherwise make: a booking Calendly
     * *refused* is not a booking Calendly *missed*. `already_filled` means somebody took the
     * slot between the picker rendering and this submit, and no amount of retrying will get it
     * back — the only honest answer is to ask for another time.
     */
    protected ?string $lastInviteeErrorCode = null;

    public function __construct(?CalendlyTokenPool $tokenPool = null)
    {
        $this->tokenPool = $tokenPool ?: new CalendlyTokenPool;
        $this->userUri = config('services.calendly.user_uri');
    }

    /**
     * Send a Calendly request, iterating the token pool's eligible tokens in order.
     * $context is passed through to CalendlyTokenPool::getEligibleTokens() — pass
     * 'metadata' for circuit-breaker-gated calls (only getEventQuestions() does this
     * today), leave null for the real booking/availability paths, matching legacy.
     *
     * Per-status handling (verified against the legacy plugin):
     * - 429: mark rate-limited, rotate to the next token.
     * - 401/403: record a failure for this token/context, rotate.
     * - 404: rotate WITHOUT recording a failure (legacy: "this token's account
     *   doesn't own this event," not a broken token).
     * - connection exception, or 5xx: HALT immediately (legacy schedules a
     *   background retry here instead of rotating through the rest of the pool).
     * - anything else (2xx, or an actionable 4xx like 400/422): return immediately.
     *
     * Return contract for callers: null or a 5xx response means a transient outage
     * (retry later); a response with status in {401,403,404,429} means the pool was
     * exhausted (not a "Calendly is down" signal); anything else is a real answer.
     */
    protected function sendWithFailover(callable $makeRequest, ?string $context = null): ?Response
    {
        $tokens = $this->tokenPool->getEligibleTokens($context);
        if (empty($tokens)) {
            return null;
        }

        $lastResponse = null;

        foreach ($tokens as $item) {
            $token = $item['token'];

            /*
             * Name the account, not the slot. "token [Pool 2] rate-limited" starts a hunt
             * through the pool to find out whose it is; naming the email ends it.
             */
            $who = $this->describeToken($item);

            try {
                $response = $makeRequest($token);
            } catch (\Throwable $e) {
                Log::error("CalendlyClient: connection error on token [{$who}]: {$e->getMessage()}");

                return null;
            }

            $status = $response->status();

            if ($status === 429) {
                Log::warning("CalendlyClient: token [{$who}] rate-limited, failing over to next token in pool");
                $this->tokenPool->markRateLimited($token);
                $lastResponse = $response;

                continue;
            }

            if ($status === 401 || $status === 403) {
                Log::warning("CalendlyClient: token [{$who}] returned {$status}, recording failure and failing over");
                $this->tokenPool->recordFailure($token, $context ?? 'booking');
                $lastResponse = $response;

                continue;
            }

            if ($status === 404) {
                Log::warning("CalendlyClient: token [{$who}] returned 404, may belong to a different account, failing over");
                $lastResponse = $response;

                continue;
            }

            if ($status >= 500) {
                Log::error("CalendlyClient: token [{$who}] returned server error {$status}, halting pool iteration");

                return $response;
            }

            return $response;
        }

        return $lastResponse;
    }

    /**
     * Send a request using one specific token, with no failover/rotation. Used by
     * flows that must address every pooled token explicitly (e.g. event-type
     * discovery across accounts) rather than trying one and failing over.
     */
    public function getForToken(
        string $token,
        string $url,
        array $query = [],
        ?int $timeoutSeconds = null,
        ?int $connectTimeoutSeconds = null
    ): ?Response {
        try {
            // Guzzle/Laravel's HTTP client treats an explicit `query` option — even
            // an empty array — as a full replacement of the URL's existing query
            // string. Calendly's `pagination.next_page` URLs already carry their
            // own query string (organization/user/page_token), so passing []
            // here would silently strip it and break every page after the first.
            // Only pass $query through when there's something to add.
            $request = Http::withToken($token)->timeout($timeoutSeconds ?? 15);

            if ($connectTimeoutSeconds !== null) {
                $request = $request->connectTimeout($connectTimeoutSeconds);
            }

            return empty($query)
                ? $request->get($url)
                : $request->get($url, $query);
        } catch (\Throwable $e) {
            Log::error("CalendlyClient::getForToken exception: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Why Calendly refused the last createInvitee() call, in Calendly's own vocabulary.
     *
     * {@see self::$lastInviteeErrorCode} for the read-it-immediately contract. Null means the
     * booking was not refused — it either worked, or never got an answer at all, and those two
     * are told apart by createInvitee()'s return value.
     */
    public function lastInviteeErrorCode(): ?string
    {
        return $this->lastInviteeErrorCode;
    }

    /**
     * Fetch event type availability slots from Calendly.
     * Ported from CalendlyIntegration::get_availability().
     */
    public function getAvailableSlots(string $eventTypeId, string $startTime, string $endTime, ?string $timezone = null): array
    {
        $params = [
            'event_type' => $eventTypeId,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        if ($timezone) {
            $params['timezone'] = $timezone;
        }

        try {
            $response = $this->sendWithFailover(
                fn (string $token) => Http::withToken($token)
                    ->timeout(15)
                    ->get('https://api.calendly.com/event_type_available_times', $params)
            );

            if (! $response) {
                Log::warning('CalendlyClient: No eligible tokens in pool (none configured, or all rate-limited/circuit-broken)');

                return [];
            }

            if ($response->failed()) {
                Log::error('CalendlyClient: Error fetching slots', $response->json() ?? ['body' => $response->body()]);

                return [];
            }

            return $response->json('collection', []);
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Exception: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch event type details from Calendly.
     */
    public function getEventType(string $eventUriOrUuid, ?string $context = null): ?array
    {
        $url = str_starts_with($eventUriOrUuid, 'http')
            ? $eventUriOrUuid
            : 'https://api.calendly.com/event_types/'.rawurlencode($eventUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url), $context);

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Event Type Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch custom questions for an event type. This is the one legacy call site that
     * used the 'metadata' circuit-breaker context (get_booking_questions), so it's
     * hardcoded here rather than exposed as a caller-supplied parameter.
     */
    public function getEventQuestions(string $eventUriOrUuid): array
    {
        $eventType = $this->getEventType($eventUriOrUuid, 'metadata');

        return $eventType['custom_questions'] ?? [];
    }

    /**
     * Book appointment by creating invitee on Calendly.
     * Ported from CalendlyIntegration::process_calendly_booking() / api.calendly.com/invitees.
     */
    public function createInvitee(
        string $eventUri,
        string $email,
        string $name,
        ?string $startTime = null,
        ?string $timezone = 'America/New_York',
        ?string $phone = null,
        array $guestEmails = [],
        array $questionsAnswers = [],
        array $tracking = []
    ): ?array {
        $this->lastInviteeErrorCode = null;

        try {
            $payload = [
                'event_type' => $eventUri,
                'invitee' => [
                    'name' => $name,
                    'email' => $email,
                    'timezone' => $timezone ?? 'America/New_York',
                ],
            ];

            if ($startTime) {
                $payload['start_time'] = $startTime;
            }

            if ($phone) {
                $payload['invitee']['text_reminder_number'] = $phone;
            }

            if (! empty($guestEmails)) {
                $payload['event_guests'] = array_values(array_filter($guestEmails, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
            }

            if (! empty($questionsAnswers)) {
                $payload['questions_and_answers'] = array_values($questionsAnswers);
            }

            if (! empty($tracking)) {
                // Calendly strictly requires: utm_campaign, utm_source, utm_medium, utm_content, utm_term, salesforce_uuid
                $payload['tracking'] = [
                    'utm_campaign' => ! empty($tracking['utm_campaign']) ? (string) $tracking['utm_campaign'] : null,
                    'utm_source' => ! empty($tracking['utm_source']) ? (string) $tracking['utm_source'] : 'remoteleverage_site',
                    'utm_medium' => ! empty($tracking['utm_medium']) ? (string) $tracking['utm_medium'] : null,
                    'utm_content' => ! empty($tracking['utm_content']) ? (string) $tracking['utm_content'] : null,
                    'utm_term' => ! empty($tracking['utm_term']) ? (string) $tracking['utm_term'] : null,
                    'salesforce_uuid' => ! empty($tracking['salesforce_uuid']) ? (string) $tracking['salesforce_uuid'] : null,
                ];
            }

            $response = $this->sendWithFailover(
                fn (string $token) => Http::withToken($token)
                    ->timeout(20)
                    ->post('https://api.calendly.com/invitees', $payload)
            );

            if (! $response) {
                Log::warning('CalendlyClient: No eligible tokens in pool (none configured, or all rate-limited/circuit-broken)');

                return null;
            }

            if ($response->failed()) {
                $code = $response->json('details.0.code');
                $this->lastInviteeErrorCode = is_string($code) && $code !== '' ? $code : 'unspecified';

                Log::error('CalendlyClient Invitee Creation Failed', [
                    'status' => $response->status(),
                    'code' => $this->lastInviteeErrorCode,
                    'response' => $response->json(),
                ]);

                return null;
            }

            return $response->json('resource');
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Booking Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * How many active meetings are on the books for one event type inside a time window.
     *
     * This is the denominator the availability API will not give you. `event_type_available_times`
     * only ever returns what is still open, so "four slots left" is a number with no scale — four
     * out of forty is a tier filling up, four out of five is a tier nobody wants. Counting the
     * booked side is the only way to tell those apart from outside Calendly, and it is what
     * AvailabilityHealthMonitor's 90%/95% thresholds are measured against.
     *
     * `/scheduled_events` has no `event_type` filter, so this pulls the window for the whole
     * account and matches on the `event_type` URI in PHP.
     *
     * Returns **null rather than 0** when it could not find out. Those mean opposite things: 0 is
     * an empty calendar, which is the healthiest reading there is, so handing it back for a failed
     * lookup would report a tier that is about to sell out as wide open. Callers must treat null
     * as "no opinion" and skip the check.
     *
     * The timeouts default to UTILIZATION_* because both callers today run after the response or
     * on cron — the hourly cost-alert warm and the deferred tier probe. A caller that ever runs
     * *in front of* a visitor must pass the PREFLIGHT_* values explicitly; the defaults here will
     * happily hold a request open for the better part of a minute, which is right for a cron tick
     * and wrong for anything else.
     */
    public function countBookedEvents(
        string $eventTypeUri,
        string $minStartIso,
        string $maxStartIso,
        ?int $timeoutSeconds = null,
        ?int $connectTimeoutSeconds = null,
        ?float $budgetSeconds = null
    ): ?int {
        $timeoutSeconds ??= self::UTILIZATION_TIMEOUT_SECONDS;
        $connectTimeoutSeconds ??= self::UTILIZATION_CONNECT_TIMEOUT_SECONDS;
        $deadline = microtime(true) + ($budgetSeconds ?? self::UTILIZATION_BUDGET_SECONDS);

        foreach ($this->tokenPool->getEligibleTokens(null) as $item) {
            if (microtime(true) >= $deadline) {
                Log::warning('CalendlyClient: utilization count exceeded its time budget, reporting no opinion');

                return null;
            }

            $token = (string) $item['token'];
            $who = $this->describeToken($item);

            $identity = $this->userIdentity($token);

            if ($identity === null) {
                Log::warning("CalendlyClient: utilization count could not resolve identity for token [{$who}], failing over");

                continue;
            }

            $booked = $this->walkScheduledEvents(
                $token,
                $identity,
                $eventTypeUri,
                $minStartIso,
                $maxStartIso,
                $who,
                $timeoutSeconds,
                $connectTimeoutSeconds,
                $deadline
            );

            if ($booked !== null) {
                return $booked;
            }
        }

        return null;
    }

    /**
     * Page through `/scheduled_events` for one token, counting events on one event type.
     *
     * Scoped to the organization first and retried against the user on a 403, the same order
     * queryScheduledEvents() uses — a token that can read its own calendar but not the org's is
     * normal, and is not a broken token.
     *
     * @param  array{organization: ?string, user: ?string}  $identity
     */
    protected function walkScheduledEvents(
        string $token,
        array $identity,
        string $eventTypeUri,
        string $minStartIso,
        string $maxStartIso,
        string $who,
        int $timeoutSeconds,
        int $connectTimeoutSeconds,
        float $deadline
    ): ?int {
        $base = [
            'status' => 'active',
            'count' => 100,
            'min_start_time' => $minStartIso,
            'max_start_time' => $maxStartIso,
        ];

        $scopes = [];

        if (! empty($identity['organization'])) {
            $scopes[] = ['organization' => $identity['organization']];
        }

        if (! empty($identity['user'])) {
            $scopes[] = ['user' => $identity['user']];
        }

        foreach ($scopes as $i => $scope) {
            $url = 'https://api.calendly.com/scheduled_events';
            $query = $scope + $base;
            $booked = 0;

            for ($page = 0; $page < self::SCHEDULED_EVENTS_PAGE_CAP; $page++) {
                /*
                 * Checked per page, not only per token. The budget is what bounds the walk in
                 * wall-clock terms, and a five-page window on a slow Calendly spends it entirely
                 * inside this loop where the caller's between-tokens check never runs.
                 */
                if (microtime(true) >= $deadline) {
                    Log::warning('CalendlyClient: utilization count exceeded its time budget mid-walk, discarding a partial count');

                    return null;
                }

                $response = $this->getForToken(
                    $token,
                    $url,
                    $query,
                    $timeoutSeconds,
                    $connectTimeoutSeconds
                );

                if ($response === null) {
                    return null;
                }

                $status = $response->status();

                // The org scope is refused for this token but it has a user scope left to try.
                if ($status === 403 && isset($scopes[$i + 1])) {
                    continue 2;
                }

                if ($status === 429) {
                    Log::warning("CalendlyClient: utilization count token [{$who}] rate-limited, failing over");
                    $this->tokenPool->markRateLimited($token);

                    return null;
                }

                if ($status === 401 || $status === 403) {
                    Log::warning("CalendlyClient: utilization count token [{$who}] returned {$status}, failing over");
                    $this->forgetUserIdentity($token);
                    $this->tokenPool->recordFailure($token, 'booking');

                    return null;
                }

                if (! $response->successful()) {
                    Log::warning("CalendlyClient: utilization count token [{$who}] returned {$status}", [
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                foreach ((array) $response->json('collection', []) as $event) {
                    if (is_array($event) && ($event['event_type'] ?? null) === $eventTypeUri) {
                        $booked++;
                    }
                }

                $next = $response->json('pagination.next_page');

                if (! is_string($next) || $next === '') {
                    return $booked;
                }

                // next_page is a full URL carrying its own query string; see getForToken().
                $url = $next;
                $query = [];
            }

            /*
             * A partial count is worse than none. It would read as a tier with room left on
             * precisely the busiest calendar, which is the one case this whole check exists for.
             */
            Log::warning('CalendlyClient: scheduled_events pagination hit its page cap, discarding a partial utilization count');

            return null;
        }

        return null;
    }

    /**
     * Duplicate-booking preflight: does the given email already have an active
     * scheduled event for this exact event type + start time? Ported from
     * legacy's is_already_booked(), then rewritten for latency — it was measured
     * at 4 057 ms p50 / 6 144 ms p95 *inside the user's request*
     * (docs/performance-baseline.md, Part 2, Pass C).
     *
     * What it used to do, and why it was slow:
     *   for each of the 4 pooled tokens: GET users/me, then GET scheduled_events
     *   = 8 sequential round-trips, walked to completion even when the first
     *   account already answered "this lead has no prior booking" — which is the
     *   overwhelmingly common case.
     *
     * What it does now:
     *   - `users/me` is resolved through a 12h cache (userIdentity()), because a
     *     token's org/user URI does not change. Warm, that is 0 round-trips.
     *   - The pool is used as a *failover*, not a fan-out: the first token that
     *     returns a usable answer ends the loop. Rotation happens only when a
     *     token cannot answer — 429 (rate-limited, the reason the pool exists),
     *     401/403 (bad/scoped-out token) or 404.
     *   - The `scheduled_events` query is narrowed to a +/-60s window around the
     *     requested slot, so the account's whole booking history is neither
     *     transferred nor scanned.
     *   - A connection error or a 5xx stops immediately and reports "no prior
     *     booking" rather than retrying three more accounts behind a 15s timeout;
     *     PREFLIGHT_BUDGET_SECONDS bounds the whole thing even when every token
     *     rotates.
     *
     * Warm-cache cost is therefore **one** round-trip instead of eight.
     *
     * Deliberate behaviour change: this no longer queries every pooled account.
     * The pooled tokens are separate Calendly accounts, but createInvitee() books
     * through sendWithFailover(), which also takes the first eligible token — so
     * the account checked here is the account the booking lands on. The residual
     * gap (a prior booking made while the primary token was rate-limited, so it
     * landed on a different account) is still covered for the common double-submit
     * case by BookMeetingAction's activity-log guard, which is account-agnostic.
     *
     * Fail-open by design: null means "no duplicate found, go ahead and book".
     * A Calendly outage must not block a booking submit.
     */
    public function findExistingInvitee(string $email, string $eventTypeUri, string $startTimeIso): ?array
    {
        $deadline = microtime(true) + self::PREFLIGHT_BUDGET_SECONDS;

        foreach ($this->tokenPool->getEligibleTokens(null) as $item) {
            if (microtime(true) >= $deadline) {
                Log::warning('CalendlyClient: duplicate-booking preflight exceeded its time budget, treating as no prior booking');

                return null;
            }

            $token = $item['token'];

            $identity = $this->userIdentity($token);
            if ($identity === null) {
                Log::warning("CalendlyClient: preflight could not resolve identity for token [{$item['label']}], failing over");

                continue;
            }

            $response = $this->queryScheduledEvents($token, $identity, $email, $startTimeIso);

            if ($response === null) {
                // Connection error or timeout. Calendly is unreachable, not this
                // token's fault — rotating would just pay the timeout again.
                Log::error('CalendlyClient: preflight could not reach Calendly, treating as no prior booking');

                return null;
            }

            $status = $response->status();

            if ($status === 429) {
                Log::warning("CalendlyClient: preflight token [{$item['label']}] rate-limited, failing over to next token in pool");
                $this->tokenPool->markRateLimited($token);

                continue;
            }

            if ($status === 401 || $status === 403) {
                Log::warning("CalendlyClient: preflight token [{$item['label']}] returned {$status}, recording failure and failing over");
                $this->forgetUserIdentity($token);
                $this->tokenPool->recordFailure($token, 'booking');

                continue;
            }

            if ($status === 404) {
                continue;
            }

            if ($status >= 500) {
                Log::error("CalendlyClient: preflight token [{$item['label']}] returned server error {$status}, treating as no prior booking");

                return null;
            }

            if (! $response->successful()) {
                Log::warning("CalendlyClient: preflight token [{$item['label']}] returned {$status}", ['body' => $response->body()]);

                continue;
            }

            // A working account answered. Short-circuit either way: a match is the
            // duplicate we were looking for, and an empty collection is a definitive
            // "no prior booking" — the case that used to cost all eight round-trips.
            foreach ($response->json('collection', []) as $scheduledEvent) {
                $sameEventType = ($scheduledEvent['event_type'] ?? null) === $eventTypeUri;
                $sameStartTime = isset($scheduledEvent['start_time'])
                    && strtotime($scheduledEvent['start_time']) === strtotime($startTimeIso);

                if ($sameEventType && $sameStartTime) {
                    return $scheduledEvent;
                }
            }

            return null;
        }

        return null;
    }

    /**
     * The org URI + user URI a token authenticates as, cached for 12h.
     *
     * This lives on the client rather than in CalendlyMetadataCache because that
     * class is constructed *with* a CalendlyClient — caching token identity there
     * would make the two mutually dependent. CalendlyMetadataCache caches
     * per-event-type metadata; this is per-token identity, and the only caller
     * that needs it is the client itself.
     *
     * @return array{organization: ?string, user: ?string, email: ?string, name: ?string}|null
     */
    public function userIdentity(string $token, bool $forceRefresh = false): ?array
    {
        $key = self::userIdentityKey($token);

        if (! $forceRefresh) {
            $cached = SoftCache::get($key);

            if (is_array($cached) && (! empty($cached['organization']) || ! empty($cached['user']))) {
                return $cached;
            }
        }

        $response = $this->getForToken(
            $token,
            'https://api.calendly.com/users/me',
            [],
            self::PREFLIGHT_TIMEOUT_SECONDS,
            self::PREFLIGHT_CONNECT_TIMEOUT_SECONDS
        );

        if (! $response || ! $response->successful()) {
            return null;
        }

        $identity = [
            'organization' => $response->json('resource.current_organization'),
            'user' => $response->json('resource.uri'),

            /*
             * The account this token belongs to. Free on this call, and the only thing that
             * makes a pool failure actionable: "a token is rate limited" prompts a hunt through
             * four tokens, "admin@remoteleverage.com is rate limited" names who to contact.
             */
            'email' => $response->json('resource.email'),
            'name' => $response->json('resource.name'),
        ];

        if (empty($identity['organization']) && empty($identity['user'])) {
            return null;
        }

        SoftCache::put($key, $identity, now()->addSeconds(self::USER_IDENTITY_TTL_SECONDS));

        return $identity;
    }

    /**
     * How a pooled token is named in logs: its account email where known, its label otherwise.
     *
     * @param  array{label: string, token: string}  $item
     */
    protected function describeToken(array $item): string
    {
        $email = $this->cachedAccountEmail((string) ($item['token'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));

        return match (true) {
            $email !== null && $label !== '' => "{$email} / {$label}",
            $email !== null => $email,
            $label !== '' => $label,
            default => 'unlabelled token',
        };
    }

    /**
     * The account email for a token, from cache only — never a network call.
     *
     * Deliberately non-fetching. Its callers are the logger that records outbound calls and the
     * admin table that lists the pool; making either of those hit Calendly would mean an HTTP
     * request triggering a log write that triggers an HTTP request. The value is populated as a
     * side effect of the identity lookups the client already performs, so in practice it is warm
     * for every token that has been used, and absent for one that never has — which is itself
     * worth seeing.
     */
    public function cachedAccountEmail(string $token): ?string
    {
        $cached = SoftCache::get(self::userIdentityKey($token));

        if (! is_array($cached)) {
            return null;
        }

        $email = trim((string) ($cached['email'] ?? ''));

        return $email === '' ? null : $email;
    }

    /**
     * Drop a token's cached identity — on 401/403 (token revoked or re-scoped),
     * and from the admin screen when the pool is edited.
     */
    public function forgetUserIdentity(string $token): void
    {
        SoftCache::forget(self::userIdentityKey($token));
    }

    /**
     * One `scheduled_events` lookup for a token, scoped to the account's
     * organization when it has one and falling back to user scope only on 403
     * (a token without organization-read permission). The legacy code also
     * re-queried on a null response; that is a connection failure, so retrying
     * the same unreachable host under a second timeout is paid latency for
     * nothing — it is reported as unreachable instead.
     *
     * @param  array{organization: ?string, user: ?string}  $identity
     */
    protected function queryScheduledEvents(string $token, array $identity, string $email, string $startTimeIso): ?Response
    {
        $slot = strtotime($startTimeIso);

        $base = [
            'invitee_email' => $email,
            'status' => 'active',
            'count' => 100,
        ];

        if ($slot !== false) {
            // The match below is exact equality on start_time, so a +/-60s window
            // is a strict superset of what can match, and keeps the response to the
            // handful of events around the requested slot instead of the account's
            // entire active history.
            $base['min_start_time'] = gmdate('Y-m-d\TH:i:s\Z', $slot - 60);
            $base['max_start_time'] = gmdate('Y-m-d\TH:i:s\Z', $slot + 60);
        }

        $orgUri = $identity['organization'] ?? null;
        $userUri = $identity['user'] ?? null;

        $response = null;

        if ($orgUri) {
            $response = $this->scheduledEventsRequest($token, ['organization' => $orgUri] + $base);

            if ($response === null || $response->status() !== 403 || ! $userUri) {
                return $response;
            }
        }

        if ($userUri) {
            return $this->scheduledEventsRequest($token, ['user' => $userUri] + $base);
        }

        return $response;
    }

    /**
     * A time-windowed scheduled_events GET, retried once unwindowed if Calendly
     * rejects the window params with a 400. The window is verified to work today;
     * the retry means a future API change narrows the result set rather than
     * silently turning the duplicate guard off.
     *
     * @param  array<string, mixed>  $query
     */
    protected function scheduledEventsRequest(string $token, array $query): ?Response
    {
        $response = $this->getForToken(
            $token,
            'https://api.calendly.com/scheduled_events',
            $query,
            self::PREFLIGHT_TIMEOUT_SECONDS,
            self::PREFLIGHT_CONNECT_TIMEOUT_SECONDS
        );

        if ($response && $response->status() === 400 && isset($query['min_start_time'])) {
            Log::warning('CalendlyClient: scheduled_events rejected the time window, retrying unwindowed', [
                'body' => $response->body(),
            ]);

            unset($query['min_start_time'], $query['max_start_time']);

            return $this->getForToken(
                $token,
                'https://api.calendly.com/scheduled_events',
                $query,
                self::PREFLIGHT_TIMEOUT_SECONDS,
                self::PREFLIGHT_CONNECT_TIMEOUT_SECONDS
            );
        }

        return $response;
    }

    protected static function userIdentityKey(string $token): string
    {
        return self::USER_IDENTITY_PREFIX.substr(hash('sha256', $token), 0, 16);
    }

    /**
     * Cancel a scheduled event. Used when a repeat booking submission picks a
     * different slot than an earlier one for the same email — the earlier
     * meeting is superseded rather than left on the calendar as a stray
     * duplicate. Best-effort: failure here should never block the new
     * booking from succeeding, so callers should treat a false return as
     * "log it and move on," not a reason to fail the request.
     */
    public function cancelScheduledEvent(string $eventUriOrUuid, string $reason = 'Rescheduled to a new time.'): bool
    {
        $uuid = str_starts_with($eventUriOrUuid, 'http')
            ? basename(rtrim($eventUriOrUuid, '/'))
            : $eventUriOrUuid;

        try {
            $response = $this->sendWithFailover(
                fn (string $token) => Http::withToken($token)
                    ->timeout(15)
                    ->post("https://api.calendly.com/scheduled_events/{$uuid}/cancellation", [
                        'reason' => $reason,
                    ])
            );

            if (! $response || ! $response->successful()) {
                Log::warning('CalendlyClient: failed to cancel scheduled event', [
                    'event' => $eventUriOrUuid,
                    'status' => $response?->status(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Cancellation Exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Fetch scheduled event details by URI or UUID.
     */
    public function getScheduledEvent(string $eventUriOrUuid): ?array
    {
        $url = str_starts_with($eventUriOrUuid, 'http')
            ? $eventUriOrUuid
            : 'https://api.calendly.com/scheduled_events/'.rawurlencode($eventUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url));

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Event Detail Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch invitee details by URI or UUID. Used by the Live Call concurrency
     * lock to self-heal when a "busy" session's invitee was actually canceled.
     */
    public function getInvitee(string $inviteeUriOrUuid): ?array
    {
        $url = str_starts_with($inviteeUriOrUuid, 'http')
            ? $inviteeUriOrUuid
            : 'https://api.calendly.com/scheduled_events/invitees/'.rawurlencode($inviteeUriOrUuid);

        try {
            $response = $this->sendWithFailover(fn (string $token) => Http::withToken($token)->get($url));

            return $response && $response->successful() ? $response->json('resource') : null;
        } catch (\Throwable $e) {
            Log::error('CalendlyClient Invitee Detail Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Poll a freshly-created scheduled event for its Google Meet location, since
     * Calendly's location data can lag slightly right after invitee creation.
     * Ported from JoinLiveCallIntegration's polling loop (up to 20x at 300ms).
     */
    public function pollForMeetLocation(string $scheduledEventUri, int $attempts = 20, int $delayMicroseconds = 300000): ?string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $eventDetails = $this->getScheduledEvent($scheduledEventUri);
            $location = $eventDetails['location'] ?? [];

            $joinUrl = $location['join_url'] ?? $location['location'] ?? null;
            if ($joinUrl) {
                return $joinUrl;
            }

            $notes = $eventDetails['meeting_notes_plain'] ?? '';
            if (preg_match('#https://meet\.google\.com/[a-z0-9-]+#i', (string) $notes, $matches)) {
                return $matches[0];
            }

            if ($i < $attempts - 1) {
                usleep($delayMicroseconds);
            }
        }

        return null;
    }
}
