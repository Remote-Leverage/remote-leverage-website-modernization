<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health\Checks;

use App\Infrastructure\Observability\Health\HealthStatus;
use App\Infrastructure\Observability\Health\IntegrationHealth;
use App\Infrastructure\Observability\Health\SelfEvaluatingHealthCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Live `select 1` against the default connection, timed.
 *
 * Not judged from `rl_integration_calls` like every other check — see
 * `SelfEvaluatingHealthCheck`. `isConfigured()` always answers true: the database is not an
 * optional credential an operator may or may not have set up, it is the connection the request
 * that renders this answer is itself running on, so "not configured" is not a state this check
 * can ever honestly report.
 *
 * ## Why the error message is never returned as-is
 *
 * A connection failure's exception message routinely contains the thing an attacker would want
 * from an unauthenticated endpoint: `Access denied for user 'rl_app'@'10.0.4.12'` names the
 * database user and, on a cloud host, an internal IP. `IntegrationCallRecorder` fingerprints
 * exactly this class of detail before anything reaches `rl_integration_calls`; the same rule
 * applies here even though there is no table row to redact — the full exception still goes to
 * the log, where an operator with server access can read it, and only a generic reason reaches
 * `/api/health/integrations`.
 */
class DatabaseHealthCheck implements SelfEvaluatingHealthCheck
{
    public function integration(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'Database';
    }

    public function alias(): string
    {
        return 'data';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function probe(): IntegrationHealth
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            Log::warning('DatabaseHealthCheck: connection failed: '.$e->getMessage());

            return new IntegrationHealth(
                integration: $this->integration(),
                label: $this->label(),
                alias: $this->alias(),
                status: HealthStatus::Down,
                configured: true,
                reason: 'Connection failed',
                sampleSize: 1,
                failureRate: 1.0,
                consecutiveFailures: 1,
                lastCallAt: now(),
                lastError: 'Connection failed',
            );
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $threshold = (int) config('health.database.degraded_latency_ms', 200);
        $degraded = $latencyMs >= $threshold;

        return new IntegrationHealth(
            integration: $this->integration(),
            label: $this->label(),
            alias: $this->alias(),
            status: $degraded ? HealthStatus::Degraded : HealthStatus::Healthy,
            configured: true,
            reason: $degraded
                ? "Connected in {$latencyMs}ms, at or above the {$threshold}ms threshold"
                : "Connected in {$latencyMs}ms",
            sampleSize: 1,
            failureRate: 0.0,
            consecutiveFailures: 0,
            lastCallAt: now(),
            lastError: null,
        );
    }
}
