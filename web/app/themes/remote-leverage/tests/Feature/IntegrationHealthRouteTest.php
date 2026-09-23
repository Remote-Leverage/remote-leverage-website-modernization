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
            reason: 'fixture',
            sampleSize: 0,
            failureRate: null,
            consecutiveFailures: 0,
            lastCallAt: null,
            lastError: null,
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

test('the response carries the full per-integration breakdown, keyed by integration name', function () {
    $data = integrationHealthController(HealthStatus::Down)->index()->getData(true);

    expect($data)->toHaveKeys(['status', 'checked_at', 'integrations'])
        ->and($data['integrations'])->toHaveKey('service0')
        ->and($data['integrations']['service0']['status'])->toBe('down')
        ->and($data['integrations']['service0']['label'])->toBe('Service0');
});
