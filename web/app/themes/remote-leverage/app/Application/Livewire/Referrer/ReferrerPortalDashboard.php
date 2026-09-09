<?php

declare(strict_types=1);

namespace App\Application\Livewire\Referrer;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use App\Domains\Referral\Services\ReferralSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ReferrerPortalDashboard extends Component
{
    protected const MAX_LOGIN_ATTEMPTS = 5;

    protected const LOGIN_LOCKOUT_MINUTES = 15;

    // Authentication State
    public string $lookupCode = '';

    public string $password = '';

    public ?string $referrerCode = null;

    public ?Referrer $referrer = null;

    public bool $isAuthenticated = false;

    public ?string $loginError = null;

    // KPI Metrics (matching rl-referral-program)
    public int $reachCount = 0;

    public int $recentReferralsCount = 0;

    public int $dealsFulfilledCount = 0;

    // Available Landing Pages for referral links
    public array $landingPages = [];

    public string $selectedLandingUrl = '';

    // Direct Lead Submission Modal
    public bool $showLeadModal = false;

    public string $leadModalName = '';

    public string $leadModalEmail = '';

    public string $leadModalPhone = '';

    public string $leadModalLandingPage = '';

    public string $leadModalNotes = '';

    public ?string $leadModalSuccess = null;

    public ?string $leadModalError = null;

    public array $recentActivity = [];

    public function mount(?string $code = null): void
    {
        $this->landingPages = app(ReferralSettingsService::class)->get()['landing_pages'];

        // A previously authenticated session may be restored without re-entering a password.
        $sessionCode = session('referrer_code');
        if ($sessionCode) {
            $referrer = app(ReferrerRepositoryInterface::class)->findByReferralCode($sessionCode);
            if ($referrer) {
                $this->referrer = $referrer;
                $this->referrerCode = $referrer->referral_code;
                $this->isAuthenticated = true;
                $this->updateSelectedLandingUrl();
                $this->loadMetrics();

                return;
            }

            session()->forget('referrer_code');
        }

        // A `?referrer=` code in the URL only pre-fills the login form; it is not proof of
        // identity, so it must never bypass the password check in authenticateReferrer().
        $prefillCode = $code ?? request()->query('referrer');
        if ($prefillCode) {
            $this->lookupCode = $prefillCode;
        }
    }

    public function authenticateReferrer(): void
    {
        $this->loginError = null;

        $cleanCode = trim($this->lookupCode);
        if (! $cleanCode) {
            $this->loginError = 'Please enter your referral code or registered email.';

            return;
        }

        $throttleKey = 'referrer_login_attempts:'.request()?->ip().':'.strtolower($cleanCode);

        if ($this->isLoginThrottled($throttleKey)) {
            $this->loginError = 'Too many failed login attempts. Please try again in '.self::LOGIN_LOCKOUT_MINUTES.' minutes.';

            return;
        }

        if (empty($this->password)) {
            $this->loginError = 'Please enter your account password.';

            return;
        }

        $repo = app(ReferrerRepositoryInterface::class);

        // Lookup by referral code or email
        $referrer = $repo->findByReferralCode($cleanCode) ?? $repo->findByEmail($cleanCode);

        if (! $referrer || empty($referrer->password) || ! password_verify($this->password, $referrer->password)) {
            $this->recordFailedLoginAttempt($throttleKey);
            $this->loginError = 'Invalid credentials. Please check your referral code/email and password, or register.';

            return;
        }

        Cache::forget($throttleKey);

        $this->referrer = $referrer;
        $this->referrerCode = $referrer->referral_code;
        $this->isAuthenticated = true;
        $this->password = '';
        session(['referrer_code' => $this->referrerCode]);

        $this->updateSelectedLandingUrl();
        $this->loadMetrics();
    }

