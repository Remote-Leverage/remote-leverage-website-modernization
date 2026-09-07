<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Events\LeadFormSubmitted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class CaptureLeadAction
{
    public function __construct(
        protected AttributionEngine $attributionEngine,
        protected PhoneValidationService $phoneValidator,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Capture, validate, attribute, and persist a new prospective lead.
     */
    public function execute(LeadCaptureData $data): Lead
    {
        // 1. Extension point: dispatch LeadFormSubmitted before persistence
        Event::dispatch(new LeadFormSubmitted($data));

        // 2. Validate & format phone number if provided
        $formattedPhone = null;
        $phoneCountry = $data->phoneCountry;
        if (! empty($data->phone)) {
            $validation = $this->phoneValidator->validateAndFormat($data->phone, $data->phoneCountry ?? 'US');
            if ($validation['isValid']) {
                $formattedPhone = $validation['e164'];
                $phoneCountry = $validation['countryCode'];
            } else {
                // Fallback to cleaned raw string if parsing fails
                $formattedPhone = trim($data->phone);
            }
        }

        $cookie = function_exists('request') && request() ? request()->cookie('rl_referrer') : ($_COOKIE['rl_referrer'] ?? null);

        $attribution = $this->attributionEngine->resolveLeadSource(
            referralSlug: $data->referralCode,
            utmSource: $data->utmSource,
            utmCampaign: $data->utmCampaign,
            cookie: $cookie
        );

        // 4. Extract first and last name
        $nameParts = explode(' ', trim($data->name), 2);
        $firstName = $data->firstName ?: ($nameParts[0] ?? '');
        $lastName = $data->lastName ?: ($nameParts[1] ?? '');

        // 5. Persist or update the Lead entity
        $lead = null;
        $leadId = $data->extraData['lead_id'] ?? null;
        if (! empty($leadId)) {
            $lead = Lead::query()->find($leadId);
        }

        $leadAttributes = [
            'name' => trim($data->name),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower(trim($data->email)),
            'phone' => $formattedPhone ?: $lead?->phone,
            'phone_country' => $phoneCountry ?: $lead?->phone_country,
            'company' => $data->company ?: $lead?->company,
            'role_needed' => $data->roleNeeded ?: $lead?->role_needed,
            'weekly_hours' => $data->weeklyHours ?: $lead?->weekly_hours,
            'monthly_revenue' => $data->monthlyRevenue ?: $lead?->monthly_revenue,
            'start_date' => $data->startDate ?: $lead?->start_date,
            'notes' => $data->notes ?: $lead?->notes,
            'utm_source' => $data->utmSource ?: $lead?->utm_source,
            'utm_medium' => $data->utmMedium ?: $lead?->utm_medium,
            'utm_campaign' => $data->utmCampaign ?: $lead?->utm_campaign,
            'utm_term' => $data->utmTerm ?: $lead?->utm_term,
            'utm_content' => $data->utmContent ?: $lead?->utm_content,
            'gclid' => $data->gclid ?: $lead?->gclid,
            'fbclid' => $data->fbclid ?: $lead?->fbclid,
            'referral_code' => $attribution['sourceID'] ?? ($data->referralCode ?: $lead?->referral_code),
            'landing_url' => $data->landingUrl ?: $lead?->landing_url,
            'referrer_url' => $data->referrerUrl ?: $lead?->referrer_url,
            'session_id' => $data->sessionId ?: $lead?->session_id,
            'source_type' => $attribution['sourceType'] ?: ($lead?->source_type ?? 'organic'),
            'source_id' => $attribution['sourceID'] ?: $lead?->source_id,
            'status' => ! empty($data->preferredSlot) ? 'booking_pending' : ($lead?->status ?? 'captured'),
        ];

        if ($lead) {
            $lead->update($leadAttributes);
        } else {
            $lead = Lead::query()->create(array_merge([
                'uuid' => (string) Str::uuid(),
            ], $leadAttributes));
        }

        // 6. Dual Logging: Stage 1 (Dispatch log for LeadCreated)
        $this->activityLogger->logDispatch(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Lead',
            payload: [
                'email' => $lead->email,
                'source_type' => $lead->source_type,
                'source_id' => $lead->source_id,
                'preferred_slot' => $data->preferredSlot,
                'monthly_revenue' => $lead->monthly_revenue,
                'utm_source' => $lead->utm_source,
                'utm_campaign' => $lead->utm_campaign,
                'gclid' => $lead->gclid,
                'fbclid' => $lead->fbclid,
                'session_id' => $lead->session_id,
                'landing_url' => $lead->landing_url,
            ],
            description: "Lead #{$lead->id} captured and stamped with source [{$lead->source_type}:{$lead->source_id}]"
        );

        // 7. Dispatch LeadCreated lifecycle event
        Event::dispatch(new LeadCreated(
            lead: $lead,
            context: [
                'preferred_slot' => $data->preferredSlot,
                'timezone' => $data->timezone ?? 'America/New_York',
                'extra_data' => $data->extraData,
            ]
        ));

        return $lead;
    }
}
