<?php

declare(strict_types=1);

namespace App\Application\Livewire\Referrer;

use App\Domains\Referral\Actions\RegisterReferrerAction;
use App\Domains\Referral\Actions\SubmitReferredLeadAction;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The form a Remote Leverage sales rep fills in at `/sales-referral` during a call (WR-126).
 *
 * The referrer is on the phone naming someone they want to refer; the rep types it while they
 * talk, rather than asking them to hang up and log into the portal. Same referral, same rules,
 * different typist — which is why everything below the referrer lookup is
 * {@see SubmitReferredLeadAction}, shared verbatim with `ReferrerPortalDashboard`.
 *
 * ## It is unauthenticated, and that is a deliberate, bounded decision
 *
 * There is no login: a rep mid-call should not be stopped by one. What bounds it instead:
 *
 *  - **The URL is unlisted and unindexed.** The route calls `PageRobots::forceNoindex()` and
 *    nothing links here. (Deliberately *not* disallowed in robots.txt — a crawler told not to
 *    fetch never reads the `noindex`. See the route.) That is obscurity, not security, and is
 *    not claimed as more.
 *  - **It cannot invent a referrer.** The code must resolve to an existing `rl_referrers` row;
 *    an unknown one is refused, so this cannot mint credit for an account that does not exist.
 *  - **It cannot invent a *reward*.** Every referral it writes is `pending`. Only a completed
 *    booking promotes one to `qualified`, and only a closed deal in HubSpot fulfils it. The
 *    worst a bad submission does is put a row in front of an admin.
 *  - **It is throttled per IP**, so the URL leaking does not hand anyone a bulk lead-injection
 *    endpoint.
 *
 * The self-referral guard applies here too, via the shared action. Worth stating plainly
 * because it reads oddly at first: the rep is not the referrer, so "self-referral" means the
 * *named* referrer's own email arriving as the lead — which is exactly the case a rep taking
 * details over the phone is least able to spot.
 */
class SalesReferralForm extends Component
{
    /**
     * Submissions allowed from one IP inside the window below.
     *
     * Generous by design. A rep working a list legitimately files several in a row, and the
     * office shares an IP; this is a bulk-abuse ceiling, not a pace limit.
     */
    public const MAX_SUBMISSIONS = 20;

    public const THROTTLE_MINUTES = 10;

    public string $referrerCode = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $revenue = '';

    public string $notes = '';

    /**
     * The "this referrer has no account yet" panel.
     *
     * On the same page rather than a route of its own, because it is the same phone call: the
     * rep finds out mid-sentence that the person is not registered, and a second URL to
     * remember is a referral lost while they look for it. Registering fills in the code below,
     * so the two steps run together.
     */
    public bool $showRegisterPanel = false;

    public string $newReferrerName = '';

    public string $newReferrerEmail = '';

    public string $newReferrerCompany = '';

    public ?string $registerError = null;

    public ?string $registerSuccess = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    /**
     * What the last successful submission recorded, so the rep can read it back on the call.
     *
     * @var array<string, string>|null
     */
    public ?array $lastSubmission = null;

    public function toggleRegisterPanel(): void
    {
        $this->showRegisterPanel = ! $this->showRegisterPanel;
        $this->registerError = null;
        $this->registerSuccess = null;
    }

