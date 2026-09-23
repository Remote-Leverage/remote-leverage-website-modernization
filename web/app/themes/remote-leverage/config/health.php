<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Integration health
    |--------------------------------------------------------------------------
    |
    | Status per integration is read off `rl_integration_calls` — the log
    | ObservabilityServiceProvider already writes for every outbound call — rather
    | than by pinging each API. That table exists precisely to answer "is this
    | integration working", and a live probe would add credentials, cost (ZeroBounce
    | bills per lookup) and failure modes of its own for a question already
    | answered by real traffic.
    |
    | An integration with no configured credential is reported `down`: whatever
    | the reason, nothing can be sent through it right now, which is the same
    | practical state as an outage. See docs/configuration.md and
    | `.env.example` for which key enables which integration.
    |
    */

    /*
     * How far back to look for recent calls.
     *
     * Short enough that a resolved outage clears itself without intervention, long
     * enough that a low-traffic integration (Meta, ZeroBounce) has a call or two to
     * judge from between leads rather than reporting "no data" on every check.
     */
    'window_minutes' => (int) env('RL_HEALTH_WINDOW_MINUTES', 30),

    /*
     * Below this many calls in the window, the failure rate is too noisy to act on
     * — one failed call out of one is 100%, and is not the same claim as one failed
     * call out of fifty. Only `down_consecutive_failures` can still fail a small
     * sample.
     */
    'min_sample' => (int) env('RL_HEALTH_MIN_SAMPLE', 3),

    /*
     * Failure-rate bands. `degraded` is calls failing often enough to notice;
     * `down` is calls failing often enough that the integration is not usably
     * working. Between the two, most of the traffic is still getting through.
     */
    'degraded_failure_rate' => (float) env('RL_HEALTH_DEGRADED_FAILURE_RATE', 0.20),

    'down_failure_rate' => (float) env('RL_HEALTH_DOWN_FAILURE_RATE', 0.75),

    /*
     * A run of failures this long marks an integration `down` regardless of sample
     * size or rate — the thing that actually happens during an outage is every
     * call failing, not a rate crossing a threshold over a large enough sample to
     * trust.
     */
    'down_consecutive_failures' => (int) env('RL_HEALTH_DOWN_CONSECUTIVE_FAILURES', 5),

    /*
     * A run of failures this long, below the threshold above, is enough to call an
     * integration `degraded` even when the overall rate in the window is still low
     * — two failures in a row against forty successes an hour ago is a rate of 5%
     * and a problem that started two calls ago.
     */
    'degraded_consecutive_failures' => (int) env('RL_HEALTH_DEGRADED_CONSECUTIVE_FAILURES', 2),

    /*
    |--------------------------------------------------------------------------
    | Database health
    |--------------------------------------------------------------------------
    |
    | The database is the one entry in the health check that is not judged from
    | `rl_integration_calls` — see `SelfEvaluatingHealthCheck`. `DatabaseHealthCheck`
    | runs a bare `select 1` on every check and times it, rather than reading history,
    | so there are no failure-rate or sample-size settings here: a single query either
    | succeeds within budget, succeeds slowly, or throws.
    */

    'database' => [

        /*
         * A successful query slower than this is `degraded` rather than `healthy` — the
         * database answered, but slowly enough to be worth noticing before it becomes an
         * outage. `select 1` touches no table and no index, so on a healthy connection
         * this is round-trip latency, not query cost; a number this size being exceeded
         * points at connection contention or network trouble, not a slow query.
         */
        'degraded_latency_ms' => (int) env('RL_HEALTH_DB_DEGRADED_LATENCY_MS', 200),
    ],

];
