<?php

declare(strict_types=1);

namespace App\Domains\Lead\Commands;

use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use Illuminate\Console\Command;

class PurgeLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lead:purge {--days= : Optional custom retention days (must be >= 30)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge leads older than the configured retention policy (minimum 30 days floor per ADR-0008)';

    public function handle(PurgeOldLeadsAction $action): int
    {
        $days = $this->option('days') ? (int) $this->option('days') : null;

        if ($days !== null && $days < PurgeOldLeadsAction::MINIMUM_RETENTION_DAYS) {
            $this->error('Retention policy violation: leads cannot be purged younger than the 30-day minimum floor.');

            return self::FAILURE;
        }

        $this->info('Evaluating leads for retention purge...');
        $purgedCount = $action->execute($days);

        $this->info("Purge completed: {$purgedCount} expired leads purged.");

        return self::SUCCESS;
    }
}
