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
            $success = true;
            if (function_exists('\wp_remote_post')) {
                $response = \wp_remote_post($webhookUrl, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode($payload),
                    'timeout' => 5,
                ]);
                $success = ! \is_wp_error($response) && \wp_remote_retrieve_response_code($response) < 300;
            }

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: $eventName === 'lead.booking_completed' ? 'LeadBookingCompleted' : 'LeadCreated',
                actorDomain: 'OutgoingWebhook',
                outcome: $success ? 'succeeded' : 'failed',
                description: $success
                    ? "Dispatched {$eventName} outgoing webhook"
                    : "Failed to dispatch {$eventName} outgoing webhook",
                payload: [
                    'event' => $eventName,
                    'webhook_url' => substr((string) $webhookUrl, 0, 30).'...',
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadEventsForWebhook: Exception sending webhook for lead #{$lead->id}: ".$e->getMessage());
        }
    }
}