    /**
     * Register the referrer the rep is talking to, then put their code in the form below.
     *
     * The account is created *unclaimed* — no password, status `pending`. The rep cannot choose
     * someone's password on a call and there is no way to send them one: there is no reset flow,
     * and the welcome email carries the referral link but no credentials. So the referrer can be
     * credited immediately and claims portal access later by signing up at `/referrer-register`
     * with the same address. See RegisterReferrerAction::createUnclaimed().
     */
    public function registerReferrer(): void
    {
        $this->registerError = null;
        $this->registerSuccess = null;

        $throttleKey = 'sales_referral_submissions:'.request()?->ip();

        if ((int) Cache::get($throttleKey, 0) >= self::MAX_SUBMISSIONS) {
            $this->registerError = 'Too many submissions from this location. Please try again shortly.';

            return;
        }

        $name = trim($this->newReferrerName);
        $email = strtolower(trim($this->newReferrerEmail));

        if ($name === '' || mb_strlen($name) > 100) {
            $this->registerError = 'Please enter the referrer full name.';

            return;
        }

        if ($email === '' || mb_strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->registerError = 'Please enter a valid email address for the referrer.';

            return;
        }

        $this->recordAttempt($throttleKey);

        try {
            $action = app(RegisterReferrerAction::class);
            $existing = app(ReferrerRepositoryInterface::class)->findByEmail($email);

            $referrer = $action->createUnclaimed([
                'name' => $name,
                'email' => $email,
                'company' => trim($this->newReferrerCompany) ?: null,
                'created_via' => 'sales_rep',
            ]);

            /*
             * createUnclaimed() hands back the existing row for an address already registered,
             * which is the right thing — but saying "registered" to a rep who is about to read
             * a code out loud would be wrong if the account was someone else's all along.
             */
            $this->registerSuccess = $existing
                ? $referrer->name.' already has an account. Their code is below.'
                : $referrer->name.' is registered. Their code is below — read it back to them.';

            $this->referrerCode = $referrer->referral_code;
            $this->showRegisterPanel = false;
            $this->newReferrerName = '';
            $this->newReferrerEmail = '';
            $this->newReferrerCompany = '';
        } catch (\Throwable $e) {
            Log::error('SalesReferralForm: could not register referrer: '.$e->getMessage(), ['exception' => $e]);
            $this->registerError = 'Could not register that referrer. Please try again.';
        }
    }

    public function submit(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $throttleKey = 'sales_referral_submissions:'.request()?->ip();

        if ((int) Cache::get($throttleKey, 0) >= self::MAX_SUBMISSIONS) {
            $this->errorMessage = 'Too many submissions from this location. Please try again shortly.';

            return;
        }

        $code = trim($this->referrerCode);

        /*
         * By code only, not by email. The portal's login accepts either, but this form is
         * filled in by someone who is *not* the referrer: letting a rep look an account up by
         * typing an email address turns an unauthenticated page into a way to test whether a
         * given address has a referrer account.
         */
        $referrer = $code === ''
            ? null
            : app(ReferrerRepositoryInterface::class)->findByReferralCode($code);

        $this->errorMessage = SubmitReferredLeadAction::validate($referrer, $this->fields());

        if ($this->errorMessage !== null) {
            // Counted whether or not it succeeded, so guessing codes is throttled too.
            $this->recordAttempt($throttleKey);

            // The most likely reason a code does not resolve, mid-call, is that the person has
            // never registered. Open the panel rather than making the rep find it.
            if ($referrer === null) {
                $this->showRegisterPanel = true;
            }

            return;
        }

        try {
            $referral = app(SubmitReferredLeadAction::class)->execute(
                $referrer,
                $this->fields(),
                SubmitReferredLeadAction::SOURCE_SALES,
                'SalesReferralForm',
            );

            $this->recordAttempt($throttleKey);

            $this->lastSubmission = [
                'referral_id' => (string) $referral->id,
                'lead_name' => (string) $referral->lead_name,
                'referrer' => (string) $referrer->name,
                'referral_code' => (string) $referrer->referral_code,
            ];

            $this->successMessage = 'Referral recorded for '.$referrer->name.'.';
            $this->resetLeadFields();
        } catch (\Throwable $e) {
            Log::error('SalesReferralForm: could not record referral: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Could not record the referral. Please check the details and try again.';
        }
    }

    /**
     * The inputs, in the shape SubmitReferredLeadAction reads.
     *
     * @return array<string, string>
     */
    protected function fields(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'revenue' => $this->revenue,
            'notes' => $this->notes,
        ];
    }

    /**
     * Clear the lead, keep the referrer.
     *
     * A rep taking several referrals from one caller should not retype the code between them,
     * and leaving the lead's details on screen is how the second referral inherits the first
     * one's phone number.
     */
    protected function resetLeadFields(): void
    {
        $this->firstName = '';
        $this->lastName = '';
        $this->email = '';
        $this->phone = '';
        $this->revenue = '';
        $this->notes = '';
    }

    protected function recordAttempt(string $throttleKey): void
    {
        $attempts = (int) Cache::get($throttleKey, 0) + 1;

        Cache::put($throttleKey, $attempts, now()->addMinutes(self::THROTTLE_MINUTES));
    }

    public function render(): View
    {
        return view('livewire.referrer.sales-referral-form');
    }
}
