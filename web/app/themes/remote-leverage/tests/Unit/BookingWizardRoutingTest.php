<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use App\Domains\Scheduling\Actions\BookMeetingAction;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Listeners\HandleLeadCreatedForBooking;
use Illuminate\Support\Str;

describe('Booking Wizard MRR Routing & Tracking Parity', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();

        config([
            'services.calendly.default_event_type' => 'https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb',
            'services.calendly.t10_event_type' => 'https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb',
            'services.calendly.t0_event_type' => 'https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62',
        ]);
    });

    test('Wizard routes to T10 (A) for MRR >= $10k', function () {
        $wizard = new MultistepBookingWizard;

        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        expect($wizard->isUnder10kMrr())->toBeFalse()
            ->and($wizard->getActiveEventTypeUri())->toBe('https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb');

        $wizard->monthlyRevenue = '$50k-$100k Per Month';
        expect($wizard->isUnder10kMrr())->toBeFalse()
            ->and($wizard->getActiveEventTypeUri())->toBe('https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb');

        $wizard->monthlyRevenue = '$100k+ Per Month';
        expect($wizard->isUnder10kMrr())->toBeFalse()
            ->and($wizard->getActiveEventTypeUri())->toBe('https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb');
    });

    test('Wizard routes to T0 for MRR < $10k', function () {
        $wizard = new MultistepBookingWizard;

        $wizard->monthlyRevenue = '$0 to $5k Per Month';
        expect($wizard->isUnder10kMrr())->toBeTrue()
            ->and($wizard->getActiveEventTypeUri())->toBe('https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62');

        $wizard->monthlyRevenue = '$5k to $10k Per Month';
        expect($wizard->isUnder10kMrr())->toBeTrue()
            ->and($wizard->getActiveEventTypeUri())->toBe('https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62');
    });

    test('Job seeker selection skips calendar flow', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->monthlyRevenue = 'Looking for a job? Click Here';
        $wizard->updatedMonthlyRevenue();

        expect($wizard->isJobSeeker())->toBeTrue()
            ->and($wizard->skipCalendar)->toBeTrue();
    });

    test('Wizard supports split firstName, lastName, and phoneCountry', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->email = 'sarah@company.com';
        $wizard->firstName = 'Sarah';
        $wizard->lastName = 'Jenkins';
        $wizard->phone = '+1 305 555 0199';
        $wizard->phoneCountry = 'US';
        $wizard->monthlyRevenue = '$10k to $50k Per Month';

        $wizard->goToStep(2);

        expect($wizard->name)->toBe('Sarah Jenkins')
            ->and($wizard->currentStep)->toBe(2);
    });

    test('CaptureLeadAction persists full UTMs and MRR', function () {
        $action = new CaptureLeadAction(
            new AttributionEngine,
            new PhoneValidationService,
            new LeadActivityLogger
        );

        $dto = LeadCaptureData::fromArray([
            'name' => 'Alexander Hamilton',
            'email' => 'alex@treasury.gov',
            'company' => 'Treasury Department',
            'role_needed' => 'Executive Assistant',
            'weekly_hours' => '40',
            'monthly_revenue' => '$5k to $10k Per Month',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'q4_acquisition',
            'utm_term' => 'virtual assistant',
            'utm_content' => 'ad_variant_3',
            'gclid' => 'gclid_test_123456',
            'fbclid' => 'fbclid_test_789012',
            'referral_code' => 'apex-partner',
            'landing_url' => 'https://remoteleverage.com/hire-an-assistant',
            'referrer_url' => 'https://google.com',
            'session_id' => 'sess_unique_uuid_001',
        ]);

        $lead = $action->execute($dto);

        expect($lead->exists)->toBeTrue()
            ->and($lead->monthly_revenue)->toBe('$5k to $10k Per Month')
            ->and($lead->utm_source)->toBe('google')
            ->and($lead->utm_medium)->toBe('cpc')
            ->and($lead->utm_campaign)->toBe('q4_acquisition')
            ->and($lead->utm_term)->toBe('virtual assistant')
            ->and($lead->utm_content)->toBe('ad_variant_3')
            ->and($lead->gclid)->toBe('gclid_test_123456')
            ->and($lead->fbclid)->toBe('fbclid_test_789012')
            ->and($lead->referral_code)->toBe('apex-partner')
            ->and($lead->landing_url)->toBe('https://remoteleverage.com/hire-an-assistant')
            ->and($lead->referrer_url)->toBe('https://google.com')
            ->and($lead->session_id)->toBe('sess_unique_uuid_001');
    });

    test('HandleLeadCreatedForBooking routes to T0 when lead has < $10k MRR', function () {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ben Franklin',
            'email' => 'ben@poorrichard.org',
            'monthly_revenue' => '$0 to $5k Per Month',
            'role_needed' => 'Executive Assistant',
            'weekly_hours' => '40',
            'status' => 'booking_pending',
        ]);

        $passedEventUri = null;
        $fakeBookMeetingAction = new class($passedEventUri) extends BookMeetingAction
        {
            public function __construct(public &$passedEventUri) {}

            public function execute(BookingRequestData $data, ?string $calendlyEventUri = null): array
            {
                $this->passedEventUri = $calendlyEventUri;

                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => 'meet_test_123',
                    'meet_url' => 'https://meet.google.com/test-url',
                ];
            }
        };

        $listener = new HandleLeadCreatedForBooking($fakeBookMeetingAction, new LeadActivityLogger);

        $event = new LeadCreated(
            lead: $lead,
            context: [
                'preferred_slot' => '2026-09-18T14:00:00Z',
                'timezone' => 'America/New_York',
            ]
        );

        $listener->handle($event);

        expect($passedEventUri)->toBe('https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62')
            ->and($lead->fresh()->status)->toBe('booked');
    });

    test('HandleLeadCreatedForBooking routes to T10 when lead has >= $10k MRR', function () {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'George Washington',
            'email' => 'george@mountvernon.org',
            'monthly_revenue' => '$50k-$100k Per Month',
            'role_needed' => 'Executive Assistant',
            'weekly_hours' => '40',
            'status' => 'booking_pending',
        ]);

        $passedEventUri = null;
        $fakeBookMeetingAction = new class($passedEventUri) extends BookMeetingAction
        {
            public function __construct(public &$passedEventUri) {}

            public function execute(BookingRequestData $data, ?string $calendlyEventUri = null): array
            {
                $this->passedEventUri = $calendlyEventUri;

                return [
                    'success' => true,
                    'provider' => 'calendly',
                    'meeting_id' => 'meet_test_456',
                    'meet_url' => 'https://meet.google.com/test-t10',
                ];
            }
        };

        $listener = new HandleLeadCreatedForBooking($fakeBookMeetingAction, new LeadActivityLogger);

        $event = new LeadCreated(
            lead: $lead,
            context: [
                'preferred_slot' => '2026-09-18T15:00:00Z',
                'timezone' => 'America/New_York',
            ]
        );

        $listener->handle($event);

        expect($passedEventUri)->toBe('https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb')
            ->and($lead->fresh()->status)->toBe('booked');
    });
});