    /**
     * Check whether this IP/code pair has exceeded the allowed failed login attempts.
     */
    protected function isLoginThrottled(string $throttleKey): bool
    {
        return (int) Cache::get($throttleKey, 0) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Record a failed login attempt, resetting the lockout window's TTL.
     */
    protected function recordFailedLoginAttempt(string $throttleKey): void
    {
        $attempts = (int) Cache::get($throttleKey, 0) + 1;
        Cache::put($throttleKey, $attempts, self::LOGIN_LOCKOUT_MINUTES * 60);
    }

    public function logout(): void
    {
        $this->isAuthenticated = false;
        $this->referrer = null;
        $this->referrerCode = null;
        $this->lookupCode = '';
        session()->forget('referrer_code');
    }

    public function updateSelectedLandingUrl(): void
    {
        $baseUrl = $this->selectedLandingUrl ?: ($this->landingPages[0]['base_url'] ?? 'https://remoteleverage.com');
        $cleanBase = strtok($baseUrl, '?');
        $this->selectedLandingUrl = "{$cleanBase}?via={$this->referrerCode}";
    }

    public function updatedSelectedLandingUrl(string $value): void
    {
        $cleanBase = strtok($value, '?');
        $this->selectedLandingUrl = "{$cleanBase}?via={$this->referrerCode}";
    }

    public function loadMetrics(): void
    {
        if (! $this->referrer) {
            return;
        }

        try {
            // 1. REACH: Count of total referral clicks
            $this->reachCount = ReferralClick::query()->where('referrer_id', $this->referrer->id)->count();

            // 2. RECENT REFERRALS: Total referrals submitted
            $referralsQuery = $this->referrer->referrals();
            $this->recentReferralsCount = $referralsQuery->count();

            // 3. DEALS FULFILLED: Referrals with qualified or closed status
            $this->dealsFulfilledCount = $referralsQuery->whereIn('status', ['qualified', 'fulfilled', 'closed_won'])->count();

            // Load recent referrals table
            $activity = $this->referrer->referrals()
                ->with('rewards')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (Referral $ref) => [
                    'id' => $ref->id,
                    'date' => $ref->created_at ? $ref->created_at->format('M j, Y') : now()->format('M j, Y'),
                    'name' => $ref->lead_name ?: 'Confidential Contact',
                    'email' => $ref->lead_email ?: 'contact@domain.com',
                    'status' => $ref->status ?? 'pending',
                    'payout' => '$'.number_format((float) $ref->rewards->sum('amount'), 2),
                ])
                ->toArray();

            $this->recentActivity = $activity;
        } catch (\Throwable $e) {
            Log::error('Error loading referrer metrics: '.$e->getMessage());
        }
    }

    public function openSubmitLeadModal(): void
    {
        $this->leadModalError = null;
        $this->leadModalSuccess = null;
        $this->leadModalLandingPage = $this->landingPages[0]['name'] ?? 'Main Homepage';
        $this->showLeadModal = true;
    }

    public function closeSubmitLeadModal(): void
    {
        $this->showLeadModal = false;
        $this->leadModalError = null;
        $this->leadModalSuccess = null;
    }

    public function submitDirectLead(): void
    {
        $this->leadModalError = null;
        $this->leadModalSuccess = null;

        if (empty($this->leadModalName)) {
            $this->leadModalError = 'Please enter the lead full name.';

            return;
        }

        if (empty($this->leadModalEmail) && empty($this->leadModalPhone)) {
            $this->leadModalError = 'Please provide either an email or phone number for the lead.';

            return;
        }

        if ($this->referrer && $this->leadModalEmail && strtolower($this->leadModalEmail) === strtolower($this->referrer->email)) {
            $this->leadModalError = 'You cannot submit yourself as a referred lead.';

            return;
        }

        try {
            $captureAction = app(CaptureLeadAction::class);

            $leadData = LeadCaptureData::fromArray([
                'name' => $this->leadModalName,
                'email' => $this->leadModalEmail ?: "lead_{$this->referrerCode}_".time().'@remoteleverage.internal',
                'phone' => $this->leadModalPhone ?: null,
                'notes' => $this->leadModalNotes ?: "Direct submission from referrer {$this->referrerCode}",
                'referral_code' => $this->referrerCode,
                'extra_data' => [
                    'source_form' => 'ReferrerPortalDirectLeadModal',
                    'target_page' => $this->leadModalLandingPage,
                    'referrer_id' => $this->referrer?->id,
                ],
            ]);

            $lead = $captureAction->execute($leadData);

            // Record referral entry in rl_referrals table
            if ($this->referrer) {
                $this->referrer->referrals()->create([
                    'lead_name' => $this->leadModalName,
                    'lead_email' => $this->leadModalEmail,
                    'lead_phone' => $this->leadModalPhone,
                    'landing_page' => $this->leadModalLandingPage,
                    'source' => 'referrer_direct_submission',
                    'status' => 'pending',
                    'notes' => trim($this->leadModalNotes."\n(lead #{$lead->id}, uuid {$lead->uuid})"),
                ]);
            }

            $this->leadModalSuccess = 'Lead successfully submitted and attributed to your referrer account!';
            $this->leadModalName = '';
            $this->leadModalEmail = '';
            $this->leadModalPhone = '';
            $this->leadModalNotes = '';

            $this->loadMetrics();
        } catch (\Throwable $e) {
            Log::error('Failed to submit direct lead in referrer portal: '.$e->getMessage(), ['exception' => $e]);
            $this->leadModalError = 'Could not record lead at this time. Please verify details and try again.';
        }
    }

    public function render(): View
    {
        return view('livewire.referrer.referrer-portal-dashboard');
    }
}
