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
        $phoneCountry = null;
        if (! empty($data->phone)) {
            $validation = $this->phoneValidator->validateAndFormat($data->phone);
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
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        // 5. Persist the Lead entity
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => trim($data->name),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower(trim($data->email)),
            'phone' => $formattedPhone,
            'phone_country' => $phoneCountry,
            'company' => $data->company,
            'role_needed' => $data->roleNeeded,
            'weekly_hours' => $data->weeklyHours,
            'start_date' => $data->startDate,
            'notes' => $data->notes,
            'source_type' => $attribution['sourceType'],
            'source_id' => $attribution['sourceID'],
            'status' => ! empty($data->preferredSlot) ? 'booking_pending' : 'captured',
        ]);

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
