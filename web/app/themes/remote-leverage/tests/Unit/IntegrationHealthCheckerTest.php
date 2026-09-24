<?php

declare(strict_types=1);

use App\Infrastructure\Observability\Health\ExplainsUnconfiguredReason;
use App\Infrastructure\Observability\Health\HealthStatus;
use App\Infrastructure\Observability\Health\IntegrationHealth;
use App\Infrastructure\Observability\Health\IntegrationHealthCheck;
use App\Infrastructure\Observability\Health\IntegrationHealthChecker;
use App\Infrastructure\Observability\Health\SelfEvaluatingHealthCheck;
use App\Infrastructure\Observability\IntegrationCall;

/*
 * `IntegrationHealthChecker` is the one place that turns raw `rl_integration_calls` rows into a
 * healthy/degraded/down verdict — see its own docblock for the reasoning. Every threshold in
 * config/health.php is a decision this suite pins down, because a config default silently
 * changing is exactly the kind of thing that would otherwise only be noticed from a false or
 * missing alert in production.
 */

/**
 * A check with a fixed `isConfigured()` answer and no gateway behind it — every scenario here
 * is about how the checker classifies calls, not about any one integration's own credential
 * resolution (those are covered in IntegrationHealthChecksTest and GoogleHealthCheckTest).
 */
class FixedHealthCheck implements IntegrationHealthCheck
{
    public function __construct(
        protected string $name = 'fixture',
        protected bool $configured = true,
    ) {}

    public function integration(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function alias(): string
    {
        return $this->name.'_alias';
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}

class ExplainingHealthCheck extends FixedHealthCheck implements ExplainsUnconfiguredReason
{
    public function unconfiguredReason(): string
    {
        return 'Signed in, but the billing project is not set';
    }
}

class ThrowingHealthCheck implements IntegrationHealthCheck
{
    public function integration(): string
    {
        return 'throws';
    }

    public function label(): string
    {
        return 'Throws';
    }

    public function alias(): string
    {
        return 'throws_alias';
    }

    public function isConfigured(): bool
    {
        throw new RuntimeException('gateway construction failed');
    }
}

class FixedSelfEvaluatingCheck implements SelfEvaluatingHealthCheck
{
    public function __construct(protected IntegrationHealth $result) {}

    public function integration(): string
    {
        return $this->result->integration;
    }

    public function label(): string
    {
        return $this->result->label;
    }

    public function alias(): string
    {
        return $this->result->alias;
    }

    public function isConfigured(): bool
    {
        // Deliberately the opposite of $result->status, so a test can prove the checker never
        // consults this for a self-evaluating check.
        return $this->result->status !== HealthStatus::Healthy;
    }

    public function probe(): IntegrationHealth
    {
        return $this->result;
    }
}

/**
 * @param  array<int, string>  $outcomesNewestFirst  'succeeded', 'failed' or 'error'
 */
function seedCalls(string $integration, array $outcomesNewestFirst, int $minutesAgoStart = 1): void
{
    foreach ($outcomesNewestFirst as $i => $outcome) {
        IntegrationCall::query()->create([
            'integration' => $integration,
            'method' => 'GET',
            'url' => 'https://api.example.com/x',
            'outcome' => $outcome,
            'error_message' => $outcome === 'succeeded' ? null : 'boom',
            'created_at' => now()->subMinutes($minutesAgoStart + $i),
        ]);
    }
}

beforeEach(function () {
    IntegrationCall::query()->delete();

    config([
        'health.window_minutes' => 30,
        'health.min_sample' => 3,
        'health.degraded_failure_rate' => 0.20,
        'health.down_failure_rate' => 0.75,
        'health.down_consecutive_failures' => 5,
        'health.degraded_consecutive_failures' => 2,
    ]);
});

describe('not configured', function () {
    test('reports down with a generic reason by default', function () {
        $checker = new IntegrationHealthChecker([new FixedHealthCheck('x', configured: false)]);

        $health = $checker->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Down)
            ->and($health->configured)->toBeFalse()
            ->and($health->reason)->toBe('No credential configured')
            ->and($health->sampleSize)->toBe(0)
            ->and($health->lastCallAt)->toBeNull();
    });

    test('a check that explains itself overrides the generic reason', function () {
        $checker = new IntegrationHealthChecker([new ExplainingHealthCheck('google', configured: false)]);

        $health = $checker->checkAll()['google'];

        expect($health->status)->toBe(HealthStatus::Down)
            ->and($health->reason)->toBe('Signed in, but the billing project is not set');
    });

    test('rl_integration_calls is never queried, even if rows exist for it', function () {
        // Traffic from before a credential was revoked must not make a currently-unconfigured
        // integration look healthy.
        seedCalls('x', ['succeeded', 'succeeded']);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x', configured: false)]))
            ->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Down)
            ->and($health->sampleSize)->toBe(0);
    });
});

describe('configured, no recent traffic', function () {
    test('silence reads as healthy, not as a problem', function () {
        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Healthy)
            ->and($health->reason)->toBe('No calls in the last 30 minutes')
            ->and($health->sampleSize)->toBe(0);
    });

    test('a call outside the window is the same as no call at all', function () {
        seedCalls('x', ['failed'], minutesAgoStart: 45);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Healthy)
            ->and($health->sampleSize)->toBe(0);
    });
});

