<?php

declare(strict_types=1);

namespace App\Application\Livewire\Partner;

use App\Domains\Lead\Services\AttributionCollector;
use App\Domains\PartnerHub\Actions\SubmitPartnershipProspectAction;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipCallCalendar;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The form at the foot of `/become-a-partner/`, then the calendar to book the partnership call.
 *
 * Mounted with `<livewire:partner.partnership-prospect-form :lazy="false" />`. Not `#[Lazy]`,
 * unlike the booking wizard: it sits at the bottom of a long page, and a lazy placeholder there
 * is one that never hydrates (see the note in the booking-footer block).
 *
 * ## Two states after submit
 *
 *  - **A partnership calendar is configured** (Partnership Settings): the card becomes
 *    that Calendly page, prefilled with the name and email just typed, so booking is one click
 *    rather than a second form. When Calendly reports the booking, `recordBooking()` stamps it
 *    on the prospect.
 *  - **None is**: a short thank-you in place of the form. The prospect is saved and announced
 *    either way; the calendar is a convenience on top of that, not a condition of it.
 *
 * ## Spam
 *
 * The same two defences the other public forms here use, and no new ones: a per-IP throttle
 * held in the cache (SalesReferralForm's), and the Leads email gate inside the action. There is
 * no honeypot anywhere on the site to reuse, and inventing one here would make this the only
 * form with a rule the others do not share.
 */
class PartnershipProspectForm extends Component
{
    /**
     * Attempts allowed from one IP inside the window below — attempts, not successes, because
     * each one that passes the field rules spends a ZeroBounce check.
     *
     * Half SalesReferralForm's twenty: that is a rep working a list from a shared office IP, and
     * this is a public form nobody sends more than once. Ten still leaves a real visitor room to
     * fix several validation errors in a row without being locked out.
     */
    public const MAX_SUBMISSIONS = 10;

    public const THROTTLE_MINUTES = 10;

    /**
     * Component property => the column the action validates, in the order the form asks.
     *
     * The action speaks column names and this component speaks camelCase properties, so its
     * validation errors are translated back through this map to land under the right field.
     */
    public const FIELDS = [
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'email' => 'email',
        'company' => 'company',
        'role' => 'role',
        'organizationType' => 'organization_type',
        'monthlyRevenue' => 'monthly_revenue',
        'businessesReached' => 'businesses_reached',
        'message' => 'message',
    ];

    public string $buttonText = 'Book a Partnership Call';

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $company = '';

    public string $role = '';

    public string $organizationType = '';

    public string $monthlyRevenue = '';

    public string $businessesReached = '';

    public string $message = '';

    public bool $submitted = false;

    /** Set when Calendly reports the call was booked. Nothing in the view depends on it yet. */
    public bool $booked = false;

    public ?string $errorMessage = null;

    /**
     * The row this visit created. Locked: `recordBooking()` writes to it, and a client that could
     * point it at another id could stamp a booking on somebody else's prospect.
     */
    #[Locked]
    public ?int $prospectId = null;

    /*
     * Attribution, read once in mount() from the page request. Every later request is a Livewire
     * update to `/livewire/update`, whose URL and referer say nothing about where the visitor
     * came from. Locked for the same reason as the id: these are recorded, not typed.
     */
    #[Locked]
    public string $landingUrl = '';

    #[Locked]
    public string $referrerUrl = '';

    /** @var array<string, string> */
    #[Locked]
    public array $utm = [];

    /** @var array<string, string> The rest of AttributionCollector's named set, click ids first. */
    #[Locked]
    public array $attribution = [];

    #[Locked]
    public ?string $ipAddress = null;

    public function mount(string $buttonText = 'Book a Partnership Call'): void
    {
        $this->buttonText = trim($buttonText) !== '' ? $buttonText : 'Book a Partnership Call';

        $request = app()->bound('request') ? app('request') : null;

        try {
            $collector = app(AttributionCollector::class);
            $collected = $collector->collect($request);
            $named = $collected['named'] ?? [];

            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
                $this->utm[$key] = (string) ($named[$key] ?? '');
                unset($named[$key]);
            }

            // The user agent rides in the same place the lead side keeps it — the attribution
            // blob, not a column.
            $this->attribution = array_filter([
                ...array_map('strval', $named),
                'user_agent' => (string) ($collected['attribution']['user_agent'] ?? ''),
            ], static fn (string $v) => $v !== '');
            $this->ipAddress = $collector->ipAddress($request);
            $this->landingUrl = (string) ($request?->fullUrl() ?? '');
            $this->referrerUrl = (string) ($request?->header('referer') ?? '');
        } catch (\Throwable $e) {
            // Attribution is worth having, never worth a form that fails to render.
            Log::warning('PartnershipProspectForm: could not read attribution: '.$e->getMessage());
        }
    }

    public function submit(): void
    {
        $this->errorMessage = null;
        $this->resetErrorBag();

        if ($this->submitted) {
            return;
        }

        $throttleKey = 'partnership_prospect_submissions:'.$this->throttleIp();

        if ((int) Cache::get($throttleKey, 0) >= self::MAX_SUBMISSIONS) {
            $this->errorMessage = 'Too many submissions from this location. Please try again in a few minutes.';

            return;
        }

        /*
         * The double-click guard, the booking wizard's shape: a disabled button only stops the
         * second click once the first response has re-rendered it, and two genuinely concurrent
         * requests both hydrate from the same snapshot with `submitted` still false. The lock is
         * shared across requests; the property is not.
         */
        $lock = Cache::lock('rl_partnership_prospect_'.md5(strtolower(trim($this->email))), 15);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->recordAttempt($throttleKey);

            $prospect = app(SubmitPartnershipProspectAction::class)->execute($this->fields(), [
                'landing_url' => $this->landingUrl,
                'referrer_url' => $this->referrerUrl,
                ...$this->utm,
                'ip_address' => $this->ipAddress,
                'context' => [
                    ...$this->attribution,
                    'source_form' => 'PartnershipProspectForm',
                ],
            ]);

            $this->prospectId = (int) $prospect->id;
            $this->submitted = true;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $column => $messages) {
                $property = array_search($column, self::FIELDS, true);
                $this->addError($property !== false ? $property : $column, (string) ($messages[0] ?? ''));
            }
        } catch (\Throwable $e) {
            Log::error('PartnershipProspectForm: could not save the prospect: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Something went wrong sending your details. Please try again, or email contact@remoteleverage.com.';
        } finally {
            $lock->release();
        }
    }

    /**
     * Calendly said the call is booked. Called from the embed's `calendly.event_scheduled`.
     *
     * It is the visitor's browser reporting, not Calendly's API, so both URIs are held to the
     * shapes Calendly actually issues before anything is written, and a prospect is only ever
     * stamped once. The worst a forged call can do is mark this visitor's *own* prospect as
     * booked — `prospectId` is locked, so it cannot reach anybody else's.
     *
     * Renderless: nothing on screen changes, and the iframe is mid-confirmation when this runs.
     */
    #[Renderless]
    public function recordBooking(string $eventUri, string $inviteeUri): void
    {
        if ($this->prospectId === null
            || ! PartnershipCallCalendar::isScheduledEventUri($eventUri)
            || ! PartnershipCallCalendar::isInviteeUri($inviteeUri)) {
            return;
        }

        try {
            $prospect = PartnershipProspect::query()->find($this->prospectId);

            if ($prospect && ! $prospect->hasBookedCall()) {
                $prospect->forceFill([
                    'booked_at' => now(),
                    'calendly_event_uri' => $eventUri,
                    'calendly_invitee_uri' => $inviteeUri,
                ])->save();
            }

            $this->booked = true;
        } catch (\Throwable $e) {
            // The call exists in Calendly regardless; this only loses our note of it.
            Log::warning('PartnershipProspectForm: could not record the booking: '.$e->getMessage(), [
                'prospect_id' => $this->prospectId,
            ]);
        }
    }

    /**
     * The inputs, keyed the way the action reads them.
     *
     * @return array<string, string>
     */
    protected function fields(): array
    {
        $fields = [];

        foreach (self::FIELDS as $property => $column) {
            $fields[$column] = $this->{$property};
        }

        return $fields;
    }

    /**
     * The Calendly iframe `src` for the state after submit, or null for the thank-you.
     */
    public function calendarUrl(): ?string
    {
        $base = PartnershipCallCalendar::url();

        if ($base === null) {
            return null;
        }

        $host = (string) (parse_url(function_exists('home_url') ? (string) home_url() : '', PHP_URL_HOST) ?: 'remoteleverage.com');

        return PartnershipCallCalendar::embedUrl($base, $host, [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            ...$this->utm,
        ]);
    }

    protected function recordAttempt(string $throttleKey): void
    {
        $attempts = (int) Cache::get($throttleKey, 0) + 1;

        Cache::put($throttleKey, $attempts, now()->addMinutes(self::THROTTLE_MINUTES));
    }

    /**
     * Who the limit counts against: the visitor IP captured at mount, not `request()->ip()`.
     *
     * Behind CloudFront and the ALB, `request()->ip()` is REMOTE_ADDR — an edge node shared by
     * thousands of visitors — so keying on it let ten junk submissions lock the form for everyone
     * arriving through that edge. `$ipAddress` is AttributionCollector's left-most
     * X-Forwarded-For entry, taken on the page load and #[Locked] so the client cannot change it.
     *
     * That entry is the one a forger controls, so a determined sender can dodge the limit by
     * sending their own header. That is the right way round for a public form: dodging costs us a
     * few ZeroBounce checks, the email gate still applies, and nobody can shut the form for
     * anyone else. Falls back to the request IP when mount captured nothing (tests, CLI).
     */
    protected function throttleIp(): string
    {
        return (string) ($this->ipAddress ?: request()?->ip() ?: 'unknown');
    }

    public function render(): View
    {
        return view('livewire.partner.partnership-prospect-form', [
            'options' => PartnershipProspectOptions::all(),
            'calendarUrl' => $this->submitted ? $this->calendarUrl() : null,
        ]);
    }
}
