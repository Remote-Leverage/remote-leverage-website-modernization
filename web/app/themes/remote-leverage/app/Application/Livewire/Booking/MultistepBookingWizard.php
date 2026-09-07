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
use Livewire\Component;

class MultistepBookingWizard extends Component
{
    // Funnel Steps: 1 = Qualification, 2 = Slot Selection, 3 = Contact & Confirm
    public int $currentStep = 1;

    // Step 1: Qualification Data
    public string $roleNeeded = 'Executive Assistant';

    public string $hoursPerWeek = '40';

    public string $startDate = 'Immediately';

    // Step 2: Timezone & Slot Selection
    public string $timezone = 'America/New_York';

    public string $selectedDate = '';

    public string $selectedSlot = '';

    public array $availableSlots = [];

    public array $calendarDays = [];

    // Step 3: Attendee Details
    public string $name = '';

    public string $email = '';

    public string $company = '';

    public string $phone = '';

    public string $notes = '';

    public ?string $referralCode = null;

    // Booking Result
    public bool $isBooked = false;

    public ?string $meetingUrl = null;

    public ?string $bookingReference = null;

    public ?string $confirmedTime = null;

    public ?string $errorMessage = null;

    protected array $rules = [
        1 => [
            'roleNeeded' => 'required|string',
            'hoursPerWeek' => 'required|string',
            'startDate' => 'required|string',
        ],
        2 => [
            'timezone' => 'required|string',
            'selectedDate' => 'required|string',
            'selectedSlot' => 'required|string',
        ],
        3 => [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150',
            'company' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ],
    ];

    public function mount(?string $referralCode = null): void
    {
        $this->referralCode = $referralCode ?? request()->cookie('rl_referrer') ?? request()->query('via');
        $this->selectedDate = Carbon::now($this->timezone)->format('Y-m-d');
        $this->loadCalendarDays();
        $this->refreshAvailableSlots();
    }

    public function updatedTimezone(): void
    {
        $this->loadCalendarDays();
        $this->refreshAvailableSlots();
        $this->selectedSlot = '';
    }

    public function updatedSelectedDate(): void
    {
        $this->refreshAvailableSlots();
        $this->selectedSlot = '';
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->refreshAvailableSlots();
        $this->selectedSlot = '';
    }

    public function selectSlot(string $slotIso): void
    {
        $this->selectedSlot = $slotIso;
    }

    public function loadCalendarDays(): void
    {
        $days = [];
        $start = Carbon::now($this->timezone)->startOfDay();

        for ($i = 0; $i < 10; $i++) {
            $date = $start->copy()->addDays($i);
            // Skip weekends for business consultations
            if ($date->isWeekend()) {
                continue;
            }

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('D'),
                'day_num' => $date->format('j'),
                'month_name' => $date->format('M'),
            ];

            if (count($days) >= 7) {
                break;
            }
        }

