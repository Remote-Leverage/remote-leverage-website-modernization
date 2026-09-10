<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\ProcessAbandonedLeadsAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadAbandoned;
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
use App\Infrastructure\WordPress\Admin\LeadsAdminDashboard;
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

    test('ProcessAbandonedLeadsAction marks stale unbooked leads abandoned, dispatches LeadAbandoned, and logs dispatch', function () {
        $abandonedEvents = [];
        Event::listen(LeadAbandoned::class, function ($e) use (&$abandonedEvents) {
            $abandonedEvents[] = $e;
        });

        // Stale lead: captured 3 hours ago, never booked
        $staleLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stale Lead',
            'email' => 'stale@lead.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $staleLead->timestamps = false;
        $staleLead->created_at = Carbon::now()->subHours(3);
        $staleLead->updated_at = Carbon::now()->subHours(3);
        $staleLead->save();

        // Fresh lead: captured 30 minutes ago, still within the timeout window
        $freshLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Fresh Lead',
            'email' => 'fresh@lead.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $freshLead->timestamps = false;
        $freshLead->created_at = Carbon::now()->subMinutes(30);
        $freshLead->updated_at = Carbon::now()->subMinutes(30);
        $freshLead->save();

        // Stale but already booked: must be excluded regardless of age
        $bookedLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Booked Lead',
            'email' => 'booked@lead.com',
            'source_type' => 'organic',
            'status' => 'booked',
        ]);
        $bookedLead->timestamps = false;
        $bookedLead->created_at = Carbon::now()->subHours(5);
        $bookedLead->updated_at = Carbon::now()->subHours(5);
        $bookedLead->save();

        $action = new ProcessAbandonedLeadsAction(new LeadActivityLogger);
        $abandonedCount = $action->execute(2);

        expect($abandonedCount)->toBe(1);
        expect($staleLead->fresh()->status)->toBe('abandoned');
        expect($freshLead->fresh()->status)->toBe('captured');
        expect($bookedLead->fresh()->status)->toBe('booked');

        expect(count($abandonedEvents))->toBe(1);
        expect($abandonedEvents[0]->lead->id)->toBe($staleLead->id);

        $dispatchLog = LeadActivityLog::query()
            ->where('lead_id', $staleLead->id)
            ->where('stage', 'dispatch')
            ->where('event_type', 'LeadAbandoned')
            ->first();

        expect($dispatchLog)->not->toBeNull()
            ->and($dispatchLog->actor_domain)->toBe('Lead')
            ->and($dispatchLog->outcome)->toBe('succeeded');
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

    test('LeadsAdminDashboard smart search and KPI caching execute efficiently at scale', function () {
        Lead::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Enterprise',
            'email' => 'john@enterprise.io',
            'phone' => '+13055550199',
            'company' => 'Enterprise Global',
            'monthly_revenue' => '$50k to $100k Per Month',
            'status' => 'booked',
        ]);

        Lead::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Startup',
            'email' => 'sarah@startup.co',
            'phone' => '+14155550188',
            'company' => 'Startup AI',
            'monthly_revenue' => '$0 to $5k Per Month',
            'status' => 'partial',
        ]);

        $dashboard = new LeadsAdminDashboard;

        // Use reflection to access protected applyOptimizedSearch
        $reflection = new ReflectionClass($dashboard);
        $method = $reflection->getMethod('applyOptimizedSearch');
        $method->setAccessible(true);

        // 1. Test email search routing
        $emailQuery = Lead::query();
        $method->invoke($dashboard, $emailQuery, 'john@enterprise.io');
        expect($emailQuery->count())->toBe(1)
            ->and($emailQuery->first()->email)->toBe('john@enterprise.io');

        // 2. Test phone search routing
        $phoneQuery = Lead::query();
        $method->invoke($dashboard, $phoneQuery, '4155550188');
        expect($phoneQuery->count())->toBe(1)
            ->and($phoneQuery->first()->email)->toBe('sarah@startup.co');

        // 3. Test text search fallback
        $textQuery = Lead::query();
        $method->invoke($dashboard, $textQuery, 'Enterprise');
        expect($textQuery->count())->toBe(1)
            ->and($textQuery->first()->name)->toBe('John Enterprise');
    });

    test('findRecentBookingForSlot ignores dispatch-stage logs and only matches a real Scheduling consumption success for the same slot', function () {
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Priya Nair',
            'email' => 'priya@growthco.com',
            'status' => 'captured',
        ]);

        $bookedSlot = '2026-10-01T14:00:00+00:00';

        // Step 1 of the wizard: partial-capture dispatch. logDispatch() always
        // writes outcome=succeeded — that only means "the event fired," not
        // "a meeting was booked" — so this must NOT satisfy the guard.
        $activityLogger->logDispatch(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Lead',
        );

        expect($activityLogger->findRecentBookingForSlot('priya@growthco.com', $bookedSlot))->toBeNull();

        // The real booking submission a few seconds later: Scheduling's
        // consumption-stage log is the only thing that should match.
        $activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Scheduling',
            outcome: 'succeeded',
            description: 'Scheduled consultation meeting (calendly: cal_123)',
            payload: ['meeting_id' => 'cal_123', 'provider' => 'calendly', 'meet_url' => 'https://calendly.com/x', 'start_time' => $bookedSlot],
        );

        $match = $activityLogger->findRecentBookingForSlot('priya@growthco.com', $bookedSlot);
        expect($match)->not->toBeNull()
            ->and($match->payload['meeting_id'])->toBe('cal_123')
            ->and($activityLogger->findRecentBookingForSlot('nobody-else@growthco.com', $bookedSlot))->toBeNull();

        // A different slot is a fresh booking request, not a duplicate — must
        // NOT match, so BookMeetingAction falls through to a real Calendly call.
        expect($activityLogger->findRecentBookingForSlot('priya@growthco.com', '2026-10-02T09:00:00+00:00'))->toBeNull();
    });

    test('findPriorBookingForDifferentSlot finds a real prior Calendly booking to cancel, ignoring dedup placeholders and non-Calendly bookings', function () {
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Marcus Webb',
            'email' => 'marcus@scaleup.io',
            'status' => 'captured',
        ]);

        $originalSlot = '2026-10-01T14:00:00+00:00';
        $newSlot = '2026-10-03T16:00:00+00:00';

        // Original real booking.
        $activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Scheduling',
            outcome: 'succeeded',
            payload: ['meeting_id' => 'cal_original', 'provider' => 'calendly', 'meet_url' => 'https://calendly.com/x', 'start_time' => $originalSlot],
        );

        $match = $activityLogger->findPriorBookingForDifferentSlot('marcus@scaleup.io', $newSlot);
        expect($match)->not->toBeNull()
            ->and($match->payload['meeting_id'])->toBe('cal_original');

        // Same slot as the "new" one being booked — nothing to cancel.
        expect($activityLogger->findPriorBookingForDifferentSlot('marcus@scaleup.io', $originalSlot))->toBeNull();
    });
});
