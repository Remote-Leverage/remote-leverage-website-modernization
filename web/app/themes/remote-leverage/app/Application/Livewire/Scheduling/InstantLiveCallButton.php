<?php

declare(strict_types=1);

namespace App\Application\Livewire\Scheduling;

use App\Domains\Scheduling\Actions\RouteInstantCallAction;
use App\Domains\Scheduling\Services\LiveCallAvailabilityRouter;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class InstantLiveCallButton extends Component
{
    public bool $isAvailable = true;

    public string $statusLabel = 'Consultants Online Now';

    public int $onlineCount = 2;

    public bool $isConnecting = false;

    public bool $modalOpen = false;

    public string $visitorEmail = '';

    public string $visitorName = '';

    public ?string $activeMeetUrl = null;

    public ?string $errorMessage = null;

    public string $buttonSize = 'default'; // 'default', 'compact', 'hero'

    public function mount(string $buttonSize = 'default'): void
    {
        $this->buttonSize = $buttonSize;
        $this->checkAvailability();
    }

    public function checkAvailability(): void
    {
        try {
            $router = app(LiveCallAvailabilityRouter::class);
            $status = $router->getStatus();

            $this->isAvailable = (bool) ($status['available'] ?? false);
            $this->statusLabel = $this->isAvailable ? 'Consultants Online Now' : 'Schedule a Call';
            $this->onlineCount = (int) ($status['online_count'] ?? ($this->isAvailable ? 2 : 0));
        } catch (\Throwable $e) {
            $this->isAvailable = true;
            $this->statusLabel = 'Consultants Online Now';
        }
    }

    public function openInstantModal(): void
    {
        $this->errorMessage = null;
        $this->modalOpen = true;
    }

    public function closeInstantModal(): void
    {
        $this->modalOpen = false;
        $this->isConnecting = false;
    }

    public function connectInstantCall(): void
    {
        $this->errorMessage = null;
        $this->isConnecting = true;

        $this->validate([
            'visitorEmail' => 'required|email|max:150',
            'visitorName' => 'required|string|min:2|max:100',
        ]);

        try {
            $action = app(RouteInstantCallAction::class);
            $result = $action->execute([
                'name' => $this->visitorName,
                'email' => $this->visitorEmail,
            ]);

            if (! empty($result['routed']) && ! empty($result['redirect_url'])) {
                $this->activeMeetUrl = $result['redirect_url'];

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

                // Redirect user to the Google Meet session
                $this->redirect($this->activeMeetUrl);
            } else {
                $this->errorMessage = $result['message'] ?? 'Unable to connect with a consultant right now.';
                $this->isConnecting = false;
            }
        } catch (\Throwable $e) {
            Log::error('Error launching instant call: '.$e->getMessage(), ['exception' => $e]);
            $this->errorMessage = 'Failed to launch live session. Please use our booking calendar.';
            $this->isConnecting = false;
        }
    }

    public function render(): View
    {
        return view('livewire.scheduling.instant-live-call-button');
    }
}
