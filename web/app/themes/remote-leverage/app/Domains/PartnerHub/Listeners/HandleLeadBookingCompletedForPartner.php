<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Models\Partner;
use Illuminate\Support\Facades\Log;

class HandleLeadBookingCompletedForPartner
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle completed lead booking: attribute to strategic partner if applicable.
     */
    public function handle(LeadBookingCompleted $event): void
    {
        $lead = $event->lead;

        if (! in_array($lead->source_type, ['referral_hub', 'partnership'], true) || empty($lead->source_id)) {
            // Lead not attributed to partner
            return;
        }

        try {
            // Find partner by referral code or slug
            $partner = Partner::query()
                ->where('referral_code', $lead->source_id)
                ->first();

            $partnerName = $partner ? $partner->name : $lead->source_id;

            // Dual logging Stage 2: consumption write
            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'PartnerHub',
                outcome: 'succeeded',
                description: "Matched booking to partner '{$partnerName}' [source: {$lead->source_type}:{$lead->source_id}]",
                payload: [
                    'partner_id' => $partner?->id,
                    'referral_code' => $lead->source_id,
                    'source_type' => $lead->source_type,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadBookingCompletedForPartner: Error attributing lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'PartnerHub',
                outcome: 'failed',
                description: 'Failed to record partner attribution: '.$e->getMessage()
            );
        }
    }
}
