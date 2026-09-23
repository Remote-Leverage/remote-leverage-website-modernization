<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Observability\Health\HealthStatus;
use App\Infrastructure\Observability\Health\IntegrationHealthChecker;
use Illuminate\Console\Command;

/**
 * `wp acorn integrations:health` — the CLI surface for the same check the diagnostics page
 * renders, so a deploy script or a cron alert can act on it without scraping HTML.
 */
class IntegrationsHealthCommand extends Command
{
    protected $signature = 'integrations:health {--as-json : Output machine-readable JSON}';

    protected $description = 'Report healthy / degraded / down for every configured integration';

    public function handle(IntegrationHealthChecker $checker): int
    {
        $results = $checker->checkAll();

        if ($this->option('as-json')) {
            $this->line((string) json_encode(
                array_map(fn ($result) => $result->toArray(), $results),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->table(
                ['Integration', 'Status', 'Reason', 'Sample', 'Failure Rate', 'Last Call'],
                array_map(fn ($result) => [
                    $result->label,
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
