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
 * failure-rate blip is how people learn to ignore the monitor. `integrations` carries one entry
 * per check, keyed by `alias` — never by the real `integration` slug `IntegrationHealthChecker`
 * itself uses internally, which would hand back exactly the vendor name `toPublicArray()`
 * already withholds from the values, just moved into the keys instead.
 *
 * ## Why unauthenticated
 *
 * Same reasoning as `api.health`: an uptime monitor cannot present a credential a human hasn't
 * configured for it, so a health endpoint it can reach has to be public. Which third-party
 * services this site integrates with is real information — Slack, ZeroBounce and Meta CAPI are
 * not otherwise visible to a visitor the way PostHog or Customer.io's CDP key are — so this
 * route never repeats the real vendor name at all: `IntegrationHealth::alias()` is what an
 * anonymous caller sees, `label()` (the real name, "HubSpot") is reserved for the CLI and the
 * wp-admin dashboard widget, which already require a credential of their own to reach. `reason`
 * and `last_error` are withheld for the same reason on top of that — see `toPublicArray()`.
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

        $integrations = [];

        foreach ($results as $health) {
            $integrations[$health->alias] = $health->toPublicArray();
        }

        return response()->json([
            'status' => $overall->value,
            'checked_at' => now()->toAtomString(),
            'integrations' => $integrations,
        ], $overall === HealthStatus::Down ? 503 : 200);
    }
}
