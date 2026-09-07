<?php

declare(strict_types=1);

namespace App\Application\Livewire\Booking;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Scheduling\Actions\FetchAvailableSlotsAction;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class MultistepBookingWizard extends Component
{
    // Step progression (1: Details, 2: Date, 3: Time, 4: Guests/Confirmation)
    public int $currentStep = 1;

    public int $totalSteps = 4;

    // Profile & Meeting Header Information (matching HeadlessCalendlyMultistepWidget)
    public string $profileEyebrow = 'STRATEGY SESSION';

    public string $profileName = 'Operations Director';

    public string $profileDuration = '30 min • Google Meet';

    public ?string $profileImage = null;

    // Step 1: Contact / Qualification Details
    public string $email = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $name = '';

    public string $phone = '';

    public string $phoneCountry = 'US';

    public array $countryCodes = [
        'US' => ['code' => '+1', 'label' => '🇺🇸 +1'],
        'CA' => ['code' => '+1', 'label' => '🇨🇦 +1'],
        'GB' => ['code' => '+44', 'label' => '🇬🇧 +44'],
        'AU' => ['code' => '+61', 'label' => '🇦🇺 +61'],
        'IE' => ['code' => '+353', 'label' => '🇮🇪 +353'],
        'NZ' => ['code' => '+64', 'label' => '🇳🇿 +64'],
        'DE' => ['code' => '+49', 'label' => '🇩🇪 +49'],
        'FR' => ['code' => '+33', 'label' => '🇫🇷 +33'],
        'ES' => ['code' => '+34', 'label' => '🇪🇸 +34'],
        'IT' => ['code' => '+39', 'label' => '🇮🇹 +39'],
        'NL' => ['code' => '+31', 'label' => '🇳🇱 +31'],
        'SG' => ['code' => '+65', 'label' => '🇸🇬 +65'],
        'AE' => ['code' => '+971', 'label' => '🇦🇪 +971'],
        'MX' => ['code' => '+52', 'label' => '🇲🇽 +52'],
        'BR' => ['code' => '+55', 'label' => '🇧🇷 +55'],
        'CO' => ['code' => '+57', 'label' => '🇨🇴 +57'],
        'PH' => ['code' => '+63', 'label' => '🇵🇭 +63'],
        'IN' => ['code' => '+91', 'label' => '🇮🇳 +91'],
        'ZA' => ['code' => '+27', 'label' => '🇿🇦 +27'],
    ];

    public ?string $company = null;

    public ?string $roleNeeded = null;

    public string $hoursPerWeek = '40';

    public string $monthlyRevenue = '$10k to $50k Per Month';

    public bool $skipCalendar = false;

    // Acquisition & UTM Hidden Tracking Fields (matching rl-testing parity)
    public string $utmSource = '';

    public string $utmMedium = '';

    public string $utmCampaign = '';

    public string $utmTerm = '';

    public string $utmContent = '';

    public string $gclid = '';

    public string $fbclid = '';

    public string $landingUrl = '';

    public string $referrerUrl = '';

    public string $sessionId = '';

    public ?string $referralCode = null;

    // Partial lead ID if captured on Step 1
    public ?int $leadId = null;

    // Step 2: Calendar & Dates
    public int $currentMonth = 0;

    public int $currentYear = 0;

    public ?string $selectedDate = null;

    public array $availableDates = [];

    // Step 3: Time Selection
    public ?string $selectedSlot = null;

    public string $timezone = 'America/New_York';

    public array $availableSlots = [];

    // Step 4: Additional Info & Guests
    public array $guestEmails = [];

    public string $newGuestEmail = '';

    public string $notes = '';

    // Completion Status
    public bool $isBooked = false;

    public ?string $confirmedTime = null;

    public ?string $bookingReference = null;

    public ?string $meetingUrl = null;

    public ?string $errorMessage = null;

    public array $stepTitles = [
        1 => 'Your Details',
        2 => 'Pick a Date',
        3 => 'Select a Time',
        4 => 'Additional Info',
    ];

