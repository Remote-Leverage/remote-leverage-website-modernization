<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\LeadActivityLog;
use Illuminate\Support\Facades\Log;

class LeadActivityLogger
{
    /**
     * Record an event dispatch in the lead activity log (Stage 1 of dual-logging).
     */
    public function logDispatch(
        int $leadId,
        string $eventType,
        string $actorDomain,
        array $payload = [],
        ?string $description = null
    ): ?LeadActivityLog {
        try {
            return LeadActivityLog::query()->create([
                'lead_id' => $leadId,
                'event_type' => $eventType,
                'actor_domain' => $actorDomain,
                'stage' => 'dispatch',
                'outcome' => 'succeeded',
                'description' => $description ?? "Dispatched {$eventType} from {$actorDomain}",
                'payload' => $payload,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to write dispatch log to LeadActivityLog for lead #{$leadId}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Record an event consumption in the lead activity log (Stage 2 of dual-logging).
     */
    public function logConsumption(
        int $leadId,
        string $eventType,
        string $actorDomain,
        string $outcome = 'succeeded',
        ?string $description = null,
        array $payload = []
    ): ?LeadActivityLog {
        try {
            return LeadActivityLog::query()->create([
                'lead_id' => $leadId,
                'event_type' => $eventType,
                'actor_domain' => $actorDomain,
                'stage' => 'consumption',
                'outcome' => in_array($outcome, ['succeeded', 'failed', 'skipped'], true) ? $outcome : 'succeeded',
                'description' => $description ?? "Handled {$eventType} in {$actorDomain} ({$outcome})",
                'payload' => $payload,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to write consumption log to LeadActivityLog for lead #{$leadId}: ".$e->getMessage());

            return null;
        }
    }
}
