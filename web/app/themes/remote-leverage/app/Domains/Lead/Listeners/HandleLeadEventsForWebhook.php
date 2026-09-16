<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class HandleLeadEventsForWebhook
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle LeadCreated event.
     */
    public function handleCreated(LeadCreated $event): void
    {
        /*
         * Shadow ban. The form succeeded and the lead is stored, but nothing downstream fires.
         *
         * Silence rather than a refusal is deliberate: an error tells someone which identifier
         * to change and they are back within a minute under a new address. This costs them
         * nothing to trigger and a great deal to detect.
         */
        if ($event->lead->is_blocked) {
            return;
        }

        $this->dispatchWebhook($event->lead, 'lead.partial_captured', $event->context);
    }

    /**
     * Handle LeadBookingCompleted event.
     */
    public function handleBookingCompleted(LeadBookingCompleted $event): void
    {
        $this->dispatchWebhook($event->lead, 'lead.booking_completed', [
            'meeting_id' => $event->meetingId,
            'provider' => $event->provider,
            'meet_url' => $event->meetUrl,
            'start_time' => $event->startTime,
            'metadata' => $event->metadata,
        ]);
    }

    /**
     * The activity-log event type for a webhook event name.
     */
    protected function eventTypeFor(string $eventName): string
    {
        return $eventName === 'lead.booking_completed' ? 'LeadBookingCompleted' : 'LeadCreated';
    }

    /**
     * Post JSON webhook payload to configured outgoing webhook endpoint.
     */
    protected function dispatchWebhook(Lead $lead, string $eventName, array $context = []): void
    {
        $webhookUrl = config('services.webhooks.lead_webhook_url');
        if (empty($webhookUrl) && function_exists('get_option')) {
            $webhookUrl = get_option('rl_lead_webhook_url') ?: get_option('rl_custom_webhook_url');
        }

        if (empty($webhookUrl)) {
            return;
        }

        $payload = [
            'event' => $eventName,
            'timestamp' => Carbon::now()->toIso8601String(),
            'lead' => [
                'id' => $lead->id,
                'uuid' => $lead->uuid,
                'name' => $lead->name,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'phone_country' => $lead->phone_country,
                'company' => $lead->company,
                'role_needed' => $lead->role_needed,
                'monthly_revenue' => $lead->monthly_revenue,
                'status' => $lead->status,
                'source_type' => $lead->source_type,
                'source_id' => $lead->source_id,
                'referral_code' => $lead->referral_code,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'utm_term' => $lead->utm_term,
                'utm_content' => $lead->utm_content,
                'gclid' => $lead->gclid,
                'fbclid' => $lead->fbclid,
                'session_id' => $lead->session_id,
                'landing_url' => $lead->landing_url,
                'created_at' => $lead->created_at?->toIso8601String(),
            ],
            'context' => $context,
        ];

        try {
            /*
             * `skipped`, not `succeeded`, when there is no HTTP transport.
             *
             * This previously defaulted to success and logged a dispatch that never left the
             * process. A log that reports a delivery it did not make is worse than no log: it
             * ends the investigation at the wrong place.
             */
            if (! function_exists('\wp_remote_post')) {
                $this->activityLogger->logConsumption(
                    leadId: $lead->id,
                    eventType: $this->eventTypeFor($eventName),
                    actorDomain: 'OutgoingWebhook',
                    outcome: 'skipped',
                    description: "No HTTP transport available; {$eventName} webhook was not sent",
                    payload: ['event' => $eventName, 'webhook_url' => $webhookUrl]
                );

                return;
            }

            $response = \wp_remote_post($webhookUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($payload),
                'timeout' => 5,
            ]);

            $isError = \is_wp_error($response);
            $statusCode = $isError ? null : (int) \wp_remote_retrieve_response_code($response);
            $success = ! $isError && $statusCode !== null && $statusCode < 300;

            /*
             * The response is recorded here as well as in the integration call log, because the
             * status code is what turns "we tried" into a diagnosis and it costs one line to
             * have it on the entry someone is already looking at.
             */
            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: $this->eventTypeFor($eventName),
                actorDomain: 'OutgoingWebhook',
                outcome: $success ? 'succeeded' : 'failed',
                description: $success
                    ? "Dispatched {$eventName} outgoing webhook (HTTP {$statusCode})"
                    : "Failed to dispatch {$eventName} outgoing webhook",
                payload: [
                    'event' => $eventName,
                    'webhook_url' => $webhookUrl,
                    'status_code' => $statusCode,
                    'error' => $isError ? $response->get_error_message() : null,
                    'response_body' => $isError
                        ? null
                        : mb_substr((string) \wp_remote_retrieve_body($response), 0, 1000),
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadEventsForWebhook: Exception sending webhook for lead #{$lead->id}: ".$e->getMessage());
        }
    }
}
