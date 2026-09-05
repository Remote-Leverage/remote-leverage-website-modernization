<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Models\LiveCallSession;
use App\Domains\Scheduling\Services\LiveCallAvailabilityRouter;
use Illuminate\Support\Facades\Log;

class RouteInstantCallAction
{
    public function __construct(
        protected LiveCallAvailabilityRouter $router
    ) {}

    /**
     * Route a visitor to an instant live consultant call and create session record.
     * Ported from JoinLiveCallIntegration.
     */
    public function execute(array $visitorData): array
    {
        $status = $this->router->getStatus();
        $email = $visitorData['email'] ?? '';
        $sessionId = uniqid('session_', true);

        Log::info('Routing instant live call', [
            'session_id' => $sessionId,
            'visitor' => $visitorData,
            'status' => $status,
        ]);

        if (! $status['available']) {
            return [
                'routed' => false,
                'session_id' => $sessionId,
                'message' => 'Consultants are currently in sessions. Please schedule a time.',
                'redirect_url' => null,
            ];
        }

        $meetUrl = $status['meet_url'];

        // Persist session into database matching rl_live_call_sessions
        LiveCallSession::query()->create([
            'session_id' => $sessionId,
            'calendly_event_uri' => $visitorData['calendly_event_uri'] ?? '',
            'calendly_invitee_uri' => $visitorData['calendly_invitee_uri'] ?? '',
            'meeting_url' => $meetUrl,
            'status' => 'initiated',
            'visitor_email' => $email,
        ]);

        return [
            'routed' => true,
            'session_id' => $sessionId,
            'message' => 'Connecting to senior consultant...',
            'redirect_url' => $meetUrl,
        ];
    }
}
