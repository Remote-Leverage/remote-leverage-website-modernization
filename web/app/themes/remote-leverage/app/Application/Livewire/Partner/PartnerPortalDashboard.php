<?php

declare(strict_types=1);

namespace App\Application\Livewire\Partner;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Referral\Models\Partner;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Repositories\PartnerRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PartnerPortalDashboard extends Component
{
    // Authentication State
    public string $lookupCode = '';

    public ?string $partnerCode = null;

    public ?Partner $partner = null;

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
        $this->landingPages = [
            [
                'name' => 'Main Homepage',
                'base_url' => 'https://remoteleverage.com',
            ],
            [
                'name' => 'Hire Executive Assistants',
                'base_url' => 'https://remoteleverage.com/services/executive-assistants',
            ],
            [
                'name' => 'Hire Real Estate Assistants',
                'base_url' => 'https://remoteleverage.com/services/real-estate',
            ],
            [
                'name' => 'Strategy Consultation Funnel',
                'base_url' => 'https://remoteleverage.com/book-consultation',
            ],
        ];

        $code = $code ?? request()->query('partner') ?? session('partner_code');
        if ($code) {
            $this->lookupCode = $code;
            $this->authenticatePartner();
        }
    }

    public function authenticatePartner(): void
    {
        $this->loginError = null;

        $cleanCode = trim($this->lookupCode);
        if (! $cleanCode) {
            $this->loginError = 'Please enter your partner referral code or registered email.';

            return;
        }

        $repo = app(PartnerRepositoryInterface::class);

        // Lookup by partner code or email
        $partner = $repo->findByReferralCode($cleanCode) ?? $repo->findByEmail($cleanCode);

        if (! $partner) {
            $this->loginError = 'No affiliate account located matching that code. Please check your credentials or register.';

            return;
        }

        $this->partner = $partner;
        $this->partnerCode = $partner->referral_code;
        $this->isAuthenticated = true;
        session(['partner_code' => $this->partnerCode]);

        $this->updateSelectedLandingUrl();
        $this->loadMetrics();
    }

    public function logout(): void
    {
        $this->isAuthenticated = false;
        $this->partner = null;
        $this->partnerCode = null;
        $this->lookupCode = '';
        session()->forget('partner_code');
    }

    public function updateSelectedLandingUrl(): void
    {
        $baseUrl = $this->selectedLandingUrl ?: ($this->landingPages[0]['base_url'] ?? 'https://remoteleverage.com');
        $cleanBase = strtok($baseUrl, '?');
        $this->selectedLandingUrl = "{$cleanBase}?via={$this->partnerCode}";
    }

    public function updatedSelectedLandingUrl(string $value): void
    {
        $cleanBase = strtok($value, '?');
        $this->selectedLandingUrl = "{$cleanBase}?via={$this->partnerCode}";
    }

    public function loadMetrics(): void
    {
        if (! $this->partner) {
            return;
        }

        try {
            // 1. REACH: Count of total referral clicks
            $this->reachCount = ReferralClick::query()->where('partner_id', $this->partner->id)->count();

            // 2. RECENT REFERRALS: Total referrals submitted
            $referralsQuery = $this->partner->referrals();
            $this->recentReferralsCount = $referralsQuery->count();

            // 3. DEALS FULFILLED: Referrals with qualified or closed status
            $this->dealsFulfilledCount = $referralsQuery->whereIn('status', ['qualified', 'fulfilled', 'closed_won'])->count();

            // Load recent referrals table
            $activity = $this->partner->referrals()
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (Referral $ref) => [
                    'id' => $ref->id,
                    'date' => $ref->created_at ? $ref->created_at->format('M j, Y') : now()->format('M j, Y'),
                    'name' => $ref->referred_name ?? 'Confidential Contact',
                    'email' => $ref->referred_email ?? 'contact@domain.com',
                    'status' => $ref->status ?? 'pending',
                    'payout' => $ref->reward_amount ? '$'.number_format((float) $ref->reward_amount, 2) : '$0.00',
                ])
                ->toArray();

            $this->recentActivity = $activity;
        } catch (\Throwable $e) {
            Log::error('Error loading partner metrics: '.$e->getMessage());
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

        try {
            $captureAction = app(CaptureLeadAction::class);

            $leadData = LeadCaptureData::fromArray([
                'name' => $this->leadModalName,
                'email' => $this->leadModalEmail ?: "lead_{$this->partnerCode}_".time().'@remoteleverage.internal',
                'phone' => $this->leadModalPhone ?: null,
                'notes' => $this->leadModalNotes ?: "Direct affiliate submission from partner {$this->partnerCode}",
                'referral_code' => $this->partnerCode,
                'extra_data' => [
                    'source_form' => 'PartnerPortalDirectLeadModal',
                    'target_page' => $this->leadModalLandingPage,
                    'partner_id' => $this->partner?->id,
                ],
            ]);

            $lead = $captureAction->execute($leadData);

            // Record referral entry in rl_referrals table
            if ($this->partner) {
                $this->partner->referrals()->create([
                    'referral_code' => $this->partnerCode,
                    'referred_name' => $this->leadModalName,
                    'referred_email' => $this->leadModalEmail,
                    'status' => 'pending',
                    'metadata' => [
                        'lead_id' => $lead->id,
                        'lead_uuid' => $lead->uuid,
                        'notes' => $this->leadModalNotes,
                    ],
                ]);
            }

            $this->leadModalSuccess = 'Lead successfully submitted and attributed to your partner account!';
            $this->leadModalName = '';
            $this->leadModalEmail = '';
            $this->leadModalPhone = '';
            $this->leadModalNotes = '';

            $this->loadMetrics();
        } catch (\Throwable $e) {
            Log::error('Failed to submit direct lead in partner portal: '.$e->getMessage(), ['exception' => $e]);
            $this->leadModalError = 'Could not record lead at this time. Please verify details and try again.';
        }
    }

    public function render(): View
    {
        return view('livewire.partner.partner-portal-dashboard');
    }
}
