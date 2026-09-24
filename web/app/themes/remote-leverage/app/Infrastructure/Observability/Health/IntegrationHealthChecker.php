<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health;

use App\Infrastructure\Observability\IntegrationCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Turns each registered `IntegrationHealthCheck` into a `healthy` / `degraded` / `down` verdict.
 *
 * ## Why this reads `rl_integration_calls` instead of pinging each API
 *
 * `IntegrationCallRecorder` already writes every outbound Calendly, HubSpot, Slack, Stripe,
 * Google, ZeroBounce, PostHog, Customer.io and Meta call, success or failure — see
 * `ObservabilityServiceProvider`. A live probe would need its own credential handling and its
 * own idea of what a healthy response looks like per integration, duplicating that plumbing, and
 * for ZeroBounce it would spend a paid lookup on every dashboard load to answer a question the
 * log already answers for free. Reading recent real traffic is also the more honest signal: a
 * probe that succeeds proves the API is reachable, not that the last ten actual syncs did not
 * fail on a payload the probe never sends.
 *
 * ## Verdict
 *
 * `down`   — not configured, or a run of `down_consecutive_failures` in a row, or a failure rate
 *            at or above `down_failure_rate` with enough calls to trust it.
 * `degraded` — a shorter run of failures, or a failure rate at or above `degraded_failure_rate`.
 * `healthy`  — everything else, including "configured but nothing has called it in the window",
 *            because silence is not evidence of a problem.
 *
 * ## The one exception
 *
 * A check that implements `SelfEvaluatingHealthCheck` (the database) skips all of the above and
 * supplies its own verdict via `probe()` — see that interface for why.
 */
class IntegrationHealthChecker
{
    /**
     * @param  iterable<IntegrationHealthCheck>  $checks
     */
    public function __construct(
        protected iterable $checks = [],
    ) {}

    /**
     * @return array<string, IntegrationHealth> keyed by integration name, in registration order
     */
    public function checkAll(): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            try {
                $results[$check->integration()] = $check instanceof SelfEvaluatingHealthCheck
                    ? $check->probe()
                    : $this->evaluate($check);
            } catch (\Throwable $e) {
                // A health check that cannot itself run must not take the diagnostics page down —
                // that would turn "is HubSpot reachable" into "is the admin reachable".
                Log::warning('IntegrationHealthChecker: '.$check->integration().' check failed: '.$e->getMessage());

                $results[$check->integration()] = new IntegrationHealth(
                    integration: $check->integration(),
                    label: $check->label(),
                    alias: $check->alias(),
                    status: HealthStatus::Down,
                    configured: false,
                    reason: 'Health check threw: '.$e->getMessage(),
                    sampleSize: 0,
                    failureRate: null,
                    consecutiveFailures: 0,
                    lastCallAt: null,
                    lastError: null,
                );
            }
        }

        return $results;
    }

    public function evaluate(IntegrationHealthCheck $check): IntegrationHealth
    {
        $integration = $check->integration();
        $label = $check->label();
        $alias = $check->alias();

        if (! $check->isConfigured()) {
            return new IntegrationHealth(
                integration: $integration,
                label: $label,
                alias: $alias,
                status: HealthStatus::Down,
                configured: false,
                reason: $check instanceof ExplainsUnconfiguredReason
                    ? $check->unconfiguredReason()
                    : 'No credential configured',
                sampleSize: 0,
                failureRate: null,
                consecutiveFailures: 0,
                lastCallAt: null,
                lastError: null,
            );
        }

        $windowMinutes = (int) config('health.window_minutes', 30);

        /** @var Collection<int, IntegrationCall> $calls */
        $calls = IntegrationCall::query()
            ->forIntegration($integration)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['outcome', 'created_at', 'error_message']);

        $lastCall = $calls->first();

        if ($calls->isEmpty()) {
            return new IntegrationHealth(
                integration: $integration,
                label: $label,
                alias: $alias,
                status: HealthStatus::Healthy,
                configured: true,
                reason: "No calls in the last {$windowMinutes} minutes",
                sampleSize: 0,
                failureRate: null,
                consecutiveFailures: 0,
                lastCallAt: null,
                lastError: null,
            );
        }

        $sampleSize = $calls->count();
        $failed = $calls->filter(fn (IntegrationCall $call) => in_array($call->outcome, ['failed', 'error'], true));
        $failureRate = $failed->count() / $sampleSize;

        $consecutiveFailures = 0;

        foreach ($calls as $call) { // newest first
            if (! in_array($call->outcome, ['failed', 'error'], true)) {
                break;
            }

            $consecutiveFailures++;
        }

        $minSample = (int) config('health.min_sample', 3);
        $degradedRate = (float) config('health.degraded_failure_rate', 0.20);
        $downRate = (float) config('health.down_failure_rate', 0.75);
        $downConsecutive = (int) config('health.down_consecutive_failures', 5);
        $degradedConsecutive = (int) config('health.degraded_consecutive_failures', 2);

        $status = match (true) {
            $consecutiveFailures >= $downConsecutive => HealthStatus::Down,
            $sampleSize >= $minSample && $failureRate >= $downRate => HealthStatus::Down,
            $consecutiveFailures >= $degradedConsecutive => HealthStatus::Degraded,
            $sampleSize >= $minSample && $failureRate >= $degradedRate => HealthStatus::Degraded,
            default => HealthStatus::Healthy,
        };

        $lastError = $failed->first()?->error_message;

        return new IntegrationHealth(
            integration: $integration,
            label: $label,
            alias: $alias,
            status: $status,
            configured: true,
            reason: $this->reasonFor($status, $sampleSize, $failureRate, $consecutiveFailures, $windowMinutes),
            sampleSize: $sampleSize,
            failureRate: round($failureRate, 4),
            consecutiveFailures: $consecutiveFailures,
            lastCallAt: $lastCall?->created_at,
            lastError: $lastError,
        );
    }

    protected function reasonFor(
        HealthStatus $status,
        int $sampleSize,
        float $failureRate,
        int $consecutiveFailures,
        int $windowMinutes,
    ): string {
        if ($status === HealthStatus::Healthy) {
            return "{$sampleSize} call(s) in the last {$windowMinutes} minutes, ".round($failureRate * 100).'% failed';
        }

        if ($consecutiveFailures >= 2) {
            return "{$consecutiveFailures} call(s) in a row failed";
        }

        return round($failureRate * 100)."% of {$sampleSize} call(s) in the last {$windowMinutes} minutes failed";
    }
}
