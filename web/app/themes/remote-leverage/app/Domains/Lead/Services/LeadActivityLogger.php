<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\LeadActivityLog;
use Illuminate\Database\Eloquent\Collection;
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
     * Duplicate-booking guard: find a genuine prior booking success for this
     * email, for this EXACT slot, within the window. Scoped by email (not
     * lead_id) because a double-submit before any lead_id context exists
     * produces two separate Lead rows for the same person — a lead_id-scoped
     * check would never catch that.
     *
     * Only matches the same start_time. A different time is a legitimate new
     * booking (or a "I changed my mind, book me a different slot" resubmit),
     * not a duplicate — the caller must let that fall through to a real
     * Calendly call rather than short-circuiting.
     *
     * Must be scoped to actor_domain=Scheduling + stage=consumption: every
     * LeadCreated *dispatch* (including the step-1 partial-capture dispatch that
     * fires seconds before the real booking submission) is unconditionally
     * logged with outcome=succeeded by logDispatch() — that only means "the
     * event was fired," not "a meeting was booked." Matching on event_type +
     * outcome alone (without the actor/stage filters) treated that harmless
     * dispatch as a prior successful booking, so BookMeetingAction short-
     * circuited into the fake 'deduplicated' response on nearly every real
     * two-step submission instead of ever calling Calendly.
     */
    public function findRecentBookingForSlot(string $email, string $startTimeIso, int $withinMinutes = 5): ?LeadActivityLog
    {
        $targetTimestamp = strtotime($startTimeIso);

        return $this->recentSchedulingSuccesses($email, $withinMinutes)
            ->first(function (LeadActivityLog $log) use ($targetTimestamp) {
                $loggedStart = $log->payload['start_time'] ?? null;

                return $loggedStart && strtotime($loggedStart) === $targetTimestamp;
            });
    }

    /**
     * Bonus reschedule support: the most recent prior real Calendly booking
     * for this email at a DIFFERENT slot than the one just booked, so it can
     * be canceled. Ignores the fake 'deduplicated' placeholder, bookings with
     * no real meeting_id, and non-Calendly (Google Calendar fallback)
     * bookings — nothing cancelable there. Not time-windowed like the dedup
     * guard above: a reschedule of a booking made hours ago should still be
     * caught, not just one from the last few minutes.
     */
    public function findPriorBookingForDifferentSlot(string $email, string $newStartTimeIso): ?LeadActivityLog
    {
        $newTimestamp = strtotime($newStartTimeIso);

        return $this->recentSchedulingSuccesses($email, null)
            ->first(function (LeadActivityLog $log) use ($newTimestamp) {
                $payload = $log->payload ?? [];
                $loggedStart = $payload['start_time'] ?? null;
                $meetingId = $payload['meeting_id'] ?? null;

                if (! $loggedStart || ! $meetingId || $meetingId === 'deduplicated') {
                    return false;
                }

                if (($payload['provider'] ?? null) !== 'calendly') {
                    return false;
                }

                return strtotime($loggedStart) !== $newTimestamp;
            });
    }

    /**
     * @return Collection<int, LeadActivityLog>
     */
    protected function recentSchedulingSuccesses(string $email, ?int $withinMinutes)
    {
        return LeadActivityLog::query()
            ->where('event_type', 'LeadCreated')
            ->where('actor_domain', 'Scheduling')
            ->where('stage', 'consumption')
            ->where('outcome', 'succeeded')
            ->when($withinMinutes !== null, fn ($query) => $query->where('created_at', '>=', now()->subMinutes($withinMinutes)))
            ->whereHas('lead', fn ($query) => $query->where('email', $email))
            ->latest('created_at')
            ->get();
    }
}