    protected array $validationRules = [
        1 => [
            'email' => 'required|email|max:150',
            'firstName' => 'required|string|min:1|max:60',
            'lastName' => 'required|string|min:1|max:60',
            'phone' => 'nullable|string|max:30',
            'monthlyRevenue' => 'required|string',
        ],
        2 => [
            'selectedDate' => 'required|string|date_format:Y-m-d',
        ],
        3 => [
            'selectedSlot' => 'required|string',
        ],
        4 => [
            'guestEmails.*' => 'email',
            'notes' => 'nullable|string|max:500',
        ],
    ];

    public function mount(?string $roleNeeded = null): void
    {
        if ($roleNeeded) {
            $this->roleNeeded = $roleNeeded;
        }

        $now = Carbon::now($this->timezone);
        $this->currentMonth = (int) $now->format('n');
        $this->currentYear = (int) $now->format('Y');

        // Acquisition & UTM tracking extraction (parity with rl-testing)
        $this->utmSource = (string) (request()->query('utm_source') ?: request()->cookie('utm_source', request()->cookie('handl_utm_source', '')));
        $this->utmMedium = (string) (request()->query('utm_medium') ?: request()->cookie('utm_medium', request()->cookie('handl_utm_medium', '')));
        $this->utmCampaign = (string) (request()->query('utm_campaign') ?: request()->cookie('utm_campaign', request()->cookie('handl_utm_campaign', '')));
        $this->utmTerm = (string) (request()->query('utm_term') ?: request()->cookie('utm_term', request()->cookie('handl_utm_term', '')));
        $this->utmContent = (string) (request()->query('utm_content') ?: request()->cookie('utm_content', request()->cookie('handl_utm_content', '')));
        $this->gclid = (string) (request()->query('gclid') ?: request()->cookie('gclid', ''));
        $this->fbclid = (string) (request()->query('fbclid') ?: request()->cookie('fbclid', ''));
        $this->referralCode = request()->query('via')
            ?: request()->query('ref')
            ?: request()->query('r')
            ?: request()->cookie('rl_referrer');
        $this->landingUrl = request()->fullUrl();
        $this->referrerUrl = (string) (request()->header('referer') ?: request()->cookie('handl_ref', ''));
        $this->sessionId = (string) Str::uuid();

        $this->loadMonthAvailability();
    }

    public function getActiveEventTypeUri(): string
    {
        if ($this->isUnder10kMrr()) {
            return config('services.calendly.t0_event_type', 'https://api.calendly.com/event_types/ff20712e-6387-4965-9026-dee4c7e5ef62');
        }

        return config('services.calendly.t10_event_type', 'https://api.calendly.com/event_types/5c82a248-c65a-4fb1-bdc6-aefd6e89fbfb');
    }

    public function isUnder10kMrr(): bool
    {
        return in_array($this->monthlyRevenue, [
            '$0 to $5k Per Month',
            '$5k to $10k Per Month',
            '<10k',
            'under_10k',
        ], true);
    }

    public function isJobSeeker(): bool
    {
        return str_contains(strtolower($this->monthlyRevenue), 'job');
    }

    public function updatedMonthlyRevenue(): void
    {
        if (str_contains(strtolower($this->monthlyRevenue), 'job')) {
            $this->skipCalendar = true;
        } else {
            $this->skipCalendar = false;
            $this->selectedDate = null;
            $this->selectedSlot = null;
            $this->loadMonthAvailability();
        }
    }

    public function updatedFirstName(): void
    {
        $this->name = trim($this->firstName.' '.$this->lastName);
    }

    public function updatedLastName(): void
    {
        $this->name = trim($this->firstName.' '.$this->lastName);
    }

