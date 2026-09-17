<?php

declare(strict_types=1);

namespace App\Application\Livewire\Referrer;

use App\Domains\Referral\Actions\RegisterReferrerAction;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ReferrerRegistrationForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $isSubmitted = false;

    public ?Referrer $createdReferrer = null;

    public ?string $referralCode = null;

    public ?string $errorMessage = null;

    protected array $rules = [
        'name' => 'required|string|min:2|max:100',
        'email' => 'required|email|max:150',
        'password' => 'required|string|min:8|confirmed',
    ];

    public function submitApplication(): void
    {
        $this->errorMessage = null;
        $this->validate();

        try {
            $registerAction = app(RegisterReferrerAction::class);
            $referrer = $registerAction->execute([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);

            $this->createdReferrer = $referrer;
            $this->referralCode = $referrer->referral_code;
            $this->isSubmitted = true;

            // Track conversion event
            try {
                $tracker = app(RecordBehaviorEventAction::class);
                $tracker->execute(new AnalyticsEventData(
                    event: 'referrer_application_submitted',
                    distinctId: $this->email,
                    properties: [
                        'referral_code' => $referrer->referral_code,
                    ],
                    timestamp: time()
                ));
            } catch (\Throwable $e) {
                // Ignore analytics failure
            }
        } catch (\Throwable $e) {
            Log::error('Referrer registration error: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Failed to submit application. If you already have an account, please login via the Referrer Portal.';
        }
    }

    public function render(): View
    {
        return view('livewire.referrer.referrer-registration-form');
    }
}
