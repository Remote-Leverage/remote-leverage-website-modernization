<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Health;

/**
 * A health check that computes its own verdict instead of being classified from
 * `rl_integration_calls`.
 *
 * Every other check in `Checks/` describes a third-party API, and `IntegrationHealthChecker`
 * judges those from real recent traffic in that table — see its docblock for why. The database
 * cannot be judged that way: there is no outbound HTTP call to log, and if the database is the
 * thing that is down, a health check that starts by querying a database table has already
 * failed for the wrong reason. `probe()` runs its own live check instead — for the database,
 * a bare `select 1` timed for latency.
 */
interface SelfEvaluatingHealthCheck extends IntegrationHealthCheck
{
    public function probe(): IntegrationHealth;
}
