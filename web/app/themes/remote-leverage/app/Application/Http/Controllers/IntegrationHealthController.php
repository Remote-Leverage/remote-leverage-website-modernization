<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Infrastructure\Observability\Health\HealthStatus;
use App\Infrastructure\Observability\Health\IntegrationHealthChecker;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/health/integrations` — machine-readable status for every configured integration.
 *
 * Deliberately a separate route from `api.health` (routes/api.php), which is a bare liveness
 * check that always answers `healthy`. Folding integration status into that one would mean a
 * rotated HubSpot token or an expired Stripe key could flip the same signal an uptime monitor
 * or an ECS/ALB health check watches to decide whether to keep the container running — the
 * container is fine, HubSpot is not, and killing the former over the latter is the wrong fix. An
 * external monitor that specifically wants "are our integrations up" points at this route
 * instead; anything watching "is the site up" keeps pointing at `api.health`.
 *
 * ## Response
 *
 * `status` is the worst status across every configured integration (`HealthStatus::worstOf`).
 * The HTTP status code mirrors it at the two ends an uptime monitor acts on: 503 when anything
 * is `down` — the site cannot currently reach that integration at all — and 200 otherwise,
 * `degraded` included, because a degraded integration is still serving and paging on every
 * failure-rate blip is how people learn to ignore the monitor. `integrations` carries the full
 * per-integration breakdown from `IntegrationHealthChecker`, keyed by integration name.
 *
 * ## Why unauthenticated
 *
 * Same reasoning as `api.health`: an uptime monitor cannot present a credential a human hasn't
 * configured for it, so a health endpoint it can reach has to be public. What it discloses is
 * which third-party services this site integrates with and whether each is currently reachable
 * — not a credential (`IntegrationCallRecorder` fingerprints those before they ever reach
 * `rl_integration_calls`, which is what this reads).
 *
 * That said, "which vendors we use" is real information for Slack, ZeroBounce and Meta CAPI —
 * unlike PostHog or Customer.io's CDP key, none of those are otherwise visible to a visitor —
 * and `IntegrationHealth::toArray()` carries two fields this endpoint must not repeat
 * unfiltered: `reason` can name a real identity (`GoogleHealthCheck` names the signed-in Google
 * account), and `last_error` is a stored `error_message` that `IntegrationCallRecorder` never
 * redacts, unlike headers, bodies and URLs. `toPublicArray()` is the version safe to answer an
 * anonymous request with; the CLI and the dashboard widget use `toArray()` directly, because
 * both already require a credential of their own to reach.
 */
class IntegrationHealthController
{
    public function __construct(
        protected IntegrationHealthChecker $checker,
    ) {}

    public function index(): JsonResponse
    {
        $results = $this->checker->checkAll();

        $overall = array_reduce(
            $results,
            static fn (?HealthStatus $worst, $health) => $worst === null
                ? $health->status
                : HealthStatus::worstOf($worst, $health->status),
            null,
        ) ?? HealthStatus::Healthy;

        return response()->json([
            'status' => $overall->value,
            'checked_at' => now()->toAtomString(),
            'integrations' => array_map(static fn ($health) => $health->toPublicArray(), $results),
        ], $overall === HealthStatus::Down ? 503 : 200);
    }
}
