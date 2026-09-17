<?php

declare(strict_types=1);

namespace App\Domains\Referral\Commands;

use App\Domains\Referral\Actions\SyncHubSpotLifecycleAction;
use Illuminate\Console\Command;

class SyncHubSpotLifecycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referral:sync-hubspot-lifecycle
                            {--limit= : Maximum leads to check this run (default '.SyncHubSpotLifecycleAction::DEFAULT_RUN_LIMIT.')}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mirror HubSpot contact lifecycle stages onto referred leads, fulfilling referrals whose deal has closed';

    public function handle(SyncHubSpotLifecycleAction $action): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $summary = $action->execute($limit);

        $this->info(sprintf(
            'Checked %d lead(s): %d stage change(s), %d referral(s) fulfilled.',
            $summary['checked'],
            $summary['changed'],
            $summary['fulfilled'],
        ));

        /*
         * Surfaced rather than folded into the success line. An id HubSpot would not return is
         * a contact that was deleted or merged away, and the lead attached to it will sit on
         * the dashboard showing its last known stage forever — silent, and indistinguishable
         * from a deal that simply has not moved.
         */
        if ($summary['unresolved'] > 0) {
            $this->warn(sprintf(
                '%d lead(s) had a HubSpot contact id that returned no record — deleted or merged in the portal.',
                $summary['unresolved'],
            ));
        }

        return self::SUCCESS;
    }
}
