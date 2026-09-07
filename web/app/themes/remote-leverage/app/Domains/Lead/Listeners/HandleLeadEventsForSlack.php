<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use Illuminate\Support\Facades\Log;

class HandleLeadEventsForSlack
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle LeadCreated (e.g. Step 1 partial capture).
     */
    public function handleCreated(LeadCreated $event): void
    {
        $this->dispatchSlackNotification($event->lead, 'partial', $event->context);
    }

    /**
     * Handle LeadBookingCompleted (e.g. Step 4 final booking confirmed).
     */
    public function handleBookingCompleted(LeadBookingCompleted $event): void
    {
        $this->dispatchSlackNotification($event->lead, 'final', [
            'meeting_id' => $event->meetingId,
            'provider' => $event->provider,
            'meet_url' => $event->meetUrl,
            'start_time' => $event->startTime,
            'metadata' => $event->metadata,
        ]);
    }

    /**
     * Build and dispatch Slack incoming webhook notification.
     */
    protected function dispatchSlackNotification(Lead $lead, string $type, array $context = []): void
    {
        $webhookUrl = config('services.slack.webhook_url');
        if (empty($webhookUrl) && function_exists('get_option')) {
            $webhookUrl = get_option('rl_jlc_slack_webhook_url') ?: get_option('rl_slack_webhook_url');
        }

        if (empty($webhookUrl)) {
            return;
        }

        $isFinal = $type === 'final';
        $title = $isFinal
            ? '🎉 *New Strategy Call Booked!*'
            : '⚠️ *Partial Lead Captured (Step 1)*';

        $sourceInfo = "[{$lead->source_type}: ".($lead->source_id ?: 'direct').']';
        if (! empty($lead->utm_campaign)) {
            $sourceInfo .= " (Campaign: {$lead->utm_campaign})";
        }

        $fields = [
            [
                'type' => 'mrkdwn',
                'text' => "*Name:*\n".($lead->name ?: 'N/A'),
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Email:*\n<mailto:{$lead->email}|{$lead->email}>",
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Phone:*\n".($lead->phone ?: 'Not provided'),
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Company MRR:*\n".($lead->monthly_revenue ?: 'Not specified'),
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Attribution:*\n{$sourceInfo}",
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Status:*\n".strtoupper($lead->status),
            ],
        ];

        if ($isFinal && ! empty($context['start_time'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Meeting Time:*\n{$context['start_time']}",
            ];
        } elseif (! $isFinal && ! empty($context['preferred_slot'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Selected Slot:*\n{$context['preferred_slot']}",
            ];
        }

        $payload = [
            'text' => "Remote Leverage Lead Alert: {$title} - {$lead->name} ({$lead->email})",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $title,
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => $fields,
                ],
            ],
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
                eventType: $isFinal ? 'LeadBookingCompleted' : 'LeadCreated',
                actorDomain: 'Slack',
                outcome: $success ? 'succeeded' : 'failed',
                description: $success
                    ? "Dispatched {$type} notification to Slack"
                    : "Failed to dispatch {$type} notification to Slack",
                payload: [
                    'type' => $type,
                    'webhook_url' => substr((string) $webhookUrl, 0, 30).'...',
                ]
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadEventsForSlack: Exception sending to Slack for lead #{$lead->id}: ".$e->getMessage());
        }
    }
}
