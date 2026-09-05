<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalendlyWebhookController
{
    public function __construct(
        protected RecordBehaviorEventAction $recordEventAction
    ) {}

    /**
     * Handle incoming Calendly webhook event.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? 'unknown';

        Log::info('Calendly Webhook Received: '.$event, ['event' => $event]);

        if ($event === 'invitee.created') {
            $invitee = $payload['payload']['invitee'] ?? [];
            $email = $invitee['email'] ?? 'unknown';

            $this->recordEventAction->execute(AnalyticsEventData::fromArray([
                'event' => 'Consultation Scheduled',
                'distinct_id' => $email,
                'properties' => [
                    'invitee_name' => $invitee['name'] ?? null,
                    'invitee_email' => $email,
                    'event_type' => $payload['payload']['event_type']['name'] ?? null,
                    'scheduled_time' => $payload['payload']['event']['start_time'] ?? null,
                ],
            ]));
        }

        return response()->json(['status' => 'received']);
    }
}
