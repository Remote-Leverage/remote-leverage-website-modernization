<?php

declare(strict_types=1);

namespace App\Application\Livewire\Partner;

use App\Domains\Referral\Actions\RegisterPartnerAction;
use App\Domains\Referral\Models\Partner;
use App\Domains\Referral\Services\StripeConnectGateway;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PartnerRegistrationForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $company = '';

    public string $website = '';

    public string $audience = 'Agency / B2B Clients';

    public string $payoutEmail = '';

    public bool $isSubmitted = false;

    public ?Partner $createdPartner = null;

    public ?string $referralCode = null;

    public ?string $stripeOnboardingUrl = null;

    public ?string $errorMessage = null;

    protected array $rules = [
        'name' => 'required|string|min:2|max:100',
        'email' => 'required|email|max:150',
        'company' => 'nullable|string|max:100',
        'website' => 'nullable|url|max:200',
        'audience' => 'required|string',
        'payoutEmail' => 'nullable|email|max:150',
    ];

    public function submitApplication(): void
    {
        $this->errorMessage = null;
        $this->validate();

        try {
            $registerAction = app(RegisterPartnerAction::class);
            $partner = $registerAction->execute([
                'name' => $this->name,
                'email' => $this->email,
                'company' => $this->company,
                'metadata' => [
                    'website' => $this->website,
                    'audience' => $this->audience,
                    'payout_email' => $this->payoutEmail ?: $this->email,
                ],
            ]);

            $this->createdPartner = $partner;
            $this->referralCode = $partner->referral_code;
            $this->isSubmitted = true;

            // Generate Stripe Express onboarding link if gateway is configured
            try {
                $stripe = app(StripeConnectGateway::class);
                $baseUrl = function_exists('home_url') ? \home_url('/partner-dashboard') : url('/partner-dashboard');
                $this->stripeOnboardingUrl = $stripe->createOnboardingLink(
                    $partner,
                    $baseUrl.'?connected=1',
                    $baseUrl.'?refresh=1'
                );
            } catch (\Throwable $e) {
                Log::info('Stripe Connect onboarding generation skipped or pending config: '.$e->getMessage());
            }

            // Track conversion event
            try {
                $tracker = app(RecordBehaviorEventAction::class);
                $tracker->execute(new AnalyticsEventData(
                    event: 'partner_application_submitted',
                    distinctId: $this->email,
                    properties: [
                        'partner_code' => $partner->referral_code,
                        'company' => $this->company,
                        'audience' => $this->audience,
                    ],
                    timestamp: time()
                ));
            } catch (\Throwable $e) {
                // Ignore analytics failure
            }
        } catch (\Throwable $e) {
            Log::error('Partner registration error: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Failed to submit application. If you already have an account, please login via the Partner Portal.';
        }
    }

    public function render(): View
    {
        return view('livewire.partner.partner-registration-form');
    }
}
