<?php

declare(strict_types=1);

namespace App\Domains\Lead\Commands;

use App\Domains\Lead\Actions\ProcessAbandonedLeadsAction;
use Illuminate\Console\Command;

class ProcessAbandonedLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lead:process-abandoned {--hours=2 : Abandonment timeout threshold in hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transition leads with no completed booking within the timeout window to "abandoned" and dispatch LeadAbandoned (ADR-0008)';

    public function handle(ProcessAbandonedLeadsAction $action): int
    {
        $hours = (int) $this->option('hours');

        $this->info("Evaluating leads inactive for more than {$hours}h...");
        $abandonedCount = $action->execute($hours);

        $this->info("Processed: {$abandonedCount} lead(s) marked abandoned.");

        return self::SUCCESS;
    }
}