        $this->calendarDays = $days;
        if (! in_array($this->selectedDate, array_column($days, 'date'), true) && ! empty($days)) {
            $this->selectedDate = $days[0]['date'];
        }
    }

    public function refreshAvailableSlots(): void
    {
        try {
            $fetchAction = app(FetchAvailableSlotsAction::class);
            $startOfDay = Carbon::parse($this->selectedDate, $this->timezone)->startOfDay()->toIso8601String();
            $endOfDay = Carbon::parse($this->selectedDate, $this->timezone)->endOfDay()->toIso8601String();

            $slots = $fetchAction->execute($startOfDay, $endOfDay, $this->timezone);

            $this->availableSlots = array_map(function ($slot) {
                $start = Carbon::parse($slot->startTime, $this->timezone);

                return [
                    'start_time' => $slot->startTime,
                    'display_time' => $start->format('g:i A'),
                    'timezone' => $this->timezone,
                    'available' => $slot->available,
                ];
            }, $slots);
        } catch (\Throwable $e) {
            Log::warning('Could not fetch real-time slots in wizard: '.$e->getMessage());
            $this->availableSlots = [];
        }
    }

    public function nextStep(): void
    {
        $this->errorMessage = null;

        if (isset($this->rules[$this->currentStep])) {
            $this->validate($this->rules[$this->currentStep]);
        }

        if ($this->currentStep === 1) {
            $this->trackStepEvent('booking_step_1_completed', [
                'role' => $this->roleNeeded,
                'hours' => $this->hoursPerWeek,
            ]);
            $this->currentStep = 2;
            $this->refreshAvailableSlots();

            return;
        }

        if ($this->currentStep === 2) {
            $this->trackStepEvent('booking_step_2_completed', [
                'slot' => $this->selectedSlot,
                'timezone' => $this->timezone,
            ]);
            $this->currentStep = 3;

            return;
        }

        if ($this->currentStep === 3) {
            $this->submitBooking();
        }
    }

    public function previousStep(): void
    {
        $this->errorMessage = null;
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function submitBooking(): void
    {
        $this->validate($this->rules[3]);

        // Optional international phone validation check
        if (! empty($this->phone)) {
            $phoneValidator = app(PhoneValidationService::class);
            $validation = $phoneValidator->validateAndFormat($this->phone);
            if (! $validation['isValid']) {
                $this->addError('phone', 'Please enter a valid international phone number.');

                return;
            }
        }

        try {
            $captureAction = app(CaptureLeadAction::class);

            $leadData = LeadCaptureData::fromArray([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'company' => $this->company,
                'role_needed' => $this->roleNeeded,
                'weekly_hours' => $this->hoursPerWeek,
                'start_date' => $this->startDate,
                'notes' => $this->notes,
                'preferred_slot' => $this->selectedSlot,
                'timezone' => $this->timezone,
                'referral_code' => $this->referralCode,
                'extra_data' => [
                    'source_form' => 'MultistepBookingWizard',
                ],
            ]);

            // Capture lead, stamp attribution, and trigger event-driven booking
            $lead = $captureAction->execute($leadData);
            $lead->refresh();

            if ($lead->status === 'booked') {
                $this->isBooked = true;
                $bookingLog = $lead->activityLogs()->where('event_type', 'LeadCreated')->where('stage', 'consumption')->first();
                $payload = $bookingLog?->payload ?? [];

                $this->meetingUrl = $payload['meet_url'] ?? null;
                $this->bookingReference = (string) ($payload['meeting_id'] ?? $lead->uuid);
                $this->confirmedTime = Carbon::parse($this->selectedSlot, $this->timezone)->format('l, F j, Y \a\t g:i A').' ('.$this->timezone.')';

                $this->trackStepEvent('booking_completed', [
                    'meeting_id' => $this->bookingReference,
                    'role' => $this->roleNeeded,
                    'hours' => $this->hoursPerWeek,
                    'lead_id' => $lead->id,
                ]);
            } else {
                $this->errorMessage = 'Your consultation request has been saved, but we could not lock this specific slot. Our team will reach out directly!';
            }
        } catch (\Throwable $e) {
            Log::error('Error executing booking in wizard: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Unable to finalize booking right now. Please try again or contact us directly.';
        }
    }

    protected function trackStepEvent(string $eventName, array $properties): void
    {
        try {
            $tracker = app(RecordBehaviorEventAction::class);
            $tracker->execute(new AnalyticsEventData(
                event: $eventName,
                distinctId: $this->email ?: 'anonymous_'.request()->ip(),
                properties: array_merge($properties, [
                    'source' => 'multistep_wizard',
                    'referral_code' => $this->referralCode,
                ]),
                timestamp: time()
            ));
        } catch (\Throwable $e) {
            // Silently ignore tracking failure to protect UI flow
        }
    }

    public function render(): View
    {
        return view('livewire.booking.multistep-booking-wizard');
    }
}
