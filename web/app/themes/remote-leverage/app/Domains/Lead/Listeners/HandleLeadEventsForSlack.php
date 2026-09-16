<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The "NEW LEAD" Slack alert, ported from the Gravity Forms Slack feed.
 *
 * Two things about the legacy feed are load-bearing and easy to get wrong:
 *
 *  - **It fires on the partial submission, not the completed booking.** The GF feed's condition
 *    is `submission_type is not Final`. The alert exists so sales sees a lead the moment contact
 *    details are entered — including the people who never finish booking, who are exactly the
 *    ones worth chasing. Firing on the final submission instead would alert only on leads that
 *    already booked themselves.
 *  - **The message format is what the sales team reads at a glance.** Field order and labels are
 *    reproduced from the feed rather than redesigned.
 *
 * Transport: production posts through the Gravity Forms Slack add-on's bot token to a channel
 * (`chat.postMessage`). This supports that, and falls back to a plain incoming webhook when no
 * token is configured. Moving to a dedicated Slack app is WR-186.
 */
class HandleLeadEventsForSlack
{
    /** The GF feed's `submission_type` value that suppresses the alert. */
    private const SUPPRESS_ON = 'Final';

    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle LeadCreated — the step 1 partial capture.
     */
    public function handleCreated(LeadCreated $event): void
    {
        $submissionType = (string) ($event->lead->submission_type ?? '');

        // Parity with the GF feed's condition. LeadCreated fires for both the partial capture
        // and the completed booking, so without this the team gets two alerts per lead and the
        // second is the less useful one.
        if (strcasecmp($submissionType, self::SUPPRESS_ON) === 0) {
            return;
        }

        $this->dispatchSlackNotification($event->lead, 'partial');
    }

    /**
     * Handle LeadBookingCompleted.
     *
     * **Off by default**, because the legacy feed did not send it — see the class docblock.
     * Enable with `SLACK_NOTIFY_ON_BOOKING=true` if the team wants a second alert when a lead
     * actually books.
     */
    public function handleBookingCompleted(LeadBookingCompleted $event): void
    {
        if (! config('services.slack.notify_on_booking', false)) {
            return;
        }

        $this->dispatchSlackNotification($event->lead, 'final', [
            'meeting_id' => $event->meetingId,
            'start_time' => $event->startTime,
            'meet_url' => $event->meetUrl,
        ]);
    }

    /**
     * Build and send the message.
     */
    protected function dispatchSlackNotification(Lead $lead, string $type, array $context = []): void
    {
        $isFinal = $type === 'final';
        $text = $isFinal
            ? $this->bookedMessage($lead, $context)
            : $this->newLeadMessage($lead);

        try {
            $success = $this->send($text);

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: $isFinal ? 'LeadBookingCompleted' : 'LeadCreated',
                actorDomain: 'Slack',
                outcome: $success ? 'succeeded' : 'failed',
                description: $success
                    ? "Dispatched {$type} notification to Slack"
                    : "Failed to dispatch {$type} notification to Slack",
                payload: ['type' => $type],
            );
        } catch (\Throwable $e) {
            Log::error("HandleLeadEventsForSlack: Exception sending to Slack for lead #{$lead->id}: ".$e->getMessage());
        }
    }

    /**
     * The legacy feed's message, field for field.
     *
     * Reproduced rather than redesigned: the sales team reads these at a glance all day, and
     * the order is the order they scan in.
     */
    protected function newLeadMessage(Lead $lead): string
    {
        $name = trim(($lead->first_name ?: $lead->name).' '.(string) $lead->last_name);

        // The feed puts all five UTMs on one "Source" line, space separated.
        $source = trim(implode(' ', array_filter([
            $lead->utm_source,
            $lead->utm_campaign,
            $lead->utm_medium,
            $lead->utm_content,
            $lead->utm_term,
        ])));

        return implode("\n", [
            '*NEW LEAD:*',
            '',
            '*Name*: '.($name ?: 'N/A'),
            '*Email:* '.($lead->email ?: 'N/A'),
            '*Phone:* '.($lead->phone ?: 'N/A'),
            '*Source:* '.($source !== '' ? $source : 'N/A'),
            '*Company Revenue:* '.($lead->monthly_revenue ?: 'N/A'),
            '*Landing page:* '.($lead->landing_page_base ?: $lead->landing_url ?: 'N/A'),
        ]);
    }

    protected function bookedMessage(Lead $lead, array $context): string
    {
        $name = trim(($lead->first_name ?: $lead->name).' '.(string) $lead->last_name);

        return implode("\n", array_filter([
            '*CALL BOOKED:*',
            '',
            '*Name*: '.($name ?: 'N/A'),
            '*Email:* '.($lead->email ?: 'N/A'),
            '*Phone:* '.($lead->phone ?: 'N/A'),
            '*Company Revenue:* '.($lead->monthly_revenue ?: 'N/A'),
            empty($context['start_time']) ? null : '*Meeting Time:* '.$context['start_time'],
            empty($context['meet_url']) ? null : '*Meeting:* '.$context['meet_url'],
        ]));
    }

    /**
     * Send by bot token where one is configured, else by incoming webhook.
     *
     * The bot path is what production uses and is the only one that can target a channel by id.
     * Both are supported because the webhook needs no Slack app, and a dedicated app is still
     * pending (WR-186).
     */
    protected function send(string $text): bool
    {
        $token = (string) (config('services.slack.bot_token') ?? '');
        $channel = (string) (config('services.slack.channel') ?? '');

        if ($token !== '' && $channel !== '') {
            $response = Http::withToken($token)
                ->timeout(5)
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text' => $text,
                    'mrkdwn' => true,
                ]);

            // Slack answers 200 with `ok: false` on an application error (bad token, missing
            // scope, bot not in channel), so the status code alone is not the outcome.
            if ($response->successful() && $response->json('ok') === true) {
                return true;
            }

            Log::warning('HandleLeadEventsForSlack: chat.postMessage rejected', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return false;
        }

        $webhookUrl = $this->webhookUrl();

        if ($webhookUrl === '') {
            Log::info('HandleLeadEventsForSlack: no bot token or webhook URL configured; nothing sent.');

            return false;
        }

        $response = Http::timeout(5)->post($webhookUrl, ['text' => $text]);

        return $response->successful();
    }

    protected function webhookUrl(): string
    {
        $url = (string) (config('services.slack.webhook_url') ?? '');

        if ($url === '' && function_exists('get_option')) {
            $url = (string) (get_option('rl_jlc_slack_webhook_url') ?: get_option('rl_slack_webhook_url') ?: '');
        }

        return $url;
    }
}