    public function goToStep(int $step): void
    {
        $this->errorMessage = null;

        if ($step > $this->currentStep) {
            if (empty($this->firstName) && ! empty($this->name)) {
                $parts = explode(' ', trim($this->name), 2);
                $this->firstName = $parts[0] ?? '';
                $this->lastName = $parts[1] ?? '';
            }
            $this->name = trim($this->firstName.' '.$this->lastName);

            // Validate current step before advancing
            if (isset($this->validationRules[$this->currentStep])) {
                $this->validate($this->validationRules[$this->currentStep]);
            }

            if ($this->currentStep === 1) {
                if (! empty($this->phone)) {
                    $phoneValidator = app(PhoneValidationService::class);
                    $validation = $phoneValidator->validateAndFormat($this->phone, $this->phoneCountry);
                    if (! $validation['isValid']) {
                        $this->addError('phone', 'Please enter a valid phone number.');

                        return;
                    }
                }

                // Parity with rl-testing capture_partial_lead on Step 1 completion
                $this->capturePartialLead();

                if ($this->skipCalendar) {
                    // Job applicant or calendar skip route
                    $this->submitBooking();

                    return;
                }
            }

            if ($this->currentStep === 2 && empty($this->selectedDate)) {
                $this->addError('selectedDate', 'Please select a date from the calendar to proceed.');

                return;
            }

            if ($this->currentStep === 3 && empty($this->selectedSlot)) {
                $this->addError('selectedSlot', 'Please select a time slot to proceed.');

                return;
            }
        }

        $this->currentStep = max(1, min($this->totalSteps, $step));
        if ($this->currentStep === 2) {
            $this->loadMonthAvailability();
        }
        $this->trackStepEvent('step_viewed', ['step' => $this->currentStep]);
    }

    /**
     * Capture partial lead from Step 1 (matches rl-testing partial capture)
     */
    public function capturePartialLead(): void
    {
        if (empty($this->firstName) && ! empty($this->name)) {
            $parts = explode(' ', trim($this->name), 2);
            $this->firstName = $parts[0] ?? '';
            $this->lastName = $parts[1] ?? '';
        }
        $this->name = trim($this->firstName.' '.$this->lastName);

        if (empty($this->email) || empty($this->name)) {
            return;
        }

        try {
            $captureAction = app(CaptureLeadAction::class);
            $leadData = LeadCaptureData::fromArray([
                'name' => $this->name,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'email' => $this->email,
                'phone' => $this->phone,
                'phone_country' => $this->phoneCountry,
                'company' => $this->company,
                'role_needed' => $this->roleNeeded,
                'weekly_hours' => $this->hoursPerWeek,
                'monthly_revenue' => $this->monthlyRevenue,
                'referral_code' => $this->referralCode,
                'utm_source' => $this->utmSource,
                'utm_medium' => $this->utmMedium,
                'utm_campaign' => $this->utmCampaign,
                'utm_term' => $this->utmTerm,
                'utm_content' => $this->utmContent,
                'gclid' => $this->gclid,
                'fbclid' => $this->fbclid,
                'landing_url' => $this->landingUrl,
                'referrer_url' => $this->referrerUrl,
                'session_id' => $this->sessionId,
                'extra_data' => [
                    'source_form' => 'MultistepBookingWizard',
                    'submission_type' => 'Partial',
                    'event_uri' => $this->getActiveEventTypeUri(),
                ],
            ]);

            $lead = $captureAction->execute($leadData);
            $this->leadId = $lead->id;
        } catch (\Throwable $e) {
            Log::warning('Could not capture partial lead on Step 1: '.$e->getMessage());
        }
    }

