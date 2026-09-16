<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Observability\IntegrationCall;
use Illuminate\Console\Command;

/**
 * Delete integration call records past their retention window.
 *
 * These rows hold full request and response bodies, which for a lead sync means a complete copy
 * of that person's contact details. They exist to diagnose a problem in the days after it
 * happens, and keeping them beyond that turns a debugging aid into a second, unmanaged store of
 * personal data that nobody remembers to include in a deletion request.
 */
class PruneIntegrationCallsCommand extends Command
{
    protected $signature = 'rl:prune-integration-calls
                            {--days= : Override the configured retention window}
                            {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete integration call logs older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('observability.integration_calls.retention_days', 30));

        if ($days < 1) {
            $this->error('Retention must be at least one day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = IntegrationCall::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} integration call(s) older than {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        /*
         * Chunked. A single unbounded DELETE over a table this wide holds a long lock, and the
         * prune runs on a schedule against a live site.
         */
        $deleted = 0;

        do {
            $batch = IntegrationCall::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} integration call(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
