<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Models\BouncedLead;
use App\Domains\Lead\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PurgeOldLeadsAction
{
    public const MINIMUM_RETENTION_DAYS = 30;

    /**
     * Purge leads older than the configured retention window (minimum 30 days).
     *
     * @param  int|null  $customRetentionDays  Optional custom days (cannot be less than MINIMUM_RETENTION_DAYS)
     * @return int Number of leads purged
     */
    public function execute(?int $customRetentionDays = null): int
    {
        $configuredDays = function_exists('get_option')
            ? (int) get_option('rl_lead_retention_days', self::MINIMUM_RETENTION_DAYS)
            : self::MINIMUM_RETENTION_DAYS;

        $targetDays = max(
            self::MINIMUM_RETENTION_DAYS,
            $customRetentionDays ?? $configuredDays
        );

        $cutoffDate = Carbon::now()->subDays($targetDays);

        $query = Lead::query()
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();
        if ($count > 0) {
            // Delete associated activity logs before purging lead
            $leadIds = $query->pluck('id')->toArray();
            $query->forceDelete();

            Log::info("PurgeOldLeadsAction: Successfully purged {$count} leads older than {$targetDays} days (cutoff: {$cutoffDate->toDateTimeString()})", [
                'purged_count' => $count,
                'retention_days' => $targetDays,
            ]);
        }

        /*
         * Bounced submissions are lead PII too — name, email and phone of somebody the form
         * refused — so they expire on the same window rather than accumulating forever behind
         * an admin screen nobody prunes.
         */
        $bounced = BouncedLead::query()->where('created_at', '<', $cutoffDate)->delete();

        if ($bounced > 0) {
            Log::info("PurgeOldLeadsAction: Purged {$bounced} bounced submissions older than {$targetDays} days.", [
                'purged_count' => $bounced,
                'retention_days' => $targetDays,
            ]);
        }

        Cache::forget(Lead::KPI_CACHE_KEY);

        return $count;
    }
}
