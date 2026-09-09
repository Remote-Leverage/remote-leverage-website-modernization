<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Events\LeadAbandoned;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

class ProcessAbandonedLeadsAction
{
    public const DEFAULT_TIMEOUT_HOURS = 2;

    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Transition leads that never completed booking within the timeout window to "abandoned"
     * and dispatch LeadAbandoned for each, per ADR-0008.
     *
     * @return int Number of leads marked abandoned
     */
    public function execute(int $timeoutHours = self::DEFAULT_TIMEOUT_HOURS): int
    {
        $cutoff = Carbon::now()->subHours($timeoutHours);

        $leads = Lead::query()
            ->whereIn('status', ['captured', 'booking_pending'])
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($leads as $lead) {
            $previousStatus = $lead->status;
            $lead->update(['status' => 'abandoned']);

            $this->activityLogger->logDispatch(
                leadId: $lead->id,
                eventType: 'LeadAbandoned',
                actorDomain: 'Lead',
                payload: [
                    'previous_status' => $previousStatus,
                    'timeout_hours' => $timeoutHours,
                    'created_at' => $lead->created_at?->toDateTimeString(),
                ],
                description: "Lead #{$lead->id} marked abandoned after {$timeoutHours}h with no completed booking"
            );

            Event::dispatch(new LeadAbandoned(
                lead: $lead,
                metadata: ['timeout_hours' => $timeoutHours, 'previous_status' => $previousStatus]
            ));
        }

        return $leads->count();
    }
}
