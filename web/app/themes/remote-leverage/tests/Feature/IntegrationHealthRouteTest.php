<?php

declare(strict_types=1);

use App\Application\Http\Controllers\IntegrationHealthController;
use App\Infrastructure\Observability\Health\HealthStatus;
use App\Infrastructure\Observability\Health\IntegrationHealth;
use App\Infrastructure\Observability\Health\IntegrationHealthChecker;
use App\Infrastructure\Observability\Health\SelfEvaluatingHealthCheck;

/*
 * `GET /api/health/integrations` — same construction style as GatedDownloadTest: the real
 * controller, driven directly rather than through the router (this suite has no HTTP kernel to
 * dispatch through), with a checker built from fixed, fake checks so the HTTP-status contract
 * can be pinned down without depending on which real integrations happen to be configured
 * wherever this runs.
 *
 * Self-evaluating rather than a plain IntegrationHealthCheck: a plain check only controls
 * isConfigured(), and everything else — Healthy vs Degraded in particular — would still come
 * from real rl_integration_calls classification (already covered exhaustively in
 * IntegrationHealthCheckerTest). This fixture needs to dictate the exact status the controller
 * sees, independent of any log data.
 */
class RouteFixtureHealthCheck implements SelfEvaluatingHealthCheck
{
    public function __construct(
        protected string $name,
        protected HealthStatus $status,
        protected string $reason = 'fixture',
        protected ?string $lastError = null,
    ) {}

    public function integration(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function isConfigured(): bool
    {
        return $this->status !== HealthStatus::Down;
    }

    public function probe(): IntegrationHealth
    {
        return new IntegrationHealth(
            integration: $this->name,
            label: $this->label(),
            status: $this->status,
            configured: $this->isConfigured(),
            reason: $this->reason,
            sampleSize: 0,
            failureRate: null,
            consecutiveFailures: 0,
            lastCallAt: null,
            lastError: $this->lastError,
        );
    }
}

function integrationHealthController(HealthStatus ...$statuses): IntegrationHealthController
{
    $checks = [];

    foreach ($statuses as $i => $status) {
        $checks[] = new RouteFixtureHealthCheck("service{$i}", $status);
    }

    return new IntegrationHealthController(new IntegrationHealthChecker($checks));
}

test('everything healthy answers 200 with an overall healthy status', function () {
    $response = integrationHealthController(HealthStatus::Healthy, HealthStatus::Healthy)->index();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['status'])->toBe('healthy');
});

test('a degraded integration still answers 200 — a blip should not fail an uptime check', function () {
    $response = integrationHealthController(HealthStatus::Healthy, HealthStatus::Degraded)->index();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['status'])->toBe('degraded');
});

test('one down integration among several answers 503 with the overall status down', function () {
    $response = integrationHealthController(HealthStatus::Healthy, HealthStatus::Degraded, HealthStatus::Down)->index();

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true)['status'])->toBe('down');
});

test('no checks registered at all is reported as healthy rather than erroring', function () {
    $response = integrationHealthController()->index();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['status'])->toBe('healthy')
        ->and($response->getData(true)['integrations'])->toBe([]);
});

test('the response carries the per-integration breakdown, keyed by integration name', function () {
    $data = integrationHealthController(HealthStatus::Down)->index()->getData(true);

    expect($data)->toHaveKeys(['status', 'checked_at', 'integrations'])
        ->and($data['integrations'])->toHaveKey('service0')
        ->and($data['integrations']['service0']['status'])->toBe('down')
        ->and($data['integrations']['service0']['label'])->toBe('Service0');
});

test('reason and last_error never reach this unauthenticated response', function () {
    // reason can name a real identity (GoogleHealthCheck names the signed-in Google account),
    // and last_error is a stored error_message IntegrationCallRecorder never redacts — see
    // IntegrationHealth::toPublicArray(). This is the regression test for that: an anonymous
    // caller of this route must never see either field, or anything that was only ever in them.
    $check = new RouteFixtureHealthCheck(
        'google',
        HealthStatus::Down,
        reason: 'Signed in as ops@remoteleverage.com, but no billing project is set',
        lastError: 'cURL error 6: Could not resolve host: warehouse-internal.aws.remoteleverage.local',
    );

    $response = (new IntegrationHealthController(new IntegrationHealthChecker([$check])))->index();
    $data = $response->getData(true);
    $raw = $response->getContent();

    expect($data['integrations']['google'])->not->toHaveKeys(['reason', 'last_error'])
        ->and($raw)->not->toContain('ops@remoteleverage.com')
        ->and($raw)->not->toContain('warehouse-internal.aws.remoteleverage.local')
        // The fields this endpoint is supposed to carry are still there.
        ->and($data['integrations']['google'])->toHaveKeys([
            'integration', 'label', 'status', 'configured', 'sample_size', 'failure_rate',
            'consecutive_failures', 'last_call_at',
        ]);
});
