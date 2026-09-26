<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Gateways\MetaConversionsApiClient;
use App\Infrastructure\Observability\IntegrationCallRecorder;
use Illuminate\Support\Facades\Log;

/**
 * Sends the server-side `invitee_meeting_scheduled` to Meta when a lead's Calendly booking
 * succeeds, and records the attempt on the lead's timeline.
 *
 * Server-side because Calendly's own Meta Pixel integration never sees these bookings: the wizard
 * books through Calendly's API, so no Calendly page loads to fire it. Fired from the booking
 * rather than the thank-you page, so a refresh or a direct visit to `/VAThankYou/` does not
 * count — that is what the browser `Schedule` event does, and why it reads higher.
 *
 * Separate from SendLeadToMetaConversionsApi for the same reason that one is separate from
 * Customer.io: they fire on different events and fail independently.
 */
class SendBookingToMetaConversionsApi
{
    public function __construct(
        protected MetaConversionsApiClient $client,
        protected LeadActivityLogger $activityLogger,
    ) {}

    public function handle(LeadBookingCompleted $event): void
    {
        $lead = $event->lead;

        try {
            if ($this->alreadySent($lead)) {
                return;
            }

            // Ties the Graph call to this lead in rl_integration_calls; see SendLeadToMetaConversionsApi.
            app(IntegrationCallRecorder::class)->forLead($lead->id);

            $result = $this->client->sendBooking($lead);

            if ($result['skipped']) {
                $this->activityLogger->logConsumption(
                    leadId: $lead->id,
                    eventType: 'LeadBookingCompleted',
                    actorDomain: 'Meta',
                    outcome: 'skipped',
                    description: 'Meta Conversions API not configured — no server-side '
                        .MetaConversionsApiClient::BOOKING_EVENT.' sent. Facebook will not count this booking.',
                    payload: ['event_id' => $result['event_id']],
                );

                return;
            }

            $failed = $result['failed'] !== [];

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Meta',
                outcome: $failed ? 'failed' : 'succeeded',
                description: $this->describe($result),
                payload: [
                    'event_name' => MetaConversionsApiClient::BOOKING_EVENT,
                    'event_id' => $result['event_id'],
                    'meeting_id' => $event->meetingId,
                    'provider' => $event->provider,
                    'pixels_sent' => $result['sent'],
                    'pixels_failed' => $result['failed'],
                    'errors' => $result['errors'],
                ],
            );
        } catch (\Throwable $e) {
            Log::error("SendBookingToMetaConversionsApi: Error processing lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadBookingCompleted',
                actorDomain: 'Meta',
                outcome: 'failed',
                description: 'Failed to send Meta Conversions API '.MetaConversionsApiClient::BOOKING_EVENT.': '.$e->getMessage(),
            );
        }
    }

    /**
     * Whether this lead's booking has already reached Meta.
     *
     * One booking can raise LeadBookingCompleted twice: once when the wizard's API call succeeds
     * (HandleLeadCreatedForBooking) and again when Calendly's `invitee.created` webhook arrives
     * for the same meeting. The event id is stable per lead, but Meta only documents collapsing a
     * browser/server pair, so the second send is stopped here rather than left to Meta.
     *
     * Only a succeeded send counts, so a failed first attempt leaves the webhook free to retry.
     * A lead who cancels and rebooks is counted once — which is also how LeadBookings counts it.
     */
    private function alreadySent(Lead $lead): bool
    {
        return LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('event_type', 'LeadBookingCompleted')
            ->where('actor_domain', 'Meta')
            ->where('outcome', 'succeeded')
            ->exists();
    }

    /**
     * @param  array{sent: list<string>, failed: list<string>, skipped: bool, event_id: string, errors: list<string>}  $result
     */
    private function describe(array $result): string
    {
        $event = MetaConversionsApiClient::BOOKING_EVENT;

        if ($result['failed'] === []) {
            return "Sent Meta Conversions API {$event} to pixel ".implode(', ', $result['sent']);
        }

        if ($result['sent'] === []) {
            return "Meta Conversions API {$event} rejected by pixel ".implode(', ', $result['failed']);
        }

        return sprintf(
            'Meta Conversions API %s sent to pixel %s, rejected by %s',
            $event,
            implode(', ', $result['sent']),
            implode(', ', $result['failed']),
        );
    }
}
