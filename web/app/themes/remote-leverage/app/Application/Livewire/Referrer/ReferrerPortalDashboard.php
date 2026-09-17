<?php

declare(strict_types=1);

namespace App\Application\Livewire\Referrer;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Domains\Referral\Support\DemoDashboardData;
use App\Domains\Referral\Support\ReferralLink;
use App\Domains\Referral\Support\ReferrerDashboardPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ReferrerPortalDashboard extends Component
{
    protected const MAX_LOGIN_ATTEMPTS = 5;

    protected const LOGIN_LOCKOUT_MINUTES = 15;

    /**
     * Referrals rendered per "Load more" step. The table used to be hard-capped at ten rows
     * with no way to reach the eleventh, so a productive referrer simply could not see their
     * own history.
     */
    protected const PAGE_SIZE = 10;

    // Authentication State
    public string $lookupCode = '';

    public string $password = '';

    public ?string $referrerCode = null;

    public ?Referrer $referrer = null;

    public bool $isAuthenticated = false;

    public ?string $loginError = null;

    /**
     * Table controls. The view model itself is rebuilt in render() rather than held in a
     * public property: it carries a timeline per referral, and round-tripping that through
     * Livewire's payload on every keystroke would dwarf the page it is rendering.
     */
    public string $statusFilter = 'all';

    public string $search = '';

    public int $visibleCount = self::PAGE_SIZE;

    public ?int $expandedReferralId = null;

    /**
     * Render the bundled demo fixture instead of this referrer's real data.
     *
     * Administrator-only, and the check is repeated inside viewModel() on every render. This
     * property arrives from the browser and is therefore attacker-controlled — gating only the
     * toggle's visibility in the template would let a signed-in referrer flip it by hand.
     */
    public bool $demoMode = false;

    /**
     * Landing pages, kept only for the direct-submission form's "target service" choice.
     *
     * No longer offered as link destinations: every referral link points at
     * ReferralLink::DESTINATION_PATH. A referrer could previously share a link to the homepage
     * or the booking funnel — pages that carry no referral offer — which made the welcome
     * notice impossible to guarantee and the destination a coin flip.
     */
    public array $landingPages = [];

    /**
     * The link this referrer shares. Derived from the selected destination, never typed.
     */
    public string $referralUrl = '';

    /**
     * Which page the link points at. Defaults to the hiring page; changed only through the
     * picker, and validated against ReferralLink::isAllowed() because it arrives from the
     * browser like any other Livewire property.
     */
    public string $selectedPath = ReferralLink::DESTINATION_PATH;

    public bool $showPageModal = false;

    // Direct Lead Submission Modal
    public bool $showLeadModal = false;

    public string $leadModalName = '';

    public string $leadModalEmail = '';

    public string $leadModalPhone = '';

    public string $leadModalLandingPage = '';

    public string $leadModalNotes = '';

    public ?string $leadModalSuccess = null;

    public ?string $leadModalError = null;

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
                $this->updateReferralUrl();

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

        $this->updateReferralUrl();
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

    /**
     * Build this referrer's shareable link for the selected destination.
     */
    public function updateReferralUrl(): void
    {
        $this->referralUrl = $this->referrerCode
            ? ReferralLink::for($this->referrerCode, $this->selectedPath)
            : '';
    }

    public function openPagePicker(): void
    {
        $this->showPageModal = true;
    }

    public function closePagePicker(): void
    {
        $this->showPageModal = false;
    }

    /**
     * Point the referral link at a different page.
     *
     * Silently falls back to the default for anything not on the curated list. The path comes
     * from the browser, so without the check a crafted request could put an arbitrary URL in
     * front of the referrer as "their" link to share.
     */
    public function selectPage(string $path): void
    {
        $this->selectedPath = ReferralLink::isAllowed($path) ? trim($path, '/') : ReferralLink::DESTINATION_PATH;
        $this->showPageModal = false;
        $this->updateReferralUrl();
    }

    /**
     * The destination list, each row carrying the two links the picker renders.
     *
     * Preview links are signed so opening one does not record a click against the referrer's
     * own reach figure — see ReferralLink::preview().
     *
     * @return array<int, array<string, mixed>>
     */
    public function destinations(): array
    {
        $code = (string) $this->referrerCode;

        return array_map(function (array $destination) use ($code) {
            return $destination + [
                'selected' => $destination['path'] === $this->selectedPath,
                'preview_url' => $code ? ReferralLink::preview($code, $destination['path']) : '#',
            ];
        }, ReferralLink::destinations());
    }

    /**
     * Display name of the page currently selected.
     */
    public function selectedPageName(): string
    {
        foreach (ReferralLink::destinations() as $destination) {
            if ($destination['path'] === $this->selectedPath) {
                return $destination['name'];
            }
        }

        return 'Hire a VA';
    }

    /**
     * Whether the signed-in WordPress user may switch this portal into demo mode.
     *
     * `manage_options` rather than merely "is logged in": the referrer portal is a public page
     * and any subscriber-level account would otherwise qualify. Outside WordPress (tests,
     * console) there is no such thing as an administrator, so this is false — a fixture must
     * never be reachable by default.
     */
    public function demoAllowed(): bool
    {
        return function_exists('current_user_can') && current_user_can('manage_options');
    }

    /**
     * Toggle the bundled demo fixture. Silently ignored for anyone not entitled to it.
     */
    public function toggleDemoMode(): void
    {
        if (! $this->demoAllowed()) {
            $this->demoMode = false;

            return;
        }

        $this->demoMode = ! $this->demoMode;
        $this->resetTableControls();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->visibleCount = self::PAGE_SIZE;
        $this->expandedReferralId = null;
    }

    /**
     * Reset paging whenever the search term changes, so filtering never lands the referrer on
     * an empty page of a shorter result set.
     */
    public function updatedSearch(): void
    {
        $this->visibleCount = self::PAGE_SIZE;
        $this->expandedReferralId = null;
    }

    public function loadMore(): void
    {
        $this->visibleCount += self::PAGE_SIZE;
    }

    /**
     * Jump the pipeline to one referral and open its history.
     *
     * What makes the "needs attention" rail a worklist rather than a read-only list: the row
     * may be behind an active filter or past the current page, so both are cleared first —
     * otherwise clicking a name appears to do nothing.
     */
    public function focusReferral(int $referralId): void
    {
        $this->statusFilter = 'all';
        $this->search = '';
        $this->visibleCount = max($this->visibleCount, self::PAGE_SIZE);
        $this->expandedReferralId = $referralId;
    }

    public function toggleTimeline(int $referralId): void
    {
        $this->expandedReferralId = $this->expandedReferralId === $referralId ? null : $referralId;
    }

    protected function resetTableControls(): void
    {
        $this->statusFilter = 'all';
        $this->search = '';
        $this->visibleCount = self::PAGE_SIZE;
        $this->expandedReferralId = null;
    }

    /**
     * Build the dashboard view model for this render.
     *
     * The demo entitlement is re-checked here, not trusted from the `$demoMode` property.
     * That property is part of Livewire's client payload, so a referrer could set it to true
     * in the browser; without this second check they would be served the fixture.
     *
     * @return array<string, mixed>
     */
    protected function viewModel(): array
    {
        if ($this->demoMode && $this->demoAllowed()) {
            return DemoDashboardData::build(
                (int) (app(ReferralSettingsService::class)->get()['stale_days'] ?? ReferralSettingsService::DEFAULT_STALE_DAYS)
            );
        }

        if (! $this->referrer) {
            return $this->emptyViewModel();
        }

        try {
            return app(ReferrerDashboardPresenter::class)->forReferrer($this->referrer) + ['is_demo' => false];
        } catch (\Throwable $e) {
            Log::error('Error building referrer dashboard: '.$e->getMessage(), ['exception' => $e]);

            return $this->emptyViewModel();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyViewModel(): array
    {
        return [
            'metrics' => ['reach' => 0, 'referrals' => 0, 'qualified' => 0, 'fulfilled' => 0, 'stale' => 0],
            'earnings' => ['due' => 0.0, 'issued' => 0.0, 'total' => 0.0, 'currency' => 'USD'],
            'referrals' => [],
            'needs_attention' => [],
            'recent_activity' => [],
            'stale_days' => ReferralSettingsService::DEFAULT_STALE_DAYS,
            'is_demo' => false,
        ];
    }

    /**
     * Apply the status filter and search term, then the "load more" window.
     *
     * @param  array<int, array<string, mixed>>  $referrals
     * @return array<int, array<string, mixed>>
     */
    protected function filterReferrals(array $referrals): array
    {
        $needle = strtolower(trim($this->search));

        $filtered = array_filter($referrals, function (array $row) use ($needle): bool {
            if ($this->statusFilter === 'stale' && ! $row['is_stale']) {
                return false;
            }

            if (! in_array($this->statusFilter, ['all', 'stale'], true) && $row['status'] !== $this->statusFilter) {
                return false;
            }

            if ($needle === '') {
                return true;
            }

            return str_contains(strtolower((string) $row['name']), $needle)
                || str_contains(strtolower((string) $row['email']), $needle);
        });

        return array_values($filtered);
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

            // Record referral entry in rl_referrals table.
            if ($this->referrer) {
                $this->referrer->referrals()->create([
                    /*
                     * The actual foreign key. This used to be recorded only in the note below,
                     * as prose — so nothing could join on it, and when the prospect later
                     * booked, the email-keyed lookup in HandleLeadBookingCompletedForReferrer
                     * failed to find this row and inserted a duplicate referral beside it. A
                     * phone-only submission missed every time, because the lead is given a
                     * synthesised `@remoteleverage.internal` address that matches nothing.
                     */
                    'lead_id' => $lead->id,
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
        } catch (\Throwable $e) {
            Log::error('Failed to submit direct lead in referrer portal: '.$e->getMessage(), ['exception' => $e]);
            $this->leadModalError = 'Could not record lead at this time. Please verify details and try again.';
        }
    }

    public function render(): View
    {
        $model = $this->viewModel();
        $filtered = $this->filterReferrals($model['referrals']);

        return view('livewire.referrer.referrer-portal-dashboard', [
            'metrics' => $model['metrics'],
            'earnings' => $model['earnings'],
            'needsAttention' => $model['needs_attention'],
            'recentActivity' => $model['recent_activity'],
            'staleDays' => $model['stale_days'],
            'isDemo' => (bool) ($model['is_demo'] ?? false),
            'canToggleDemo' => $this->demoAllowed(),
            'totalReferrals' => count($model['referrals']),
            'filteredCount' => count($filtered),
            'referrals' => array_slice($filtered, 0, $this->visibleCount),
            'hasMore' => count($filtered) > $this->visibleCount,
            'destinations' => $this->destinations(),
            'selectedPageName' => $this->selectedPageName(),
        ]);
    }
}
