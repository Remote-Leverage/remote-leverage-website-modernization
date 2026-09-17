<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\ReferralLeadMatcher;
use Illuminate\Support\Facades\Log;

class HandleLeadBookingCompletedForReferrer
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
        protected ReferralLeadMatcher $matcher,
    ) {}

    /**
     * Handle completed lead booking: attribute to referrer if applicable, recording
     * a qualified Referral.
     *
     * Booking a call only qualifies the lead — it does not fulfill the referral and
     * does not earn a reward. Per legacy RL_Referral_Service::update_referral_status(),
     * a reward is only generated when the referral is later explicitly transitioned to
     * "fulfilled"/"rewarded" (the actual deal closing, via admin action or an
     * authenticated CRM webhook), which is a separate, human/ops-driven step. See
     * FulfillReferralAction.
     */
    public function handle(LeadBookingCompleted $event): void
    {
        $lead = $event->lead;

        if (! in_array($lead->source_type, ['referral_hub', 'partnership'], true) || empty($lead->source_id)) {
            // Lead not attributed to a referrer
            return;
        }

        try {
            // Find referrer by referral code or slug
            $referrer = Referrer::query()
                ->where('referral_code', $lead->source_id)
                ->first();

            $referrerName = $referrer ? $referrer->name : $lead->source_id;

            $referral = null;

            if ($referrer && strtolower($lead->email) !== strtolower($referrer->email)) {
                /*
                 * Resolve through the identity graph rather than updateOrCreate-ing on
                 * `lead_email`. That key could not see the two cases it most needed to: a
                 * phone-only direct submission (whose referral holds a blank email while the
                 * lead holds a synthesised internal one) and a prospect booking under a
                 * different address than they were referred under. Both missed, and the miss
                 * *inserted a duplicate referral* rather than failing visibly — so the
                 * referrer's dashboard showed two rows for one person and the reward attached
                 * to whichever the admin happened to fulfil.
                 */
                $referral = $this->matcher->findForLead($referrer, $lead);

                if ($referral) {
                    $this->matcher->link($referral, $lead);

                    /*
                     * Never demote a referral that has already closed. A prospect who books a
                     * second call after the deal is done would otherwise be walked back to
                     * `qualified`, and the admin screen would offer to fulfil — and reward —
                     * an already-rewarded referral a second time.
                     */
                    $referral->update(array_filter([
                        'lead_name' => $lead->name,
                        'lead_phone' => $lead->phone ?? '',
                        'landing_page' => $lead->landing_url,
                        'source' => 'booking_completed',
                        'status' => in_array($referral->status, Referral::FULFILLED_STATUSES, true)
                            ? null
                            : 'qualified',
                    ], static fn ($value) => $value !== null));
                } else {
                    $referral = Referral::query()->create([
                        'referrer_id' => $referrer->id,
                        'lead_id' => $lead->id,
                        'lead_email' => $lead->email,
                        'lead_name' => $lead->name,
                        'lead_phone' => $lead->phone ?? '',
                        'landing_page' => $lead->landing_url,
                        'source' => 'booking_completed',
                        'status' => 'qualified',
                    ]);
                }
            }

            // Dual logging Stage 2: consumption write
            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Referral',
                outcome: 'succeeded',
                description: "Matched booking to referrer '{$referrerName}', referral qualified [source: {$lead->source_type}:{$lead->source_id}]",
                payload: [
                    'referrer_id' => $referrer?->id,
                    'referral_id' => $referral?->id,
                    'lead_id' => $lead->id,
                    'referral_code' => $lead->source_id,
                    'source_type' => $lead->source_type,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadBookingCompletedForReferrer: Error attributing lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Referral',
                outcome: 'failed',
                description: 'Failed to record referrer attribution: '.$e->getMessage()
            );
        }
    }
}
