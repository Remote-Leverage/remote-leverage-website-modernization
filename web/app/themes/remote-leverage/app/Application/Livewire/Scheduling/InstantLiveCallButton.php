<?php

declare(strict_types=1);

namespace App\Application\Livewire\Scheduling;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Scheduling\Actions\RouteInstantCallAction;
use App\Domains\Scheduling\Services\LiveCallAvailabilityRouter;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class InstantLiveCallButton extends Component
{
    public string $layoutStyle = 'pill'; // 'pill' or 'standard'

    public string $buttonText = 'MEET US RIGHT NOW';

    public string $buttonStatusText = 'Available';

    public string $formTitle = 'Talk to Sales Representative';

    public string $formSubtitle = 'Enter your contact details below to be instantly connected to our sales team on Google Meet.';

    public bool $isAvailable = true;

    public int $onlineCount = 2;

    public bool $isConnecting = false;

    public bool $modalOpen = false;

    public bool $isDismissed = false;

    public int $currentStep = 1; // 1: Lead Form, 2: Connecting, 3: Confirmation

    public string $visitorName = '';

    public string $visitorEmail = '';

    public string $visitorPhone = '';

    public ?string $activeMeetUrl = null;

    public int $countdown = 5;

    public ?string $errorMessage = null;

    public function mount(string $layoutStyle = 'pill', string $buttonText = 'MEET US RIGHT NOW'): void
    {
        $this->layoutStyle = $layoutStyle;
        $this->buttonText = $buttonText;
        $this->checkAvailability();
    }

    public function checkAvailability(): void
    {
        try {
            $router = app(LiveCallAvailabilityRouter::class);
            $status = $router->getStatus();

            $this->isAvailable = (bool) ($status['available'] ?? false);
            $this->buttonStatusText = $this->isAvailable ? 'Available' : 'Unavailable';
            $this->onlineCount = (int) ($status['online_count'] ?? ($this->isAvailable ? 2 : 0));
        } catch (\Throwable $e) {
            $this->isAvailable = true;
            $this->buttonStatusText = 'Available';
        }
    }

    public function openInstantModal(): void
    {
        $this->errorMessage = null;
        $this->currentStep = 1;
        $this->modalOpen = true;
    }

    public function closeInstantModal(): void
    {
        $this->modalOpen = false;
        $this->isConnecting = false;
        $this->currentStep = 1;
    }

    public function dismissPill(): void
    {
        $this->isDismissed = true;
    }

    public function connectInstantCall(): void
    {
        $this->errorMessage = null;

        $this->validate([
            'visitorName' => 'required|string|min:2|max:100',
            'visitorEmail' => 'required|email|max:150',
            'visitorPhone' => 'nullable|string|max:30',
        ]);

        $this->currentStep = 2;
        $this->isConnecting = true;

        try {
            // 1. Capture Lead in Lead Domain (ADR-0008)
            $lead = null;
            try {
                $captureAction = app(CaptureLeadAction::class);
                $lead = $captureAction->execute(new LeadCaptureData(
                    name: $this->visitorName,
                    email: $this->visitorEmail,
                    phone: $this->visitorPhone ?: null,
                    extraData: ['source' => 'instant_live_call']
                ));
            } catch (\Throwable $e) {
                Log::warning('Lead capture non-fatal failure during instant call: '.$e->getMessage());
            }

            // 2. Route call to available consultant
            $action = app(RouteInstantCallAction::class);
            $result = $action->execute([
                'name' => $this->visitorName,
                'email' => $this->visitorEmail,
                'phone' => $this->visitorPhone,
            ], $lead);

            if (! empty($result['routed']) && ! empty($result['redirect_url'])) {
                $this->activeMeetUrl = $result['redirect_url'];
                $this->currentStep = 3;
                $this->isConnecting = false;

                // Track analytics event
                try {
                    $tracker = app(RecordBehaviorEventAction::class);
                    $tracker->execute(new AnalyticsEventData(
                        event: 'instant_call_connected',
                        distinctId: $this->visitorEmail,
                        properties: [
                            'session_id' => $result['session_id'] ?? null,
                            'meet_url' => $result['redirect_url'],
                        ],
                        timestamp: time()
                    ));
                } catch (\Throwable $e) {
                    // Ignore analytics error
                }
            } else {
                $this->errorMessage = $result['message'] ?? 'Unable to connect with a consultant right now.';
                $this->currentStep = 1;
                $this->isConnecting = false;
            }
        } catch (\Throwable $e) {
            Log::error('Error launching instant call: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Failed to launch live session. Please use our booking calendar.';
            $this->currentStep = 1;
            $this->isConnecting = false;
        }
    }

    public function render(): View
    {
        return view('livewire.scheduling.instant-live-call-button');
    }
}
