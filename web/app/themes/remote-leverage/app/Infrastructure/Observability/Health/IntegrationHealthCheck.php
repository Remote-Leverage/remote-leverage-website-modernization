<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health;

/**
 * Identifies one integration to the health checker and says whether it has credentials.
 *
 * Deliberately thin. Working out *whether traffic to it is healthy* is one piece of logic —
 * `IntegrationHealthChecker`, reading `rl_integration_calls` — shared by every integration
 * rather than reimplemented per class; an implementation only has to know its own name and
 * how to answer "is there a credential for this at all", reusing whatever precedence its
 * gateway already resolves that from (env, then the admin-configured Lead setting).
 */
interface IntegrationHealthCheck
{
    /**
     * Matches the value `IntegrationCallRecorder::classify()` writes into
     * `rl_integration_calls.integration` — see `config('observability.integration_calls.hosts')`.
     */
    public function integration(): string;

    /**
     * Human label for the diagnostics page and CLI output.
     */
    public function label(): string;

    /**
     * Whether a credential is present. Not whether it still works — that is what the recent
     * call history answers, and a check here must never make a network call itself: it runs
     * on every diagnostics page load and every `wp acorn integrations:health`.
     */
    public function isConfigured(): bool;
}
