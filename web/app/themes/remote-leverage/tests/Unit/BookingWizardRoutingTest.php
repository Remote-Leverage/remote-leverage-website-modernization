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
use App\Domains\Scheduling\Actions\FetchAvailableSlotsAction;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Data\TimeSlotData;
use App\Domains\Scheduling\Listeners\HandleLeadCreatedForBooking;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use App\Domains\Scheduling\Services\TierAvailability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

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

        $listener = new HandleLeadCreatedForBooking($fakeBookMeetingAction, new LeadActivityLogger, new CalendlyEventTypeRoleResolver);

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

        $listener = new HandleLeadCreatedForBooking($fakeBookMeetingAction, new LeadActivityLogger, new CalendlyEventTypeRoleResolver);

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

    test('Wizard loads one shared UTC window per tier, whatever the visitor\'s zone', function () {
        $fetcher = new class extends FetchAvailableSlotsAction
        {
            public array $calls = [];

            public function __construct() {}

            public function execute(?string $startDate = null, ?string $endDate = null, string $timezone = 'UTC', ?string $eventTypeId = null): array
            {
                $this->calls[] = compact('startDate', 'endDate', 'timezone', 'eventTypeId');

                // Out of order, duplicated, one unavailable, one with an offset: the list the
                // browser gets is sorted, unique, open-only and Zulu.
                $slot = fn (string $start, bool $open = true) => new TimeSlotData($start, $start, 'UTC', $open);

                return [
                    $slot('2026-09-26T15:00:00Z'),
                    $slot('2026-09-25T14:30:00Z'),
                    $slot('2026-09-25T09:30:00-05:00'),
                    $slot('2026-09-25T14:30:00Z'),
                    $slot('2026-09-27T15:00:00Z', false),
                ];
            }
        };

        app()->instance(FetchAvailableSlotsAction::class, $fetcher);
        Cache::forget('rl_avail_window_'.md5('https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb'));

        try {
            $manila = new MultistepBookingWizard;
            $manila->monthlyRevenue = '$10k to $50k Per Month';
            $manila->timezone = 'Asia/Manila';
            $manila->loadAvailability();

            $chicago = new MultistepBookingWizard;
            $chicago->monthlyRevenue = '$10k to $50k Per Month';
            $chicago->timezone = 'America/Chicago';
            $chicago->loadAvailability();

            expect($fetcher->calls)->toHaveCount(1)
                ->and($fetcher->calls[0]['timezone'])->toBe('UTC')
                ->and($manila->openSlots)->toBe(['2026-09-25T14:30:00Z', '2026-09-26T15:00:00Z'])
                ->and($chicago->openSlots)->toBe($manila->openSlots);

            // Seven days, never more: Calendly refuses a longer window on this endpoint.
            $start = Carbon\Carbon::parse($fetcher->calls[0]['startDate']);
            $end = Carbon\Carbon::parse($fetcher->calls[0]['endDate']);
            expect($start->diffInDays($end))->toEqual(7);
        } finally {
            app()->offsetUnset(FetchAvailableSlotsAction::class);
        }
    });

    test('the warm-up fetches once, and not at all when cached or already being fetched', function () {
        $fetcher = new class extends FetchAvailableSlotsAction
        {
            public int $calls = 0;

            public function __construct() {}

            public function execute(?string $startDate = null, ?string $endDate = null, string $timezone = 'UTC', ?string $eventTypeId = null): array
            {
                $this->calls++;

                return [new TimeSlotData('2026-09-25T14:30:00Z', '2026-09-25T15:00:00Z', 'UTC')];
            }
        };

        $uri = 'https://api.calendly.com/event_types/warm-test';
        $key = TierAvailability::cacheKey($uri);
        $tier = new TierAvailability($fetcher);
        Cache::forget($key);

        // Another request already fetching: skip, do not queue a second Calendly call.
        $held = Cache::lock($key.'_lock', 30);
        $held->get();
        $tier->warm($uri, 't10');
        expect($fetcher->calls)->toBe(0);
        $held->release();

        $tier->warm($uri, 't10');
        $tier->warm($uri, 't10');
        expect($fetcher->calls)->toBe(1)
            ->and($tier->slots($uri, 't10'))->toBe(['2026-09-25T14:30:00Z'])
            ->and($fetcher->calls)->toBe(1);

        Cache::forget($key);
    });

    test('Wizard declares no property Livewire uses for itself', function () {
        // A public `$slots` once replaced Livewire 4's own slot registry, and every request that
        // reached dehydrate died with "Call to a member function getName() on string" — while
        // this suite, which never runs Livewire's lifecycle, stayed green.
        $livewire = array_map(
            fn (ReflectionProperty $p) => $p->getName(),
            (new ReflectionClass(Component::class))->getProperties()
        );

        $own = array_map(
            fn (ReflectionProperty $p) => $p->getName(),
            array_filter(
                (new ReflectionClass(MultistepBookingWizard::class))->getProperties(),
                fn (ReflectionProperty $p) => $p->getDeclaringClass()->getName() === MultistepBookingWizard::class
            )
        );

        expect(array_values(array_intersect($own, $livewire)))->toBe([]);
    });

    test('Wizard warms the calendar after the revenue click, and makes no Calendly call on a date click', function () {
        $wizard = new class extends MultistepBookingWizard
        {
            public int $fetches = 0;

            public int $warmUps = 0;

            public function loadAvailability(bool $fresh = false): void
            {
                $this->fetches++;
            }

            protected function warmAvailability(): void
            {
                $this->warmUps++;
            }
        };

        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        $wizard->openSlots = ['2026-09-25T14:30:00Z'];
        $wizard->updatedMonthlyRevenue();

        // Queued for after the response, never fetched in front of it.
        expect($wizard->fetches)->toBe(0)
            ->and($wizard->warmUps)->toBe(1)
            ->and($wizard->openSlots)->toBe([]);

        $wizard->monthlyRevenue = 'Looking for a job? Click Here';
        $wizard->updatedMonthlyRevenue();
        expect($wizard->warmUps)->toBe(1);

        $wizard->monthlyRevenue = '$10k to $50k Per Month';
        $wizard->currentStep = 2;
        $wizard->selectDate('2026-09-25');

        expect($wizard->fetches)->toBe(0)
            ->and($wizard->selectedDate)->toBe('2026-09-25')
            ->and($wizard->currentStep)->toBe(3);

        // The value arrives from the browser; anything that is not a date is ignored.
        $wizard->selectDate('2026-09-25T00:00:00Z');
        expect($wizard->selectedDate)->toBe('2026-09-25');
    });

    test('Wizard isolated fields mount defaults and custom configuration', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->mount(
            roleNeeded: 'Sales Assistant',
            skin: 'naked',
            enableIsolatedFields: true,
            isolatedSteps: [],
            hideProfileHeader: true,
            hideProgressBar: true,
            buttonText: 'Find me an Assistant'
        );

        expect($wizard->skin)->toBe('naked')
            ->and($wizard->enableIsolatedFields)->toBeTrue()
            ->and($wizard->hideProfileHeader)->toBeTrue()
            ->and($wizard->hideProgressBar)->toBeTrue()
            ->and($wizard->buttonText)->toBe('Find me an Assistant')
            ->and($wizard->isolatedSteps)->toHaveCount(2)
            ->and($wizard->isolatedSteps[0]['step_fields'])->toBe(['email'])
            ->and($wizard->isolatedSteps[1]['step_fields'])->toContain('monthly_revenue', 'name', 'phone', 'consent');
    });

    test('Wizard isolated fields automatically adds unassigned fields to step 2 when only email is isolated', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->mount(
            roleNeeded: null,
            skin: 'naked',
            enableIsolatedFields: true,
            isolatedSteps: [
                ['step_label' => 'Email', 'step_fields' => ['email']],
            ]
        );

        // Missing fields (revenue, name, phone, consent) should automatically be added to step 2
        expect($wizard->isolatedSteps)->toHaveCount(2)
            ->and($wizard->isolatedSteps[0]['step_fields'])->toBe(['email'])
            ->and($wizard->isolatedSteps[1]['step_fields'])->toContain('monthly_revenue', 'name', 'phone', 'consent');
    });

    test('Wizard mount handles kebab-case attributes from Blade and auto-enables isolated fields', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->mount(
            ...[
                'enable-isolated-fields' => true,
                'isolated-steps' => [
                    ['step_label' => 'Email', 'step_fields' => ['email']],
                    ['step_label' => 'Details', 'step_fields' => ['monthly_revenue', 'name', 'phone', 'consent']],
                ],
                'hide-profile-header' => true,
                'hide-progress-bar' => true,
                'button-text' => 'Get Started',
            ]
        );

        expect($wizard->enableIsolatedFields)->toBeTrue()
            ->and($wizard->hideProfileHeader)->toBeTrue()
            ->and($wizard->hideProgressBar)->toBeTrue()
            ->and($wizard->buttonText)->toBe('Get Started')
            ->and($wizard->isolatedSteps)->toHaveCount(2);
    });

    test('Wizard enforces a strict 3-step flow across all skins', function () {
        $wizard = new MultistepBookingWizard;
        $wizard->mount(skin: 'default');

        expect($wizard->totalSteps)->toBe(3)
            ->and($wizard->stepTitles)->toHaveCount(3)
            ->and(array_keys($wizard->stepTitles))->toBe([1, 2, 3]);

        $wizard->email = 'founder@acme.com';
        $wizard->name = 'Alice Smith';
        $wizard->phone = '+1 305 555 0199';
        $wizard->phoneCountry = 'US';
        $wizard->monthlyRevenue = '$10k to $25k Per Month';

        $wizard->goToStep(2);
        expect($wizard->currentStep)->toBe(2);

        $date = Carbon\Carbon::now()->addDay()->format('Y-m-d');
        $wizard->selectDate($date);
        expect($wizard->currentStep)->toBe(3);

        // Selecting a slot sets selectedSlot and stays on step 3 for confirmation
        $wizard->selectSlot($date.'T14:00:00Z');
        expect($wizard->selectedSlot)->toBe($date.'T14:00:00Z')
            ->and($wizard->currentStep)->toBe(3);
    });
});
