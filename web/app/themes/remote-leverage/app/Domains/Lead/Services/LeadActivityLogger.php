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

    /**
     * Duplicate-booking guard: has this email already had a successful booking
     * logged within the window? Scoped by email (not lead_id) because a
     * double-submit before any lead_id context exists produces two separate Lead
     * rows for the same person — a lead_id-scoped check would never catch that.
     */
    public function hasRecentSuccessfulBooking(string $email, int $withinMinutes = 5): bool
    {
        return LeadActivityLog::query()
            ->where('event_type', 'LeadCreated')
            ->where('outcome', 'succeeded')
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->whereHas('lead', fn ($query) => $query->where('email', $email))
            ->exists();
    }
}