describe('classification from recent calls', function () {
    test('every recent call succeeding is healthy', function () {
        seedCalls('x', ['succeeded', 'succeeded', 'succeeded']);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Healthy)
            ->and($health->sampleSize)->toBe(3)
            ->and($health->failureRate)->toBe(0.0);
    });

    test('a failure rate at the down threshold, with enough calls to trust it, is down', function () {
        // Newest-first: fail, success, fail, fail — 3 of 4 failed (75%), but the newest call
        // succeeded, so this is a rate problem, not a consecutive-run problem.
        seedCalls('x', ['failed', 'succeeded', 'failed', 'failed']);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Down)
            ->and($health->consecutiveFailures)->toBe(1)
            ->and($health->failureRate)->toBe(0.75)
            ->and($health->reason)->toContain('75%');
    });

    test('a failure rate between the two thresholds is degraded', function () {
        // Newest-first: success, fail, success, fail, success — 2 of 5 failed (40%), no
        // consecutive run at all.
        seedCalls('x', ['succeeded', 'failed', 'succeeded', 'failed', 'succeeded']);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Degraded)
            ->and($health->consecutiveFailures)->toBe(0)
            ->and($health->failureRate)->toBe(0.4);
    });

    test('a run of failures at the down threshold is down regardless of the overall rate', function () {
        // 5 failures in a row, then a long history of successes further back — the rate over
        // the whole sample is nowhere near 75%, but an outage is exactly this shape.
        seedCalls('x', array_merge(
            array_fill(0, 5, 'failed'),
            array_fill(0, 20, 'succeeded'),
        ));

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Down)
            ->and($health->consecutiveFailures)->toBe(5)
            ->and($health->reason)->toBe('5 call(s) in a row failed');
    });

    test('a short run of failures below the down threshold is degraded, even at a low overall rate', function () {
        seedCalls('x', array_merge(
            array_fill(0, 2, 'failed'),
            array_fill(0, 20, 'succeeded'),
        ));

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Degraded)
            ->and($health->consecutiveFailures)->toBe(2);
    });

    test('a sample below the minimum does not trigger the rate rule', function () {
        // One failure, then a success — not consecutive, and too small a sample (2 < 3) to
        // trust a 50% rate.
        seedCalls('x', ['failed', 'succeeded']);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Healthy)
            ->and($health->sampleSize)->toBe(2);
    });

    test('an "error" outcome counts as a failure exactly like "failed"', function () {
        seedCalls('x', array_merge(array_fill(0, 5, 'error'), array_fill(0, 5, 'succeeded')));

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->status)->toBe(HealthStatus::Down)
            ->and($health->consecutiveFailures)->toBe(5);
    });

    test('the most recent error message is surfaced as the last error', function () {
        IntegrationCall::query()->create([
            'integration' => 'x', 'method' => 'GET', 'url' => 'https://api.example.com/x',
            'outcome' => 'failed', 'error_message' => 'HubSpot rejected the payload',
            'created_at' => now()->subMinutes(1),
        ]);

        $health = (new IntegrationHealthChecker([new FixedHealthCheck('x')]))->checkAll()['x'];

        expect($health->lastError)->toBe('HubSpot rejected the payload')
            ->and($health->lastCallAt)->not->toBeNull();
    });
});

describe('checkAll resilience and shape', function () {
    test('a check that throws is reported as down and does not stop the others', function () {
        $results = (new IntegrationHealthChecker([
            new ThrowingHealthCheck,
            new FixedHealthCheck('ok'),
        ]))->checkAll();

        expect($results)->toHaveKeys(['throws', 'ok'])
            ->and($results['throws']->status)->toBe(HealthStatus::Down)
            ->and($results['throws']->reason)->toContain('gateway construction failed')
            ->and($results['ok']->status)->toBe(HealthStatus::Healthy);
    });

    test('results are keyed by integration name, in registration order', function () {
        $results = (new IntegrationHealthChecker([
            new FixedHealthCheck('first'),
            new FixedHealthCheck('second'),
        ]))->checkAll();

        expect(array_keys($results))->toBe(['first', 'second']);
    });

    test('a self-evaluating check is returned exactly as its probe reports it', function () {
        $forced = new IntegrationHealth(
            integration: 'database',
            label: 'Database',
            alias: 'data',
            status: HealthStatus::Down,
            configured: true,
            reason: 'Connection failed',
            sampleSize: 1,
            failureRate: 1.0,
            consecutiveFailures: 1,
            lastCallAt: null,
            lastError: 'Connection failed',
        );

        // isConfigured() on this fixture deliberately answers true only when the forced result is
        // NOT Healthy — a self-evaluating check bypasses isConfigured() entirely, so if the
        // checker ever consulted it here, this would come back Healthy instead of Down.
        $health = (new IntegrationHealthChecker([new FixedSelfEvaluatingCheck($forced)]))
            ->checkAll()['database'];

        expect($health)->toBe($forced)
            ->and($health->status)->toBe(HealthStatus::Down);
    });
});
