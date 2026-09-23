<?php

declare(strict_types=1);

namespace App\Application\Livewire\Booking;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\RecordBouncedLeadAction;
use App\Domains\Lead\Data\LeadAudience;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\AttributionCollector;
use App\Domains\Lead\Services\EmailValidationService;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Scheduling\Actions\FetchAvailableSlotsAction;
use App\Domains\Scheduling\Services\AvailabilityHealthMonitor;
use App\Domains\Scheduling\Services\CalendlyEventTypeRoleResolver;
use App\Domains\Scheduling\Services\TierUtilizationProbe;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Support\GoogleEnhancedConversion;
use App\Infrastructure\Queue\Deferred;
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
    /** How long a populated availability answer is reused. See availabilityTtl(). */
    public const AVAILABILITY_TTL_SECONDS = 600;

    /** How long an empty one is. Deliberately an order of magnitude shorter. */
    public const EMPTY_AVAILABILITY_TTL_SECONDS = 60;

    /**
     * How long a step-one submission keeps resuming the lead the same visit already created.
     *
     * Long enough to cover a reload, a phone call and a second look at the pricing; short
     * enough that someone returning tomorrow is a new lead with a new alert rather than an
     * amendment to a card the channel scrolled past yesterday. See resumableLeadId().
     */
    private const RESUME_WINDOW_MINUTES = 30;

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

    /**
     * Whether `posthog.identify()` has already been sent for this visitor.
     *
     * A public property because it has to survive the component's re-hydration between
     * requests — a local flag would reset on every one and re-identify on each step.
     */
    public bool $identifiedInBrowser = false;

    /**
     * Whether the interstitial warning screen is showing.
     *
     * Its own flag rather than a `currentStep` value, because the warning sits *between* steps
     * 1 and 2 without being one: the progress indicator must not count it, and going back from
     * the calendar has to land on the warning rather than skip it — which is what the legacy
     * form did.
     */
    public bool $showWarning = false;

    /**
     * Whether the visitor has already passed the warning.
     *
     * Separate from `showWarning`, and the reason the Continue button appeared to do nothing:
     * `acknowledgeWarning()` cleared `showWarning` and called `goToStep(2)`, which re-ran the
     * step-1 checks — including the one that decides whether to show the warning. It was
     * immediately true again, so the visitor bounced straight back to the screen they had just
     * dismissed. A flag that survives the trip is what breaks the loop.
     */
    public bool $warningAcknowledged = false;

    /**
     * Whether the "looking for VA work?" interstitial is showing.
     *
     * Sits in the same slot as the pricing warning — after step 1's gates, before the calendar —
     * and for the same reason: the partial lead is already captured, so somebody who leaves for
     * the jobs site is still a lead, and somebody who came to hire has not lost anything.
     */
    public bool $showApplicantNotice = false;

    /**
     * The visitor said they are here to hire. Survives the trip back through step 1 so the
     * notice cannot re-fire on the same signals and trap them in a loop — the mistake
     * `warningAcknowledged` exists to document.
     */
    public bool $applicantNoticeDismissed = false;

    public ?string $referralCode = null;

    // Partial lead ID if captured on Step 1
    public ?int $leadId = null;

    // Step 2: Calendar & Dates
    public int $currentMonth = 0;

    public int $currentYear = 0;

    public ?string $selectedDate = null;

    public array $availableDates = [];

    /**
     * Soonest bookable date on this tier, in any month. Null only when the tier has nothing
     * bookable at all — which is the sell-out condition, not merely an empty month.
     */
    public ?string $nextAvailableDate = null;

    // Step 3: Time Selection
    public ?string $selectedSlot = null;

    public string $timezone = 'America/New_York';

    /**
     * Set once the visitor picks a zone themselves, so a late-arriving browser
     * detection cannot overwrite a deliberate choice.
     */
    public bool $timezoneChosen = false;

    /**
     * Zones offered in the picker. Desktop and mobile used to carry two different
     * hand-written lists — desktop had Bogota and UTC, mobile had Paris — so which
     * zones a visitor could pick depended on their screen width.
     */
    public const TIMEZONE_CHOICES = [
        'America/Bogota',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'Europe/London',
        'Europe/Paris',
        'UTC',
    ];

    public const TIMEZONE_LABELS = [
        'America/New_York' => 'Eastern Time (ET)',
        'America/Chicago' => 'Central Time (CT)',
        'America/Denver' => 'Mountain Time (MT)',
        'America/Los_Angeles' => 'Pacific Time (PT)',
        'Europe/London' => 'London (GMT/BST)',
        'Europe/Paris' => 'Central Europe (CET)',
    ];

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

    /**
     * The phone input sits inside `wire:ignore` and is written by phoneInputComponent, so a
     * value the visitor can see is not necessarily a value the server received. "The phone
     * field is required" in front of a filled-in box would read as a broken form.
     */
    protected array $validationMessages = [
        'phone.required' => 'Please enter your phone number — re-type it if it was filled in automatically.',
    ];

    protected array $validationRules = [
        1 => [
            'email' => 'required|email|max:150',
            'firstName' => 'required|string|min:1|max:60',
            'lastName' => 'required|string|min:1|max:60',
            // Required, matching the asterisk the label has always carried. It was nullable
            // until 2026-09-21, and nothing else enforced it either: the input has no native
            // `required`, the wizard has no <form> for the browser to validate, and the
            // isolated sub-step gate returned true for phone unconditionally. Leads reached
            // `booked` with no number at all.
            'phone' => 'required|string|max:30',
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
        return in_array($this->monthlyRevenue, LeadQualification::SUB_T10_BANDS, true);
    }

    public function isJobSeeker(): bool
    {
        return str_contains(strtolower($this->monthlyRevenue), 'job');
    }

    public function updatedMonthlyRevenue(): void
    {
        // A different band is a different warning decision.
        $this->warningAcknowledged = false;

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
                $this->validate($this->validationRules[$this->currentStep], $this->validationMessages);
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

                $emailCheck = app(EmailValidationService::class)->validate($this->email, $this->ipAddress);

                if (! $emailCheck['valid']) {
                    /*
                     * Record it before returning. This branch is the *only* trace a refused
                     * visitor leaves: capturePartialLead() is a few lines below and never runs,
                     * so without this row there is no lead, no activity log and no way to tell
                     * "the booking form is broken" apart from "the email check refused them".
                     */
                    app(RecordBouncedLeadAction::class)->execute($this->email, $emailCheck, [
                        'name' => trim($this->name) ?: trim($this->firstName.' '.$this->lastName),
                        'phone' => $this->phone,
                        'company' => $this->company,
                        'ip_address' => $this->ipAddress,
                        'posthog_session_id' => $this->posthogSessionId,
                        'utm_source' => $this->utmSource,
                        'utm_medium' => $this->utmMedium,
                        'utm_campaign' => $this->utmCampaign,
                        'referral_code' => $this->referralCode,
                        'role_needed' => $this->roleNeeded,
                        'monthly_revenue' => $this->monthlyRevenue,

                        /*
                         * The ad identifiers, and the collected attribution the `fbc` rules need.
                         *
                         * Passing `attributionNamed`/`attribution` rather than a bare `fbc` is the
                         * point: the wizard's copy was frozen in `mount()`, before Meta's pixel JS
                         * ran, so a visitor who arrived on a bare `fbclid` is holding a *synthetic*
                         * `fbc` here. RecordBouncedLeadAction runs it through the same FbcResolver
                         * CaptureLeadAction uses, which prefers the live `_fbc` cookie that has
                         * almost certainly landed by now and rejects one from a different click.
                         * Recording the frozen value instead would file a paid click under a
                         * fabricated identifier and call it real.
                         */
                        'gclid' => $this->gclid,
                        'fbclid' => $this->fbclid,
                        'msclkid' => $this->attributionNamed['msclkid'] ?? '',
                        'landing_url' => $this->landingUrl,
                        'attribution_named' => $this->attributionNamed,
                        'attribution' => $this->attribution,
                    ]);

                    $this->addError('email', (string) $emailCheck['message']);

                    return;
                }

                // Parity with rl-testing capture_partial_lead on Step 1 completion
                $this->capturePartialLead();

                /*
                 * Checked before the pricing warning, because it answers a different question
                 * and answers it first. Telling somebody who wants a VA job that our recruiting
                 * fee might be beyond their budget is a conversation about the wrong product.
                 */
                if (! $this->applicantNoticeDismissed && $this->applicantNotice() !== null) {
                    $this->showApplicantNotice = true;
                    $this->trackStepEvent('va_applicant_notice_shown', ['lead_id' => $this->leadId]);

                    return;
                }

                /*
                 * The revenue-band warning, shown before the calendar rather than after booking.
                 * The partial lead is captured first on purpose: someone who reads the pricing
                 * and leaves is still a lead worth having, and is exactly who the Slack alert
                 * exists to surface.
                 */
                if (! $this->warningAcknowledged && $this->warningForBand() !== null) {
                    $this->showWarning = true;
                    $this->trackStepEvent('pricing_warning_shown', ['band' => $this->monthlyRevenue]);

                    return;
                }

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

                    /*
                     * Update the lead this visit already created rather than making another.
                     *
                     * Step 1 runs more than once whenever the revenue-band warning sends
                     * someone back to change their answer. Without this, each pass wrote a new
                     * row: two Slack cards, two Meta `Lead` conversions for one person (the
                     * event id is derived from the lead uuid, so Meta cannot collapse them),
                     * two admin emails — and the first row left behind forever as a partial
                     * drop-off that nobody dropped off from. submitBooking() has always passed
                     * this; the partial capture simply never did.
                     */
                    'lead_id' => $this->leadId ?? $this->resumableLeadId(),
                ],
                'attribution_named' => $this->attributionNamedFor('Partial'),
                'attribution' => $this->attribution,
            ]);

            $lead = $captureAction->execute($leadData);
            $this->leadId = $lead->id;

            // Identify before the event, so `partial_form_submitted` is the first thing on the
            // identified person rather than the last thing on the anonymous one.
            $this->identifyInBrowser();
            $this->trackStepEvent('partial_form_submitted', ['lead_id' => $lead->id]);
        } catch (\Throwable $e) {
            Log::warning('Could not capture partial lead on Step 1: '.$e->getMessage());
            $this->reportException($e);
        }
    }

    /**
     * The lead row this visit already created, when the component itself has forgotten it.
     *
     * `$leadId` lives in the Livewire snapshot, so a page reload loses it while the visit
     * carries on — same person, same browser, same answers, a minute later. Without this,
     * filling step 1 again after a refresh forks the lead exactly as the revenue-band warning
     * used to.
     *
     * Keyed on the address plus PostHog's session id, **not** on `$sessionId`: that one is
     * minted in `mount()`, so it is a per-component-instance value and a reload produces a new
     * one — precisely the case this exists to cover. PostHog's survives the reload and expires
     * with the visit, which is the notion of "session" wanted here. Where PostHog is blocked
     * there is no session id to match on and the address alone carries it, inside the window.
     *
     * A completed form is never resumed. Someone who books and then opens the form again is
     * starting something new, and threading that onto the booked lead's alert would bury it.
     *
     * Never throws — a capture that cannot look up its predecessor still captures.
     */
    protected function resumableLeadId(): ?int
    {
        $email = strtolower(trim((string) $this->email));

        if ($email === '') {
            return null;
        }

        $posthog = trim((string) $this->posthogSessionId);

        try {
            $id = Lead::query()
                ->where('email', $email)
                ->where('created_at', '>=', Carbon::now()->subMinutes(self::RESUME_WINDOW_MINUTES))
                ->whereRaw("LOWER(TRIM(COALESCE(submission_type, ''))) <> 'final'")
                ->when($posthog !== '', fn ($query) => $query->where('posthog_session_id', $posthog))
                ->latest('id')
                ->value('id');

            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            Log::warning('Could not look up a resumable lead for this visit: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Send a swallowed exception to Sentry.
     *
     * Both catch blocks in this component deliberately keep the visitor moving, which means
     * nothing else raises the alarm: Sentry's automatic reporting only sees *uncaught*
     * exceptions, `enable_logs` is off, and there is no Sentry log channel — so `Log::error()`
     * becomes a breadcrumb attached to no event. The only surviving trace was a line in
     * `laravel.log`, which is truncated on every container start.
     *
     * That is how a 212-character `fbclid` silently dropped every paid-social lead for as long
     * as it did: the form said "an error occurred", the dashboard simply looked quiet, and no
     * alert existed anywhere in between.
     *
     * Guarded and never rethrown: reporting a failure must not become a second failure, and in
     * the bare container the unit tests build, `sentry` is not bound at all.
     */
    protected function reportException(\Throwable $e): void
    {
        try {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        } catch (\Throwable) {
            // Reporting is best-effort by definition.
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

    /**
     * Adopt the browser's own zone on load. Nothing detected one before: every visitor
     * was shown New York times regardless of where they were, and the only way to see
     * their own was to notice the dropdown and use it.
     *
     * Ignored once the visitor has chosen a zone, and ignored for anything that is not
     * a real zone identifier — the value arrives from the client and reaches Calendly.
     */
    public function detectTimezone(string $tz): void
    {
        if ($this->timezoneChosen || $tz === '' || $tz === $this->timezone) {
            return;
        }

        if (! in_array($tz, timezone_identifiers_list(), true)) {
            return;
        }

        $this->timezone = $tz;

        $now = Carbon::now($this->timezone);
        $this->currentMonth = (int) $now->format('n');
        $this->currentYear = (int) $now->format('Y');

        $this->loadMonthAvailability();

        if ($this->selectedDate) {
            $this->loadSlotsForDate($this->selectedDate);
        }
    }

    /**
     * The offered zones, with the visitor's own prepended when it is not one of them —
     * a <select> that cannot represent its current value displays a different zone than
     * the one the times are actually in.
     */
    public function timezoneChoices(): array
    {
        $choices = self::TIMEZONE_CHOICES;

        if (! in_array($this->timezone, $choices, true)) {
            array_unshift($choices, $this->timezone);
        }

        return $choices;
    }

    public function timezoneLabel(string $tz): string
    {
        return self::TIMEZONE_LABELS[$tz] ?? str_replace(['_', '/'], [' ', ', '], $tz);
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
        $this->timezoneChosen = true;
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

            /*
             * A booking the scheduling domain has given up on — today that means the slot was
             * taken between this visitor rendering the picker and submitting it, which Calendly
             * answers with `already_filled`.
             *
             * Everything below assumes a meeting exists. It sets a confirmation, then redirects
             * to /VAThankYou/, which fires GA4 `appointment_booked` and two live Google Ads
             * conversions. Sending a failed booking down that path tells someone to expect a
             * call nobody is going to make, and trains Ads bidding on a conversion that never
             * happened. Both were happening until 2026-09-20.
             *
             * A *transient* failure still shows the confirmation on purpose: the retry ladder
             * has the slot and will usually land it within thirty seconds. Only a failure marked
             * as final gets this branch.
             */
            if (! empty($this->selectedSlot) && $lead->status === 'booking_failed') {
                $takenSlot = $this->selectedSlot;

                $this->isBooked = false;
                $this->selectedSlot = null;
                $this->currentStep = 3;
                $this->errorMessage = 'That time was booked by someone else moments before you confirmed. These are the times still open — please pick another.';

                if ($this->selectedDate) {
                    // The cached day still lists the slot that has just gone.
                    Cache::forget('rl_avail_slots_'.md5($this->getActiveEventTypeUri().$this->selectedDate.$this->timezone));
                    $this->loadSlotsForDate($this->selectedDate);
                }

                $this->trackStepEvent('booking_failed', [
                    'lead_id' => $lead->id,
                    'selected_time' => $takenSlot,
                    'role' => $this->roleNeeded,
                ]);

                return;
            }

            /*
             * A slot was chosen but no provider has taken it yet — the retry ladder still has it.
             *
             * This used to render the confirmation anyway, on the reasoning that the ladder
             * usually lands within thirty seconds. It usually does. When it does not, the visitor
             * has already been told a consultant is expecting them and sent to /VAThankYou/,
             * which fires two live Google Ads conversions; nothing afterwards ever corrects
             * either. Between 19 and 21 September five people were told exactly that for meetings
             * that existed on no calendar.
             *
             * So an unconfirmed booking now says it is unconfirmed. It stays in the widget rather
             * than redirecting, which also keeps the conversion out of Ads until there is a
             * meeting to report.
             */
            if ($lead->status !== 'booked' && ! empty($this->selectedSlot)) {
                $this->isBooked = false;
                // Pinned rather than assumed. The visitor is on step 3 when they submit, so this
                // is almost always already 3 — but "almost always" is how the confirmation came
                // to be shown for a booking that had not happened, and the sibling branch above
                // pins it for the same reason.
                $this->currentStep = 3;
                $this->errorMessage = 'We have your time and we are still confirming it with the calendar. '
                    .'You will get an email as soon as it is confirmed — if nothing arrives within a few minutes, '
                    .'please pick another time.';

                $this->trackStepEvent('booking_pending', [
                    'lead_id' => $lead->id,
                    'selected_time' => $this->selectedSlot,
                    'role' => $this->roleNeeded,
                ]);

                return;
            }

            if ($lead->status === 'booked') {
                $this->isBooked = true;
                $bookingLog = $lead->activityLogs()
                    ->where('event_type', 'LeadCreated')
                    ->where('stage', 'consumption')
                    ->first();
                $payload = $bookingLog?->payload ?? [];

                // Null, not an invented room. The confirmation view already hides the link
                // when there is none, and a retry that lands will write the real one.
                $this->meetingUrl = $payload['meet_url'] ?? null;
                $this->bookingReference = (string) ($payload['meeting_id'] ?? $lead->uuid);
                // Same conversion bug as the slot labels, and worse here: this one appends
                // the timezone name to a time it never converted into that timezone.
                $this->confirmedTime = $this->selectedSlot
                    ? Carbon::parse($this->selectedSlot)->setTimezone($this->timezone)->format('l, F j, Y \a\t g:i A').' ('.$this->timezone.')'
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

            /*
             * Hand the thank-you page what it needs for Google Ads enhanced conversions. The
             * conversion itself is emitted there (see ConversionHooks), and that page has no
             * other way to know which lead it is confirming — the redirect carries no id, and
             * putting one in the URL would leak it to every pixel on the page via
             * `page_location`.
             *
             * `/VAThankYou/` is excluded from the HTML cache in docker/nginx.conf for this
             * reason: it now renders per-lead hashed data, and a shared cache entry would serve
             * one booker's identifiers — and one booker's transaction_id — to everybody else in
             * the TTL window.
             */
            session()->put(
                GoogleEnhancedConversion::SESSION_KEY,
                GoogleEnhancedConversion::payload($lead),
            );

            /*
             * A successful booking now navigates to a dedicated thank-you page (with its own
             * "confirm via email" steps and social proof) rather than rendering a confirmation
             * state inline in the widget.
             *
             * **The capitalisation is load-bearing.** GTM container GTM-53JDTQCZ fires GA4
             * `appointment_booked`, Google Ads conversions `AyW6CJHZnr8ZEOWU8r4q` and
             * `pTbrCP6-_dIbEOWU8r4q`, and the PostHog `appointment_booked_web` capture off a
             * `Page Path contains VAThankYou` trigger. That predicate compiles to a bare `_cn`
             * with no ignore-case flag, so it is case-sensitive.
             *
             * Gravity Forms redirected to `/VAThankYou`, which WordPress serves as
             * `/VAThankYou/` with the capitals intact — so the trigger has always matched. A
             * lowercase `/vathankyou/` serves the identical page and silently matches nothing,
             * taking the primary conversion event and two live Ads conversions with it.
             *
             * Audited 2026-09-17. See `docs/domains/tracking.md`.
             */
            $this->redirect(home_url('/VAThankYou/'));

            return;
        } catch (\Throwable $e) {
            Log::error('Error executing booking in wizard: '.$e->getMessage(), ['exception' => $e]);
            $this->reportException($e);
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
            $monthKey = $start->format('Y-m');

            $cacheKey = 'rl_avail_dates_'.md5($this->getActiveEventTypeUri().$monthKey.$this->timezone);

            $cached = Cache::get($cacheKey);
            $fetched = false;

            if (is_array($cached)) {
                $this->availableDates = $cached;
            } else {
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
                $fetched = true;

                Cache::put($cacheKey, $this->availableDates, self::availabilityTtl($this->availableDates));
            }

            $this->refreshNextAvailableDate();

            /*
             * Reported after the next-available lookup, because that is what separates a sold-out
             * tier from a month the rolling booking window simply has not reached yet — and only
             * on a real fetch, so a cached empty does not re-report a sell-out on every pageview.
             */
            if ($fetched) {
                $role = $this->activeTierRole();

                app(AvailabilityHealthMonitor::class)->record(
                    $role,
                    $this->getActiveEventTypeUri(),
                    $this->availableDates,
                    $monthKey,
                    $this->nextAvailableDate
                );

                /*
                 * The leading indicator, measured off the same visit but never in front of it.
                 * Counting how full the window is means paging Calendly's scheduled_events, and
                 * the booking widget is the last request on the site that should wait on one —
                 * so it goes after the response, like the live-call Slack alerts do. The probe
                 * throttles itself, so a busy hour measures once rather than once per visitor.
                 *
                 * `Deferred::call` rather than a closure: it queues where a queue is configured
                 * and falls back to the same after-response dispatch everywhere else, so this
                 * behaves identically until an environment sets QUEUE_CONNECTION. Only the role
                 * string crosses the boundary — no `$this`, because serialising a Livewire
                 * component would drag the whole form state along with it.
                 *
                 * First site converted, and chosen for being the cheapest to be wrong about:
                 * internal telemetry, self-throttling, and nothing a visitor or a customer ever
                 * sees.
                 */
                Deferred::call(TierUtilizationProbe::class, 'probe', [$role]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load month availability: '.$e->getMessage());
            $this->availableDates = [];
        }
    }

    /**
     * The soonest bookable date on this tier, in any month, or null when there is none.
     *
     * Only consulted when the displayed month came back empty, because that is the only time
     * the answer is worth a request. Calendly's booking window is a rolling few days, so an
     * empty month is the normal state of an otherwise healthy calendar viewed from far
     * enough away — "fully booked" with no date attached reads as broken, and is the message
     * that sent the 2026-09-17 sell-out to engineering instead of to sales.
     */
    protected function refreshNextAvailableDate(): void
    {
        if (! empty($this->availableDates)) {
            /*
             * Already on screen — no request needed, but still worth setting rather than
             * nulling. The empty state never renders this branch, so the only reader is
             * AvailabilityHealthMonitor, and a null here would have the admin panel print
             * "no bookable dates" beside a tier it had just marked bookable.
             */
            $dates = $this->availableDates;
            sort($dates);
            $this->nextAvailableDate = $dates[0];

            return;
        }

        try {
            $cacheKey = 'rl_avail_next_'.md5($this->getActiveEventTypeUri().$this->timezone);

            /*
             * A miss and a cached "nothing at all" are both meaningful and have to be
             * distinguishable. Null cannot carry that: Laravel's cache repository returns the
             * *default* for a stored null, so caching null would read back as a miss forever
             * and re-hit Calendly on every pageview — precisely when the tier is sold out and
             * the calendar is busiest. Empty string is the stored form of "none".
             */
            $cached = Cache::get($cacheKey, false);

            if ($cached !== false) {
                $this->nextAvailableDate = $cached === '' ? null : $cached;

                return;
            }

            // Nulls let FetchAvailableSlotsAction pick its own window: now through +30 days,
            // which is past any horizon Calendly will answer for.
            $slots = app(FetchAvailableSlotsAction::class)->execute(
                null,
                null,
                $this->timezone,
                $this->getActiveEventTypeUri()
            );

            $dates = [];
            foreach ($slots as $slot) {
                $dates[] = Carbon::parse($slot->startTime)->setTimezone($this->timezone)->format('Y-m-d');
            }

            sort($dates);

            $this->nextAvailableDate = $dates[0] ?? null;

            Cache::put(
                $cacheKey,
                $this->nextAvailableDate ?? '',
                self::availabilityTtl($dates)
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve next available date: '.$e->getMessage());
            $this->nextAvailableDate = null;
        }
    }

    /**
     * Move the calendar to the soonest bookable date and open it.
     */
    public function jumpToNextAvailable(): void
    {
        if (empty($this->nextAvailableDate)) {
            return;
        }

        $target = Carbon::parse($this->nextAvailableDate, $this->timezone);

        $this->currentMonth = (int) $target->format('n');
        $this->currentYear = (int) $target->format('Y');

        $this->loadMonthAvailability();
        $this->selectDate($target->format('Y-m-d'));
    }

    /**
     * How long an availability answer may be reused.
     *
     * An empty answer is the perishable one. Availability on these calendars is consumed and
     * released continuously — twelve bookings landed on `t10` during the five hours it was
     * reporting empty on 2026-09-17 — so a ten-minute hold on "nothing here" converts every
     * momentary sell-out into a ten-minute outage for everyone routed to that tier. A
     * populated answer going stale by the same margin costs a visitor one rejected slot at
     * submit time, which the booking path already handles.
     *
     * @param  array<int, string>  $result
     */
    protected static function availabilityTtl(array $result): int
    {
        return empty($result) ? self::EMPTY_AVAILABILITY_TTL_SECONDS : self::AVAILABILITY_TTL_SECONDS;
    }

    /**
     * Which revenue tier the current answers route to.
     */
    public function activeTierRole(): string
    {
        return $this->isUnder10kMrr() ? 't0' : 't10';
    }

    public function loadSlotsForDate(string $date): void
    {
        try {
            $slotsAction = app(FetchAvailableSlotsAction::class);
            $start = Carbon::parse($date, $this->timezone)->startOfDay();
            $end = $start->copy()->endOfDay();

            $cacheKey = 'rl_avail_slots_'.md5($this->getActiveEventTypeUri().$date.$this->timezone);

            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $this->availableSlots = $cached;
            } else {
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
                        // setTimezone, not parse($iso, $tz). Calendly returns UTC Zulu and
                        // ignores the timezone param, and PHP discards the timezone argument
                        // whenever the string carries its own offset — so parse($iso, $tz)
                        // silently labels every slot in UTC under a banner naming the
                        // visitor's timezone. That is how a 15:45Z slot was offered as
                        // "3:45pm" and booked at 10:45 Central on 2026-09-21.
                        'time' => Carbon::parse($slot->startTime)->setTimezone($this->timezone)->format('g:ia'),
                    ];
                }

                $this->availableSlots = $times;

                // A day that just sold out is the single most perishable answer here: the
                // visitor is looking at it, and the next slot to free up is the one they
                // wanted. Ten minutes of "no times available" on a day that has them again
                // is a lost booking.
                Cache::put($cacheKey, $times, self::availabilityTtl($times));
            }
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
     * ## Where each destination is fired from, and why they differ
     *
     * **PostHog is captured in the browser**, by `capturePostHog()` below. It was briefly sent
     * server-side, on the reasoning that a server call cannot be ad-blocked. That traded away
     * the thing PostHog is built around: the browser owns the identity. A server event carries
     * no person, no `$current_url`, no `$lib`, and no link to the session recording unless the
     * component first ships the browser's distinct id back to PHP — which is a round trip the
     * component loses, every time, for `form_loaded`. The result was a funnel of orphan events
     * under throwaway UUIDs and an empty Person column, against a legacy funnel whose rows all
     * carried the visitor's email. Reverted 2026-09-18.
     *
     * **Customer.io stays server-side**, deferred, because its Track API is keyed to credentials
     * that belong nowhere near a browser, and because it is identified by email rather than by
     * a browser-held id, so it loses nothing by being sent from here.
     */
    protected function trackStepEvent(string $eventName, array $properties = []): void
    {
        try {
            $payload = array_merge([
                'form_id' => (string) $this->getId(),
                'session_id' => $this->sessionId,
                'form_type' => 'multistep',
                'is_isolated' => ! empty($this->isolatedSteps),
                'step' => $this->currentStep,
                'selected_date' => $this->selectedDate,
                'selected_slot' => $this->selectedSlot,
                'role_needed' => $this->roleNeeded,
            ], $properties);

            /*
             * Deferred, not inline.
             *
             * `CustomerIOClient::track()` is an outbound HTTP call. Run inline it sat *inside
             * the Livewire round trip*, so a keystroke on a `wire:model.live` field waited on a
             * third-party endpoint before the component could respond. The visible symptom is
             * the form eating characters: the response lands late and patches the DOM over what
             * was typed meanwhile.
             */
            /*
             * No email, no Customer.io. `CustomerIOClient::track()` enforces this for every
             * caller — see it for why a stand-in id is not a placeholder but a new profile —
             * and the check is repeated here only to avoid queuing a deferred HTTP job that is
             * already known to be dropped. `form_loaded` fires from mount(), on the front page,
             * every post and the booking footer, so that is most of the site's traffic.
             *
             * The anonymous steps are not lost: capturePostHog() below still records them.
             */
            $distinctId = $this->resolveDistinctId();

            if ($distinctId !== '') {
                $event = new AnalyticsEventData(
                    event: $eventName,
                    distinctId: $distinctId,
                    properties: $payload,
                );

                $this->deferTracking(static fn () => app(CustomerIOClient::class)->track($event));
            }

            // Browser-side effects last, and each one isolated: a Livewire lifecycle that
            // cannot accept a `js()` effect must not take the Customer.io dispatch with it.
            $this->capturePostHog($eventName, $payload);
            $this->pushToDataLayer($eventName, $properties);
        } catch (\Throwable $e) {
            // Analytics must never break a booking. It must not vanish either — this catch
            // hid a funnel that had been dead since cutover, so the exception goes to Sentry.
            $this->reportException($e);
        }
    }

    /**
     * Capture a funnel event in the visitor's browser, through the PostHog snippet.
     *
     * `js()` reaches the browser on every lifecycle the component has, including the initial
     * server render — Livewire serialises the call into `wire:effects.xjs`, which is why
     * `form_loaded` works here despite having no round trip of its own to ride on.
     *
     * The snippet in `TrackingHooks::injectPostHogSnippet()` installs a queueing stub that
     * accepts `capture` before `array.js` has landed, so there is no load order to wait for.
     * When PostHog is blocked or absent, `window.posthog` is simply undefined and the guard
     * drops the call — the same outcome the legacy form had, and the honest one: the visitor
     * opted out.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function capturePostHog(string $eventName, array $properties = []): void
    {
        try {
            $this->js($this->postHogCaptureExpression($eventName, $properties));
        } catch (\Throwable $e) {
            // No Livewire lifecycle to attach the effect to (a bare unit test, a console call).
        }
    }

    /**
     * Identify the visitor to PostHog, by email, from the browser.
     *
     * Legacy did this and it is why every row in the funnel had a person against it. Nothing in
     * v2 did it for the booking form until 2026-09-18 — `posthog.identify()` appeared exactly
     * once in the theme, in the checkout block — so booking events could only ever have landed
     * on anonymous profiles. `person_profiles: 'identified_only'` means the browsing session has
     * no profile to merge into either, so without this call there is no person at all.
     *
     * Called at partial capture: the email has been validated by then, and it is the same
     * moment the legacy form identified. PostHog aliases the anonymous id to the email itself,
     * so everything already captured in this session follows the person over.
     */
    protected function identifyInBrowser(): void
    {
        if ($this->email === '' || $this->identifiedInBrowser) {
            return;
        }

        $this->identifiedInBrowser = true;

        try {
            $this->js($this->postHogIdentifyExpression());
        } catch (\Throwable $e) {
            // As above: no lifecycle to carry the effect.
        }
    }

    /**
     * The `posthog.capture()` call for one funnel event.
     *
     * Built as a string rather than inlined so it can be asserted on directly — the whole point
     * of moving back to the browser is the shape of this call, and a unit test cannot observe a
     * Livewire `js()` effect.
     *
     * `$session_id` is deliberately not forwarded: captured here, the browser stamps its own
     * session, URL and library onto the event. Nulls are stripped so an untouched field does not
     * land in PostHog as a property with no value.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function postHogCaptureExpression(string $eventName, array $properties = []): string
    {
        $properties = array_filter($properties, static fn ($value) => $value !== null);

        return sprintf(
            'if (window.posthog && typeof window.posthog.capture === "function") { window.posthog.capture(%s, %s); }',
            json_encode($eventName, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode((object) $properties, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The `posthog.identify()` call, with the person properties the funnel reports on.
     *
     * Only what a funnel or a cohort is built from. `phone` is not among them: it buys nothing
     * in PostHog that the Lead row does not already hold, and person properties are readable by
     * anyone with project access.
     */
    protected function postHogIdentifyExpression(): string
    {
        $traits = array_filter([
            'email' => $this->email,
            'name' => $this->name,
            'role_needed' => $this->roleNeeded,
            'monthly_revenue' => $this->monthlyRevenue,
        ], static fn ($value) => $value !== null && $value !== '');

        return sprintf(
            'if (window.posthog && typeof window.posthog.identify === "function") { window.posthog.identify(%s, %s); }',
            json_encode($this->email, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode((object) $traits, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Mirror a funnel event into the browser's `dataLayer`, for GTM.
     *
     * ## Why this exists at all
     *
     * The container has a trigger on `{{Event}} equals form_submit` firing GA4 `generate_lead`
     * and Google Ads conversion `oqW3CP3jnJcbEOWU8r4q`. On the Elementor site that event came
     * from gtag.js noticing a **native** Gravity Forms submission — there is no form-submit
     * listener tag in the container, so nothing else could have produced it.
     *
     * A Livewire wizard never submits a form natively. Without this, that trigger simply never
     * fires again and a live Google Ads conversion goes quiet at cutover with no error anywhere.
     * Audited 2026-09-17; see `docs/domains/tracking.md`.
     *
     * ## Why every event, not just that one
     *
     * The legacy names are already the contract that PostHog funnels and Customer.io campaigns
     * are keyed to (see {@see trackStepEvent}). Publishing the whole set to `dataLayer` means a
     * new GTM trigger on any funnel step is a change someone makes in the container, not a
     * deploy — which is the entire reason for having a tag manager. Pushing only the one event
     * we happen to need today guarantees this method gets edited again.
     *
     * The server-side dispatch above stays the source of truth: it is immune to ad-blockers.
     * This is additive, and deliberately carries no personal data — the properties here are the
     * same funnel metadata, and `email`/`phone` are never among them.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function pushToDataLayer(string $eventName, array $properties = []): void
    {
        /*
         * `form_submit` is the legacy alias, not a rename. `partial_form_submitted` is the
         * moment the visitor completed step 1 and a lead row was written, which is exactly when
         * the Gravity form used to submit natively — so it is the honest equivalent of what the
         * container's trigger has always meant by a form submission.
         */
        $names = $eventName === 'partial_form_submitted'
            ? [$eventName, 'form_submit']
            : [$eventName];

        $payload = array_merge([
            'form_id' => (string) $this->getId(),
            'form_type' => 'multistep',
            'step' => $this->currentStep,
        ], array_filter(
            $properties,
            static fn ($value, string $key): bool => is_scalar($value) && ! in_array($key, ['email', 'phone'], true),
            ARRAY_FILTER_USE_BOTH,
        ));

        foreach ($names as $name) {
            $this->js(sprintf(
                'window.dataLayer = window.dataLayer || []; window.dataLayer.push(%s);',
                json_encode(
                    array_merge(['event' => $name], $payload),
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
            ));
        }
    }

    /**
     * The warning configured for the selected revenue band, or null when there is none.
     *
     * @return array<string, mixed>|null
     */
    public function warningForBand(): ?array
    {
        /*
         * Indexed, not dot-path: `config('booking.revenue_bands.'.$label)` splits the label on
         * dots, so a band name containing one would silently resolve to nothing. The labels are
         * marketing copy and will change without anyone thinking about Laravel's dot notation.
         */
        $bands = config('booking.revenue_bands', []);
        $band = is_array($bands) ? ($bands[$this->monthlyRevenue] ?? null) : null;

        if (! is_array($band) || empty($band['show_warning'])) {
            return null;
        }

        return $band;
    }

    /**
     * The VA-applicant notice's copy, or null when this visitor does not look like one.
     *
     * Who counts as a possible VA is {@see LeadAudience}'s decision, not
     * this component's — the same rule that draws the "Possible VA" badge, the audience filter
     * and the export column on the leads dashboard. Asking it rather than re-deriving it means
     * the widget and the dashboard can never disagree about the same person.
     *
     * Read off the partial lead rather than off this component's own fields, because the rule
     * includes a referral override and `source_type` is resolved inside CaptureLeadAction — the
     * wizard does not know it. `capturePartialLead()` has already run by the time step 1 reaches
     * this gate, so the row is there; when it is not (capture skipped or failed), the notice
     * stays down, which is the right way to be wrong.
     *
     * @return array<string, mixed>|null
     */
    public function applicantNotice(): ?array
    {
        $config = (array) config('booking.applicant_notice', []);

        if (empty($config['enabled']) || empty($config['jobs_url']) || empty($this->leadId)) {
            return null;
        }

        $lead = Lead::find($this->leadId);

        return $lead?->audience()->label() === null ? null : $config;
    }

    /**
     * "No, I'm here to hire a VA." The inference was wrong, so carry on to the calendar.
     *
     * Advances directly rather than through `goToStep(2)`, for the reason spelled out in
     * {@see self::acknowledgeWarning()}: step 1's gates have already run, and re-running them
     * spends a second email-verification credit and writes a second partial capture.
     */
    public function dismissApplicantNotice(): void
    {
        if (! $this->showApplicantNotice) {
            return;
        }

        $this->trackStepEvent('va_applicant_notice_dismissed');

        $this->applicantNoticeDismissed = true;
        $this->showApplicantNotice = false;

        if ($this->skipCalendar) {
            $this->submitBooking();

            return;
        }

        $this->currentStep = 2;
        $this->loadMonthAvailability();
        $this->trackStepEvent('step_date_selection');
        $this->trackStepEvent('step_viewed', ['step' => 2]);
    }

    /**
     * Back out of the applicant notice to step 1, as the pricing warning's arrow does.
     */
    public function backFromApplicantNotice(): void
    {
        $this->showApplicantNotice = false;
        $this->currentStep = 1;

        // Backing out is not "I'm hiring". Someone who returns to correct the field that
        // triggered this must be able to see it again.
        $this->applicantNoticeDismissed = false;
    }

    /**
     * They took the jobs link. Recorded server-side so the handoff is measurable against
     * `va_applicant_notice_shown` rather than inferred from a gap.
     */
    public function trackApplicantJobsClick(): void
    {
        $this->trackStepEvent('va_applicant_jobs_opened');
    }

    /**
     * The visitor read the pricing and chose to continue.
     */
    public function acknowledgeWarning(): void
    {
        if (! $this->showWarning) {
            return;
        }

        $this->trackStepEvent('pricing_warning_accepted', ['band' => $this->monthlyRevenue]);

        $this->warningAcknowledged = true;
        $this->showWarning = false;

        /*
         * Advance directly rather than through `goToStep(2)`.
         *
         * Step 1's gates — validation, phone formatting, email verification, partial capture —
         * all ran before the warning was shown. Routing back through `goToStep()` would run
         * them a second time: a second ZeroBounce credit spent on an address just verified, and
         * a second partial capture for a lead already recorded. Reaching this method is proof
         * they passed.
         */
        $this->currentStep = 2;
        $this->loadMonthAvailability();
        $this->trackStepEvent('step_date_selection');
        $this->trackStepEvent('step_viewed', ['step' => 2]);
    }

    /**
     * Back out of the warning to step 1, as the legacy form's arrow did.
     */
    public function dismissWarning(): void
    {
        $this->showWarning = false;
        $this->currentStep = 1;

        // Not acknowledged — backing out is the opposite of accepting. Someone who returns to
        // change their revenue band must see the warning again if the new band warrants it.
        $this->warningAcknowledged = false;
    }

    /**
     * Run analytics after the response has been flushed.
     *
     * `afterResponse()` registers a terminating callback, which only a full Application has. In
     * a bare container — how the unit tests build the component — there is nothing to register
     * against and the callback is silently dropped, which would make this whole funnel
     * untestable *and* look like it worked. Falling back to an inline call there keeps the
     * behaviour observable without changing what production does.
     */
    protected function deferTracking(\Closure $callback): void
    {
        if (method_exists(app(), 'terminating')) {
            dispatch($callback)->afterResponse();

            return;
        }

        $callback();
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

    /**
     * Who the deferred Customer.io event is about.
     *
     * Email first, because Customer.io is identified by email everywhere else in this codebase
     * — `HandleLeadCreatedForTracking` calls `identify()` with it, and the lead-scoring map is
     * keyed to that person. Anything sent under a different id creates a second profile that no
     * campaign will ever match.
     *
     * There is deliberately **no fallback**. This returned the Laravel session id when the email
     * was still empty, and the Track API turned each of those into a real, emailless person —
     * 40-character ids filling the workspace, none of them reachable by any campaign, and never
     * merged into the real profile once the email finally arrived and the id changed under them.
     * An empty string here means "nobody to send this to yet", and the caller drops the event.
     *
     * It also used to prefer PostHog's browser distinct id, from back when the same payload was
     * dual-dispatched to PostHog as well. PostHog is captured in the browser now, so that id has
     * no reader here.
     */
    protected function resolveDistinctId(): string
    {
        return $this->email;
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
