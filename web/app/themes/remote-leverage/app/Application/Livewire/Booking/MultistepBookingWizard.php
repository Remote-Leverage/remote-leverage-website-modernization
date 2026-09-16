<?php

declare(strict_types=1);

namespace App\Application\Livewire\Booking;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Services\AttributionCollector;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Scheduling\Actions\FetchAvailableSlotsAction;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class MultistepBookingWizard extends Component
{
    // Step progression (1: Details, 2: Date, 3: Time & Confirm)
    public int $currentStep = 1;

    public int $totalSteps = 3;

    // Profile & Meeting Header Information (matching HeadlessCalendlyMultistepWidget)
    public string $profileEyebrow = 'STRATEGY SESSION';

    public string $profileName = 'Operations Director';

    public string $profileDuration = '30 min • Google Meet';

    public ?string $profileImage = null;

    public string $skin = 'default';

    public bool $enableIsolatedFields = false;

    public array $isolatedSteps = [];

    /** Default off site-wide (2026-09-15): the avatar / "30 min · Google Meet" card and the
     *  three-step progress rail are not wanted on any surface. A caller can still pass
     *  false explicitly to bring either back. */
    public bool $hideProfileHeader = true;

    public bool $hideProgressBar = true;

    /** Narrow contexts (the article sidebar) need the revenue picker as a dropdown
     *  rather than a wrapping row of pills. First/last name are now always paired,
     *  on every instance, so this no longer controls that. */
    public bool $compactFields = false;

    /** The page-bottom booking blocks (acf/booking, acf/booking-footer) lead with the revenue
     *  question, rendered as a vertical radio list rather than a row of pills (direction
     *  2026-09-15). The glass skin has always done this; this brings the other skins in line.
     *
     *  Scoped to those blocks on purpose — the hero forms (acf/hire-va-hero,
     *  acf/consult-landing-hero) keep their progressive email-first reveal. */
    public bool $revenueFirst = false;

    public string $buttonText = 'Book a Consultation';

    /**
     * Heading and supporting line on the card, above the fields.
     *
     * Defaults are what every page carrying the light/glass card already renders; the 2026
     * homepage's booking footer names its own ("Book a Free 15-Minute Consultation", with no
     * supporting line).
     */
    public string $cardTitle = 'Your Contact Information';

    public string $cardSubtitle = 'Provide your contact details so our advisor can review your company requirements.';

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

    /**
     * No default on purpose. This field is not decorative: isUnder10kMrr() and
     * HandleLeadCreatedForBooking pick the Calendly event type from it, and
     * isJobSeeker() filters job applicants out of the sales funnel. A pre-selected
     * bracket silently classified every lead that never opened the control.
     */
    public string $monthlyRevenue = '';

    /**
     * Consent to SMS/phone/email contact. Recorded, never blocking — a lead who
     * leaves it unticked still books; they just must not be enrolled in SMS.
     *
     * It starts false because it previously shipped with a hardcoded `checked`
     * attribute and no binding to anything, so it neither expressed a choice nor
     * reached the server. (Its `required` attribute was inert anyway: the wizard
     * submits through wire:click, not a native <form>, so constraint validation
     * never ran.)
     */
    public bool $consent = false;

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

    /** Whether `form_started` has already been emitted for this component instance. */
    public bool $formStartedTracked = false;

    /**
     * Attribution with a first-class Lead column, as `column => value`.
     *
     * Held as one array rather than a property per parameter so a new tracking parameter is a
     * line in `AttributionCollector::NAMED` plus a migration — not another public property to
     * add here, hydrate on every Livewire round trip, and thread into the DTO.
     *
     * @var array<string, string>
     */
    public array $attributionNamed = [];

    /** @var array<string, mixed> The HandL set plus any query parameter without a column. */
    public array $attribution = [];

    /** Client IP, taken from the forwarded header because this sits behind a CDN. */
    public ?string $ipAddress = null;

