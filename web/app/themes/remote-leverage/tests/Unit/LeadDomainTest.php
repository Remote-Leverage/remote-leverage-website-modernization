<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Events\LeadFormSubmitted;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Listeners\HandleLeadEventsForWebhook;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

describe('Lead Domain', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();
    });

    test('PhoneValidationService validates and formats US and international numbers to E.164', function () {
        $service = new PhoneValidationService;

        // Valid US number
        $usResult = $service->validateAndFormat('(305) 555-0199', 'US');
        expect($usResult['isValid'])->toBeTrue()
            ->and($usResult['e164'])->toBe('+13055550199')
            ->and($usResult['countryCode'])->toBe('US');

        // Valid UK number
        $ukResult = $service->validateAndFormat('+44 20 7946 0991', 'GB');
        expect($ukResult['isValid'])->toBeTrue()
            ->and($ukResult['e164'])->toBe('+442079460991')
            ->and($ukResult['countryCode'])->toBe('GB');

        // Invalid number
        $invalidResult = $service->validateAndFormat('12345', 'US');
        expect($invalidResult['isValid'])->toBeFalse()
            ->and($invalidResult['error'])->not->toBeNull();
    });

    test('LeadCaptureData instantiates correctly and serializes to array', function () {
        $dto = LeadCaptureData::fromArray([
            'name' => 'Elena Rostova',
            'email' => 'elena@novacapital.com',
            'phone' => '+1 (305) 555-0188',
            'company' => 'Nova Capital',
            'role_needed' => 'Executive Assistant',
            'weekly_hours' => '40',
            'start_date' => 'Immediately',
            'referral_code' => 'apex-partner',
        ]);

        expect($dto->name)->toBe('Elena Rostova')
            ->and($dto->email)->toBe('elena@novacapital.com')
            ->and($dto->company)->toBe('Nova Capital')
            ->and($dto->referralCode)->toBe('apex-partner');

        $array = $dto->toArray();
        expect($array['email'])->toBe('elena@novacapital.com')
            ->and($array['role_needed'])->toBe('Executive Assistant');
    });

    test('CaptureLeadAction validates, stamps attribution, persists lead, logs dispatch, and fires LeadCreated', function () {
        $submittedEvents = [];
        $createdEvents = [];
        Event::listen(LeadFormSubmitted::class, function ($e) use (&$submittedEvents) {
            $submittedEvents[] = $e;
        });
        Event::listen(LeadCreated::class, function ($e) use (&$createdEvents) {
            $createdEvents[] = $e;
        });

        $attributionEngine = new AttributionEngine;
        $phoneValidator = new PhoneValidationService;
        $activityLogger = new LeadActivityLogger;

        $action = new CaptureLeadAction($attributionEngine, $phoneValidator, $activityLogger);

        $dto = LeadCaptureData::fromArray([
            'name' => 'Marcus Aurelius',
            'email' => 'marcus@rome.org',
            'phone' => '+1 305 555 0123',
            'company' => 'Imperial Operations',
            'role_needed' => 'Operations Manager',
            'weekly_hours' => '40',
            'start_date' => 'Immediately',
            'referral_code' => 'partner-capital',
            'preferred_slot' => '2026-09-15T15:00:00Z',
        ]);

        $lead = $action->execute($dto);

        expect($lead->exists)->toBeTrue()
            ->and($lead->email)->toBe('marcus@rome.org')
            ->and($lead->first_name)->toBe('Marcus')
            ->and($lead->last_name)->toBe('Aurelius')
            ->and($lead->source_type)->toBe('partnership')
            ->and($lead->source_id)->toBe('partner-capital')
            ->and($lead->status)->toBe('booking_pending');

        // Check dual logging Stage 1 (dispatch)
        $dispatchLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('stage', 'dispatch')
            ->where('event_type', 'LeadCreated')
            ->first();

        expect($dispatchLog)->not->toBeNull()
            ->and($dispatchLog->actor_domain)->toBe('Lead')
            ->and($dispatchLog->outcome)->toBe('succeeded');

        expect(count($submittedEvents))->toBe(1);
        expect(count($createdEvents))->toBe(1);
        expect($createdEvents[0]->lead->id)->toBe($lead->id);
        expect($createdEvents[0]->context['preferred_slot'])->toBe('2026-09-15T15:00:00Z');
    });

    test('PurgeOldLeadsAction enforces 30-day minimum retention floor', function () {
        // Create an old lead (40 days old)
        $oldLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old Lead',
            'email' => 'old@archive.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $oldLead->timestamps = false;
        $oldLead->created_at = Carbon::now()->subDays(40);
        $oldLead->updated_at = Carbon::now()->subDays(40);
        $oldLead->save();

        // Create a recent lead (10 days old)
        $recentLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Recent Lead',
            'email' => 'recent@current.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $recentLead->timestamps = false;
        $recentLead->created_at = Carbon::now()->subDays(10);
        $recentLead->updated_at = Carbon::now()->subDays(10);
        $recentLead->save();

        $purgeAction = new PurgeOldLeadsAction;

        // Execute purge with 30-day default window
        $purgedCount = $purgeAction->execute();

        expect($purgedCount)->toBe(1);
        expect(Lead::query()->find($oldLead->id))->toBeNull();
        expect(Lead::query()->find($recentLead->id))->not->toBeNull();
    });

    test('CaptureLeadAction updates existing lead on final submission without duplicating rows', function () {
        $attributionEngine = new AttributionEngine;
        $phoneValidator = new PhoneValidationService;
        $activityLogger = new LeadActivityLogger;
        $action = new CaptureLeadAction($attributionEngine, $phoneValidator, $activityLogger);

        // 1. Partial Step 1 submission
        $step1Dto = LeadCaptureData::fromArray([
            'name' => 'Sarah Jenkins',
            'first_name' => 'Sarah',
            'last_name' => 'Jenkins',
            'email' => 'sarah@growthco.io',
            'phone' => '+1 (305) 555-0199',
            'monthly_revenue' => '$10k to $50k Per Month',
            'extra_data' => [
                'source_form' => 'MultistepBookingWizard',
                'submission_type' => 'Partial',
            ],
        ]);

        $partialLead = $action->execute($step1Dto);
        expect(Lead::query()->count())->toBe(1)
            ->and($partialLead->status)->toBe('captured');

        // 2. Final Step 4 booking submission using the existing lead ID
        $step4Dto = LeadCaptureData::fromArray([
            'name' => 'Sarah Jenkins',
            'first_name' => 'Sarah',
            'last_name' => 'Jenkins',
            'email' => 'sarah@growthco.io',
            'phone' => '+1 (305) 555-0199',
            'monthly_revenue' => '$10k to $50k Per Month',
            'preferred_slot' => '2026-09-15T14:00:00Z',
            'notes' => 'Looking to hire an executive assistant.',
            'extra_data' => [
                'source_form' => 'MultistepBookingWizard',
                'lead_id' => $partialLead->id,
            ],
        ]);

        $finalLead = $action->execute($step4Dto);

        // Verify no duplicate row was created; existing row updated
        expect(Lead::query()->count())->toBe(1)
            ->and($finalLead->id)->toBe($partialLead->id)
            ->and($finalLead->status)->toBe('booking_pending')
            ->and($finalLead->notes)->toBe('Looking to hire an executive assistant.');
    });

    test('HandleLeadEventsForSlack dispatches and logs consumption for partial and final events', function () {
        config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test/123']);
        $activityLogger = new LeadActivityLogger;
        $listener = new HandleLeadEventsForSlack($activityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Doe',
            'email' => 'john@startup.com',
            'monthly_revenue' => '$50k-$100k Per Month',
            'source_type' => 'ad',
            'source_id' => 'google',
            'status' => 'captured',
        ]);

        // Partial capture event
        $listener->handleCreated(new LeadCreated($lead, ['preferred_slot' => null]));

        $partialLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Slack')
            ->where('event_type', 'LeadCreated')
            ->first();

        expect($partialLog)->not->toBeNull()
            ->and($partialLog->stage)->toBe('consumption');

        // Final booking event
        $lead->status = 'booked';
        $lead->save();

        $bookingCompleted = new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'meet-1234',
            provider: 'calendly',
            meetUrl: 'https://meet.google.com/abc-def-ghi',
            startTime: '2026-09-15T15:00:00Z'
        );

        $listener->handleBookingCompleted($bookingCompleted);

        $finalLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Slack')
            ->where('event_type', 'LeadBookingCompleted')
            ->first();

        expect($finalLog)->not->toBeNull()
            ->and($finalLog->outcome)->toBe('succeeded');
    });

    test('HandleLeadEventsForWebhook dispatches and logs consumption for partial and final events', function () {
        config(['services.webhooks.lead_webhook_url' => 'https://api.example.com/webhooks/leads']);
        $activityLogger = new LeadActivityLogger;
        $listener = new HandleLeadEventsForWebhook($activityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Alice Smith',
            'email' => 'alice@company.com',
            'monthly_revenue' => '$100k+ Per Month',
            'source_type' => 'partnership',
            'source_id' => 'tech-partner',
            'status' => 'captured',
        ]);

        // Partial capture event
        $listener->handleCreated(new LeadCreated($lead, ['preferred_slot' => null]));

        $partialLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'OutgoingWebhook')
            ->where('event_type', 'LeadCreated')
            ->first();

        expect($partialLog)->not->toBeNull()
            ->and($partialLog->stage)->toBe('consumption');

        // Final booking completed event
        $bookingCompleted = new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'meet-5678',
            provider: 'calendly',
            meetUrl: 'https://meet.google.com/xyz-123',
            startTime: '2026-09-16T16:00:00Z'
        );

        $listener->handleBookingCompleted($bookingCompleted);

        $finalLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'OutgoingWebhook')
            ->where('event_type', 'LeadBookingCompleted')
            ->first();

        expect($finalLog)->not->toBeNull()
            ->and($finalLog->outcome)->toBe('succeeded');
    });
});
