<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Events\LeadFormSubmitted;
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
});