    /**
     * PostHog's session id, pushed in from the browser once the SDK has one.
     *
     * Only the browser knows it, so it cannot be collected server-side like the rest of the
     * attribution. It is what makes the session replay reachable from the lead timeline.
     */
    public string $posthogSessionId = '';

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
    ];

    public function mount(
        ?string $roleNeeded = null,
        string $skin = 'default',
        bool $enableIsolatedFields = false,
        array $isolatedSteps = [],
        bool $hideProfileHeader = true,
        bool $hideProgressBar = true,
        bool $compactFields = false,
        bool $revenueFirst = false,
        string $buttonText = 'Book a Consultation',
        ...$rest
    ): void {
        if (isset($rest['revenue-first'])) {
            $revenueFirst = (bool) $rest['revenue-first'];
        }
        if (isset($rest['revenueFirst'])) {
            $revenueFirst = (bool) $rest['revenueFirst'];
        }
        $this->revenueFirst = $revenueFirst;

        if (isset($rest['enable-isolated-fields'])) {
            $enableIsolatedFields = (bool) $rest['enable-isolated-fields'];
        }
        if (isset($rest['enableIsolatedFields'])) {
            $enableIsolatedFields = (bool) $rest['enableIsolatedFields'];
        }
        if (isset($rest['isolated-steps'])) {
            $isolatedSteps = (array) $rest['isolated-steps'];
        }
        if (isset($rest['isolatedSteps'])) {
            $isolatedSteps = (array) $rest['isolatedSteps'];
        }
        if (isset($rest['hide-profile-header'])) {
            $hideProfileHeader = (bool) $rest['hide-profile-header'];
        }
        if (isset($rest['hideProfileHeader'])) {
            $hideProfileHeader = (bool) $rest['hideProfileHeader'];
        }
        if (isset($rest['hide-progress-bar'])) {
            $hideProgressBar = (bool) $rest['hide-progress-bar'];
        }
        if (isset($rest['hideProgressBar'])) {
            $hideProgressBar = (bool) $rest['hideProgressBar'];
        }
        if (isset($rest['compact-fields'])) {
            $compactFields = (bool) $rest['compact-fields'];
        }
        if (isset($rest['compactFields'])) {
            $compactFields = (bool) $rest['compactFields'];
        }
        if (isset($rest['button-text'])) {
            $buttonText = (string) $rest['button-text'];
        }
        if (isset($rest['buttonText'])) {
            $buttonText = (string) $rest['buttonText'];
        }

        if (! empty($isolatedSteps)) {
            $enableIsolatedFields = true;
        }

        if ($roleNeeded) {
            $this->roleNeeded = $roleNeeded;
        }
        $this->skin = $skin;
        $this->enableIsolatedFields = $enableIsolatedFields;
        $this->isolatedSteps = $isolatedSteps;
        $this->hideProfileHeader = $hideProfileHeader;
        $this->hideProgressBar = $hideProgressBar;
        $this->compactFields = $compactFields;
        $this->buttonText = $buttonText;

        if ($this->enableIsolatedFields) {
            if (empty($this->isolatedSteps)) {
                $this->isolatedSteps = [
                    ['step_label' => 'Email', 'step_fields' => ['email']],
                    ['step_label' => 'Complete First Step', 'step_fields' => ['monthly_revenue', 'name', 'phone', 'consent']],
                ];
            } else {
                $configuredFields = [];
                foreach ($this->isolatedSteps as $st) {
                    foreach ($st['step_fields'] ?? [] as $f) {
                        $configuredFields[] = $f;
                    }
                }
                $allStandardFields = ['monthly_revenue', 'name', 'phone', 'consent'];
                $missing = array_diff($allStandardFields, $configuredFields);
                if (! empty($missing)) {
                    if (count($this->isolatedSteps) === 1) {
                        $this->isolatedSteps[] = [
                            'step_label' => 'Remaining Details',
                            'step_fields' => array_values($missing),
                        ];
                    } else {
                        $lastIdx = count($this->isolatedSteps) - 1;
                        $this->isolatedSteps[$lastIdx]['step_fields'] = array_values(array_unique(array_merge($this->isolatedSteps[$lastIdx]['step_fields'], $missing)));
                    }
                }
            }
        }

        $now = Carbon::now($this->timezone);
        $this->currentMonth = (int) $now->format('n');
        $this->currentYear = (int) $now->format('Y');

        // Acquisition & UTM tracking extraction (parity with rl-testing)
        $req = app()->bound('request') ? app('request') : null;
        $this->utmSource = (string) ($req?->query('utm_source') ?: $req?->cookie('utm_source', $req?->cookie('handl_utm_source', '')));
        $this->utmMedium = (string) ($req?->query('utm_medium') ?: $req?->cookie('utm_medium', $req?->cookie('handl_utm_medium', '')));
        $this->utmCampaign = (string) ($req?->query('utm_campaign') ?: $req?->cookie('utm_campaign', $req?->cookie('handl_utm_campaign', '')));
        $this->utmTerm = (string) ($req?->query('utm_term') ?: $req?->cookie('utm_term', $req?->cookie('handl_utm_term', '')));
        $this->utmContent = (string) ($req?->query('utm_content') ?: $req?->cookie('utm_content', $req?->cookie('handl_utm_content', '')));
        $this->gclid = (string) ($req?->query('gclid') ?: $req?->cookie('gclid', ''));
        $this->fbclid = (string) ($req?->query('fbclid') ?: $req?->cookie('fbclid', ''));
        $this->referralCode = $req?->query('via')
            ?: $req?->query('ref')
            ?: $req?->query('r')
            ?: $req?->cookie('rl_referrer');
        $this->landingUrl = (string) ($req?->fullUrl() ?? '');
        $this->referrerUrl = (string) ($req?->header('referer') ?: $req?->cookie('handl_ref', ''));
        $this->sessionId = (string) Str::uuid();

        /*
         * Everything the legacy Gravity Form's hidden fields carried. The five UTMs, gclid and
         * fbclid are read above for backwards compatibility with callers that pass them
         * explicitly; the collector fills in the rest, and crucially keeps any query parameter
         * it does not recognise so a new ad platform's click id is never silently dropped.
         */
        $collector = app(AttributionCollector::class);
        $collected = $collector->collect($req);

        $this->attributionNamed = $collected['named'];
        $this->attribution = $collected['attribution'];
        $this->ipAddress = $collector->ipAddress($req);

        $this->trackStepEvent('form_loaded');
    }

    public function getActiveEventTypeUri(): string
    {
        $roleResolver = app(CalendlyEventTypeRoleResolver::class);

        return $this->isUnder10kMrr()
            ? (string) $roleResolver->get('t0')
            : (string) $roleResolver->get('t10');
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

        // Legacy fired `step_date_selection` only on arrival at the calendar step. `step_viewed`
        // has no legacy counterpart and is kept as a v2 addition: it is what makes per-step
        // drop-off measurable at all, which the legacy set could not show.
        if ($this->currentStep === 2) {
            $this->trackStepEvent('step_date_selection');
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
                'consent' => $this->consent,
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
                'attribution_named' => $this->attributionNamedFor('Partial'),
                'attribution' => $this->attribution,
            ]);

            $lead = $captureAction->execute($leadData);
            $this->leadId = $lead->id;

            $this->trackStepEvent('partial_form_submitted', ['lead_id' => $lead->id]);
        } catch (\Throwable $e) {
            Log::warning('Could not capture partial lead on Step 1: '.$e->getMessage());
        }
    }

    public function prevMonth(): void
    {
        $today = Carbon::today($this->timezone);
        $currentFirst = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1, $this->timezone);
        if ($currentFirst->isSameMonth($today) || $currentFirst->isPast()) {
            return;
        }

        $date = $currentFirst->copy()->subMonth();
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

        $this->trackStepEvent('hour_selected', ['selected_time' => $slot]);
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

        // Fired before the attempt, not after it: paired against `booking_finished` this is
        // what makes a failed booking visible as a gap rather than as silence.
        $this->trackStepEvent('booking_request_sent', ['selected_time' => $this->selectedSlot]);

        if (empty($this->firstName) && ! empty($this->name)) {
            $parts = explode(' ', trim($this->name), 2);
            $this->firstName = $parts[0] ?? '';
            $this->lastName = $parts[1] ?? '';
        }
        $this->name = trim($this->firstName.' '.$this->lastName);

        // Server-side double-submit guard: a plain Livewire property only catches a
        // second click after the first response already re-rendered the button
        // disabled — it can't stop two genuinely concurrent requests that both
        // hydrated from the same prior snapshot. Cache::lock() is shared across
        // requests regardless of what snapshot they hydrated from.
        $lock = Cache::lock('rl_booking_submit_'.md5($this->email.'|'.$this->selectedSlot), 15);

        if (! $lock->get()) {
            $this->errorMessage = 'Your booking is already being processed — please wait a moment.';

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
                'consent' => $this->consent,
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
                'attribution_named' => $this->attributionNamedFor('Final'),
                'attribution' => $this->attribution,
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

                $this->trackStepEvent('booking_finished', [
                    'meeting_id' => $this->bookingReference,
                    'lead_id' => $lead->id,
                    'role' => $this->roleNeeded,
                    'selected_time' => $this->selectedSlot,
                ]);
            } else {
                $this->isBooked = true;
                $this->confirmedTime = 'Consultation Inquiry Received';
                $this->meetingUrl = '#';
                $this->bookingReference = (string) $lead->uuid;

                // The skipCalendar / job-applicant route reaches the thank-you page without
                // ever booking a slot. It still has to emit the terminal event, or every
                // funnel reads that route as a 100% drop-off after `booking_request_sent`.
                $this->trackStepEvent('booking_finished', [
                    'lead_id' => $lead->id,
                    'role' => $this->roleNeeded,
                    'booked_slot' => false,
                ]);
            }

            // A successful booking now navigates to a dedicated thank-you page
            // (with its own "confirm via email" steps and social proof) rather
            // than rendering a confirmation state inline in the widget.
            $this->redirect(home_url('/vathankyou/'));

            return;
        } catch (\Throwable $e) {
            Log::error('Error executing booking in wizard: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'An error occurred processing your consultation. Please check your information or try again.';
        } finally {
            $lock->release();
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

            $cacheKey = 'rl_avail_dates_'.md5($this->getActiveEventTypeUri().$start->format('Y-m').$this->timezone);
            $this->availableDates = Cache::remember($cacheKey, 600, function () use ($slotsAction, $start, $end) {
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

                return array_keys($dates);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to load month availability: '.$e->getMessage());
            $this->availableDates = [];
        }
    }

    public function loadSlotsForDate(string $date): void
    {
        try {
            $slotsAction = app(FetchAvailableSlotsAction::class);
            $start = Carbon::parse($date, $this->timezone)->startOfDay();
            $end = $start->copy()->endOfDay();

            $cacheKey = 'rl_avail_slots_'.md5($this->getActiveEventTypeUri().$date.$this->timezone);
            $this->availableSlots = Cache::remember($cacheKey, 600, function () use ($slotsAction, $start, $end) {
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
                        'time' => Carbon::parse($slot->startTime, $this->timezone)->format('g:ia'),
                    ];
                }

                return $times;
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to load slots for date: '.$e->getMessage());
            $this->availableSlots = [];
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

    /**
     * Emit a booking-funnel event under the name the legacy form used.
     *
     * The names here are **verbatim from rl-elementor-blocks'
     * `assets/js/headless-calendly-multistep.js`**, which fired them client-side via
     * `posthog.capture()`. They are what the existing PostHog funnels, Customer.io campaigns
     * and n8n flows are keyed to, so they are not free to rename: this method deliberately
     * does not prefix them. An earlier version emitted `booking_wizard_{$name}`, which meant
     * every downstream consumer of `form_started` / `hour_selected` / `booking_finished` saw
     * nothing at all once the Livewire wizard replaced the legacy form.
     *
     * The four common properties are also the legacy shape. `form_id` was the Gravity Forms
     * id there and is the Livewire component id here — different value, same role: a stable
     * handle for one form instance on one page.
     *
     * These fire **server-side** now, from the component, rather than from the browser. That
     * is strictly more reliable (no ad-blockers, no lost beacon on unload), but it does mean
     * an event only exists where the component has a lifecycle hook to hang it on — see
     * `form_started`, which is the one that had to change meaning slightly.
     */
    protected function trackStepEvent(string $eventName, array $properties = []): void
    {
        try {
            $recorder = app(RecordBehaviorEventAction::class);
            $recorder->execute(new AnalyticsEventData(
                event: $eventName,
                distinctId: $this->resolveDistinctId(),
                properties: array_merge([
                    'form_id' => (string) $this->getId(),
                    'session_id' => $this->sessionId,
                    'form_type' => 'multistep',
                    'is_isolated' => ! empty($this->isolatedSteps),
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

    /**
     * Identify the visitor, without letting the attempt lose the event.
     *
     * This used to be `$this->email ?: session()->getId()`. Every event raised before the
     * visitor types an email — which, since the funnel was restored, is most of them —
     * therefore depended on `session()` exposing `getId()`. Where it does not, the call throws,
     * and `trackStepEvent()`'s catch-all discards the event with no trace. The component's own
     * `sessionId` is a per-instance UUID that is always present after mount, so it is a better
     * anonymous handle than a session id the container may not offer at all.
     */
    /**
     * The attribution columns to stamp on the Lead, for either capture point.
     *
     * Built in one place so a partial capture and a completed booking cannot disagree about
     * what was collected — the partial is what survives when someone abandons at step 2, and
     * it carried none of this before.
     *
     * @return array<string, string>
     */
    protected function attributionNamedFor(string $submissionType): array
    {
        return array_filter(array_merge($this->attributionNamed, [
            'ip_address' => $this->ipAddress,
            'posthog_session_id' => $this->posthogSessionId,
            'timezone' => $this->timezone,
            'submission_type' => $submissionType,

            // Mirrors the legacy form's two constant hidden fields (GF 50 and 51). `source`
            // in HubSpot is fed from data_source, so it is what separates leads that came
            // through this site from every other feed into the portal.
            'intake_form' => 'yes',
            'data_source' => (string) config('services.lead.data_source', 'Remote Leverage v2'),
        ]), static fn ($value) => $value !== null && $value !== '');
    }

    protected function resolveDistinctId(): string
    {
        if ($this->email !== '') {
            return $this->email;
        }

        try {
            $sessionId = (string) session()->getId();

            if ($sessionId !== '') {
                return $sessionId;
            }
        } catch (\Throwable) {
            // No session driver bound; fall through to the component's own id.
        }

        return $this->sessionId ?: 'anonymous';
    }

    /**
     * `form_started` — the visitor has begun filling the form.
     *
     * Legacy bound this to the first `input` event on any visible field. Livewire only learns
     * about a field when its model round-trips, so this fires on the first property update
     * instead: later than the legacy event by one debounce, same meaning. The guard is a
     * public property because it has to survive the component's re-hydration between
     * requests — a local flag would reset on every one and re-fire the event each keystroke.
     */
    public function updated(string $property): void
    {
        if ($this->formStartedTracked) {
            return;
        }

        // Calendar paging and timezone are not "starting the form" — they move the UI without
        // the visitor having entered anything.
        if (in_array($property, ['timezone', 'currentMonth', 'currentYear', 'newGuestEmail'], true)) {
            return;
        }

        $this->formStartedTracked = true;
        $this->trackStepEvent('form_started');
    }

    /**
     * Rendered while `lazy` on the <livewire:booking.multistep-booking-wizard>
     * tag defers the real mount until the component scrolls into view — a
     * bare fallback here would collapse to zero height and could miss the
     * intersection observer's trigger entirely. Height is an approximation
     * of the real widget; adjust if it causes visible layout shift.
     */
    public function placeholder(array $params = []): string
    {
        return <<<'HTML'
        <div class="animate-pulse rounded-2xl bg-white/10 min-h-120 w-full"></div>
        HTML;
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
            'skin' => $this->skin,
        ]);
    }
}
