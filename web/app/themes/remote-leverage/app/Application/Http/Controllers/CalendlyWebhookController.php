<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class CalendlyWebhookController
{
    public function __construct(
        protected RecordBehaviorEventAction $recordEventAction,
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle incoming Calendly webhook event per ADR-0008.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? 'unknown';

        Log::info('Calendly Webhook Received: '.$event, ['event' => $event]);

        if ($event === 'invitee.created') {
            $invitee = $payload['payload']['invitee'] ?? [];
            $email = $invitee['email'] ?? null;
            $meetingId = $invitee['uri'] ?? ('cal_'.uniqid());

            // 1. Record PostHog behavioral telemetry
            $this->recordEventAction->execute(AnalyticsEventData::fromArray([
                'event' => 'Consultation Scheduled',
                'distinct_id' => $email ?? 'unknown',
                'properties' => [
                    'invitee_name' => $invitee['name'] ?? null,
                    'invitee_email' => $email,
                    'event_type' => $payload['payload']['event_type']['name'] ?? null,
                    'scheduled_time' => $payload['payload']['event']['start_time'] ?? null,
                    'meeting_id' => $meetingId,
                ],
            ]));

            // 2. Associate with Lead entity if found
            if ($email) {
                $lead = Lead::query()->where('email', strtolower($email))->latest()->first();
                if ($lead) {
                    $lead->update(['status' => 'booked']);

                    $this->activityLogger->logDispatch(
                        leadId: $lead->id,
                        eventType: 'LeadBookingCompleted',
                        actorDomain: 'Scheduling',
                        payload: ['meeting_id' => $meetingId, 'provider' => 'calendly'],
                        description: "Calendly webhook invitee.created processed for lead #{$lead->id}"
                    );

                    Event::dispatch(new LeadBookingCompleted(
                        lead: $lead,
                        meetingId: $meetingId,
                        provider: 'calendly',
                        meetUrl: $invitee['scheduling_url'] ?? null,
                        startTime: $payload['payload']['event']['start_time'] ?? null,
                        metadata: $payload
                    ));
                }
            }
        } elseif ($event === 'invitee.canceled') {
            $invitee = $payload['payload']['invitee'] ?? [];
            $email = $invitee['email'] ?? null;
            $reason = $payload['payload']['cancellation']['reason'] ?? 'Invitee canceled appointment';
            $cancelerType = $payload['payload']['cancellation']['canceler_type'] ?? 'invitee';

            if ($email) {
                $lead = Lead::query()->where('email', strtolower($email))->latest()->first();
                if ($lead) {
                    $lead->update(['status' => 'canceled']);

                    $this->activityLogger->logDispatch(
                        leadId: $lead->id,
                        eventType: 'LeadBookingCanceled',
                        actorDomain: 'Scheduling',
                        payload: ['reason' => $reason, 'canceler_type' => $cancelerType],
                        description: "Calendly booking canceled: {$reason}"
                    );

                    Event::dispatch(new LeadBookingCanceled(
                        lead: $lead,
                        reason: $reason,
                        canceledBy: $cancelerType,
                        metadata: $payload
                    ));
                }
            }
        }

        return response()->json(['status' => 'received']);
    }
}
