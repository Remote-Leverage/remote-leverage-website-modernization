<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Observability\Health\HealthStatus;
use App\Infrastructure\Observability\Health\IntegrationHealthChecker;
use Illuminate\Console\Command;

/**
 * `wp acorn integrations:health` — the CLI surface for the same check the diagnostics page
 * renders, so a deploy script or a cron alert can act on it without scraping HTML.
 *
 * Shows each integration's `alias`, not its vendor `label` — this output is routinely pasted
 * into a deploy log or a chat channel less trusted than the terminal it ran in, and "which
 * third-party services this site integrates with" is exactly what `alias()` exists to not hand
 * out for free. The wp-admin dashboard widget is the one place that pairs the alias with the
 * real vendor name, because that surface already requires being logged in to reach.
 */
class IntegrationsHealthCommand extends Command
{
    protected $signature = 'integrations:health {--as-json : Output machine-readable JSON}';

    protected $description = 'Report healthy / degraded / down for every configured integration';

    public function handle(IntegrationHealthChecker $checker): int
    {
        $results = $checker->checkAll();

        if ($this->option('as-json')) {
            // Re-keyed by alias, not array_map()'d in place — $results is keyed by the real
            // integration slug, and preserving that as the JSON object's keys would hand back
            // exactly the vendor name toPublicArray() withholds from the values.
            $byAlias = [];

            foreach ($results as $result) {
                $byAlias[$result->alias] = $result->toPublicArray();
            }

            $this->line((string) json_encode($byAlias, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Integration', 'Status', 'Reason', 'Sample', 'Failure Rate', 'Last Call'],
                array_map(fn ($result) => [
                    $result->alias,
                    strtoupper($result->status->value),
                    $result->reason,
                    $result->sampleSize,
                    $result->failureRate === null ? '—' : round($result->failureRate * 100).'%',
                    $result->lastCallAt?->format('Y-m-d H:i:s') ?? '—',
                ], $results),
            );
        }

        $worstDown = collect($results)->contains(fn ($result) => $result->status === HealthStatus::Down);

        return $worstDown ? self::FAILURE : self::SUCCESS;
    }
}