    public function prevMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = (int) $date->format('n');
        $this->currentYear = (int) $date->format('Y');
        $this->loadMonthAvailability();
    }

    public function nextMonth(): void
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = (int) $date->format('n');
        $this->currentYear = (int) $date->format('Y');
        $this->loadMonthAvailability();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->selectedSlot = null;
        $this->errorMessage = null;

        $this->loadSlotsForDate($date);
        $this->goToStep(3);
    }

    public function selectSlot(string $slot): void
    {
        $this->selectedSlot = $slot;
        $this->goToStep(4);
    }

    public function addGuest(): void
    {
        if (empty($this->newGuestEmail)) {
            return;
        }

        $this->validateOnly('newGuestEmail', ['newGuestEmail' => 'email']);

        if (! in_array($this->newGuestEmail, $this->guestEmails, true)) {
            $this->guestEmails[] = $this->newGuestEmail;
        }

        $this->newGuestEmail = '';
    }

    public function removeGuest(int $index): void
    {
        if (isset($this->guestEmails[$index])) {
            unset($this->guestEmails[$index]);
            $this->guestEmails = array_values($this->guestEmails);
        }
    }

    public function updatedTimezone(): void
    {
        $this->loadMonthAvailability();
        if ($this->selectedDate) {
            $this->loadSlotsForDate($this->selectedDate);
        }
    }

    public function submitBooking(): void
    {
        $this->errorMessage = null;

        if (empty($this->firstName) && ! empty($this->name)) {
            $parts = explode(' ', trim($this->name), 2);
            $this->firstName = $parts[0] ?? '';
            $this->lastName = $parts[1] ?? '';
        }
        $this->name = trim($this->firstName.' '.$this->lastName);

        try {
            $captureAction = app(CaptureLeadAction::class);

            $leadData = LeadCaptureData::fromArray([
                'name' => $this->name,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'email' => $this->email,
                'phone' => $this->phone,
                'phone_country' => $this->phoneCountry,
                'company' => $this->company,
                'role_needed' => $this->roleNeeded,
                'weekly_hours' => $this->hoursPerWeek,
                'monthly_revenue' => $this->monthlyRevenue,
                'notes' => $this->notes,
                'preferred_slot' => $this->selectedSlot,
                'timezone' => $this->timezone,
                'referral_code' => $this->referralCode,
                'utm_source' => $this->utmSource,
                'utm_medium' => $this->utmMedium,
                'utm_campaign' => $this->utmCampaign,
                'utm_term' => $this->utmTerm,
                'utm_content' => $this->utmContent,
                'gclid' => $this->gclid,
                'fbclid' => $this->fbclid,
                'landing_url' => $this->landingUrl,
                'referrer_url' => $this->referrerUrl,
                'session_id' => $this->sessionId,
                'extra_data' => [
                    'source_form' => 'MultistepBookingWizard',
                    'guest_emails' => $this->guestEmails,
                    'event_uri' => $this->getActiveEventTypeUri(),
                    'lead_id' => $this->leadId,
                ],
            ]);

            // Execute lead capture and event-driven booking
            $lead = $captureAction->execute($leadData);
            $lead->refresh();

            if ($lead->status === 'booked' || ! empty($this->selectedSlot)) {
                $this->isBooked = true;
                $bookingLog = $lead->activityLogs()
                    ->where('event_type', 'LeadCreated')
                    ->where('stage', 'consumption')
                    ->first();
                $payload = $bookingLog?->payload ?? [];

                $this->meetingUrl = $payload['meet_url'] ?? 'https://meet.google.com/rl-strategy-'.substr((string) $lead->uuid, 0, 8);
                $this->bookingReference = (string) ($payload['meeting_id'] ?? $lead->uuid);
                $this->confirmedTime = $this->selectedSlot
                    ? Carbon::parse($this->selectedSlot, $this->timezone)->format('l, F j, Y \a\t g:i A').' ('.$this->timezone.')'
                    : 'Scheduled Directly';

                $this->trackStepEvent('booking_completed', [
                    'meeting_id' => $this->bookingReference,
                    'lead_id' => $lead->id,
                    'role' => $this->roleNeeded,
                ]);
            } else {
                $this->isBooked = true;
                $this->confirmedTime = 'Consultation Inquiry Received';
                $this->meetingUrl = '#';
                $this->bookingReference = (string) $lead->uuid;
            }
        } catch (\Throwable $e) {
            Log::error('Error executing booking in wizard: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'An error occurred processing your consultation. Please check your information or try again.';
        }
    }

    public function loadMonthAvailability(): void
    {
        if (empty($this->currentYear) || empty($this->currentMonth)) {
            $now = Carbon::now($this->timezone);
            $this->currentMonth = (int) $now->format('n');
            $this->currentYear = (int) $now->format('Y');
        }

        try {
            $slotsAction = app(FetchAvailableSlotsAction::class);
            $start = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1, $this->timezone)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $slots = $slotsAction->execute(
                $start->toIso8601String(),
                $end->toIso8601String(),
                $this->timezone,
                $this->getActiveEventTypeUri()
            );

            $dates = [];
            foreach ($slots as $slot) {
                $dateKey = Carbon::parse($slot->startTime)->setTimezone($this->timezone)->format('Y-m-d');
                $dates[$dateKey] = true;
            }

            $this->availableDates = array_keys($dates);
        } catch (\Throwable $e) {
            // Fallback generation for weekdays in current month
            $daysInMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->daysInMonth;
            $dates = [];
            $today = Carbon::today($this->timezone);
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateObj = Carbon::createFromDate($this->currentYear, $this->currentMonth, $d, $this->timezone);
                if ($dateObj->isWeekday() && ! $dateObj->isPast()) {
                    $dates[] = $dateObj->format('Y-m-d');
                }
            }
            $this->availableDates = $dates;
        }
    }

    public function loadSlotsForDate(string $date): void
    {
        try {
            $slotsAction = app(FetchAvailableSlotsAction::class);
            $start = Carbon::parse($date, $this->timezone)->startOfDay();
            $end = $start->copy()->endOfDay();

            $slots = $slotsAction->execute(
                $start->toIso8601String(),
                $end->toIso8601String(),
                $this->timezone,
                $this->getActiveEventTypeUri()
            );

            $times = [];
            foreach ($slots as $slot) {
                $times[] = [
                    'iso' => $slot->startTime,
                    'time' => Carbon::parse($slot->startTime, $this->timezone)->format('g:i A'),
                ];
            }

            $this->availableSlots = $times;
        } catch (\Throwable $e) {
            // Default slots
            $this->availableSlots = [
                ['iso' => "{$date} 09:00:00", 'time' => '9:00 AM'],
                ['iso' => "{$date} 10:00:00", 'time' => '10:00 AM'],
                ['iso' => "{$date} 11:00:00", 'time' => '11:00 AM'],
                ['iso' => "{$date} 13:00:00", 'time' => '1:00 PM'],
                ['iso' => "{$date} 14:00:00", 'time' => '2:00 PM'],
                ['iso' => "{$date} 15:00:00", 'time' => '3:00 PM'],
                ['iso' => "{$date} 16:00:00", 'time' => '4:00 PM'],
            ];
        }
    }

    public function getDaysGridProperty(): array
    {
        if (empty($this->currentYear) || empty($this->currentMonth)) {
            $now = Carbon::now($this->timezone);
            $this->currentMonth = (int) $now->format('n');
            $this->currentYear = (int) $now->format('Y');
        }

        $firstDayOfMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1, $this->timezone);
        $daysInMonth = $firstDayOfMonth->daysInMonth;
        $startDayOfWeek = (int) $firstDayOfMonth->format('w'); // 0 = Sunday, 6 = Saturday
        $today = Carbon::today($this->timezone)->format('Y-m-d');

        $grid = [];

        // Preceding empty slots for offset
        for ($i = 0; $i < $startDayOfWeek; $i++) {
            $grid[] = ['empty' => true];
        }

        // Days of month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateObj = Carbon::createFromDate($this->currentYear, $this->currentMonth, $day, $this->timezone);
            $dateStr = $dateObj->format('Y-m-d');
            $isPast = $dateObj->lt(Carbon::today($this->timezone));
            $isToday = ($dateStr === $today);
            $hasAvailability = in_array($dateStr, $this->availableDates, true) && ! $isPast;
            $isSelected = ($this->selectedDate === $dateStr);

            $grid[] = [
                'empty' => false,
                'day' => $day,
                'date' => $dateStr,
                'isPast' => $isPast,
                'isToday' => $isToday,
                'hasAvailability' => $hasAvailability,
                'isSelected' => $isSelected,
            ];
        }

        return $grid;
    }

    protected function trackStepEvent(string $eventName, array $properties = []): void
    {
        try {
            $recorder = app(RecordBehaviorEventAction::class);
            $recorder->execute(new AnalyticsEventData(
                event: "booking_wizard_{$eventName}",
                distinctId: $this->email ?: session()->getId(),
                properties: array_merge([
                    'step' => $this->currentStep,
                    'selected_date' => $this->selectedDate,
                    'selected_slot' => $this->selectedSlot,
                    'role_needed' => $this->roleNeeded,
                ], $properties)
            ));
        } catch (\Throwable $e) {
            // Silently swallow analytics errors to avoid breaking booking UX
        }
    }

    public function render(): View
    {
        if (empty($this->currentYear) || empty($this->currentMonth)) {
            $now = Carbon::now($this->timezone);
            $this->currentMonth = (int) $now->format('n');
            $this->currentYear = (int) $now->format('Y');
        }

        $monthTitle = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1, $this->timezone)->format('F Y');

        return view('livewire.booking.multistep-booking-wizard', [
            'monthTitle' => $monthTitle,
            'daysGrid' => $this->daysGrid,
        ]);
    }
}
