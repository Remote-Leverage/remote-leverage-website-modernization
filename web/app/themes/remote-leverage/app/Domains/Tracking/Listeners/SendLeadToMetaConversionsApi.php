<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Listeners;

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Gateways\MetaConversionsApiClient;
use App\Infrastructure\Observability\IntegrationCallRecorder;
use Illuminate\Support\Facades\Log;

/**
 * Sends the server-side `Lead` to Meta when a lead is captured, and records the attempt on the
 * lead's timeline.
 *
 * Kept separate from HandleLeadCreatedForTracking rather than folded into it: that listener owns
 * Customer.io and PostHog, and a Meta outage must not cost us the Customer.io identify (or the
 * reverse). They are independent vendors on the same event and they fail independently.
 *
 * The timeline entry is the point of this class as much as the send is. Meta reporting 0
 * conversions was undiagnosable for a day precisely because nothing anywhere recorded that no
 * event had been sent — an empty Ads Manager looks exactly like no traffic. Now every lead
 * carries its own answer.
 */
class SendLeadToMetaConversionsApi
{
    public function __construct(
        protected MetaConversionsApiClient $client,
        protected LeadActivityLogger $activityLogger,
    ) {}

    public function handle(LeadCreated $event): void
    {
        $lead = $event->lead;

        try {
            /*
             * Associates the outbound Graph call with this lead in rl_integration_calls, so the
             * timeline carries the wire record (URL, status, duration) next to the decision below.
             * Ambient per-request state, and this runs afterResponse() in the same process, so it
             * is re-asserted here rather than assumed to have survived from CaptureLeadAction.
             */
            app(IntegrationCallRecorder::class)->forLead($lead->id);

            $result = $this->client->sendLead($lead);

            if ($result['skipped']) {
                $this->activityLogger->logConsumption(
                    leadId: $lead->id,
                    eventType: 'LeadCreated',
                    actorDomain: 'Meta',
                    outcome: 'skipped',
                    description: 'Meta Conversions API not configured — no server-side Lead sent. '
                        .'Facebook will not count this conversion.',
                    payload: ['event_id' => $result['event_id']],
                );

                return;
            }

            $failed = $result['failed'] !== [];

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Meta',
                outcome: $failed ? 'failed' : 'succeeded',
                description: $this->describe($result),
                payload: [
                    'event_name' => 'Lead',
                    'event_id' => $result['event_id'],
                    'pixels_sent' => $result['sent'],
                    'pixels_failed' => $result['failed'],
                    'errors' => $result['errors'],
                    // Which identifiers Meta actually got. The difference between a matched and
                    // an unmatched conversion is almost always a missing fbc, and this is where
                    // you look when the match rate drops.
                    'match_keys' => $this->client->matchKeys($lead),
                ],
            );
        } catch (\Throwable $e) {
            Log::error("SendLeadToMetaConversionsApi: Error processing lead #{$lead->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'LeadCreated',
                actorDomain: 'Meta',
                outcome: 'failed',
                description: 'Failed to send Meta Conversions API Lead: '.$e->getMessage(),
            );
        }
    }

    /**
     * @param  array{sent: list<string>, failed: list<string>, skipped: bool, event_id: string, errors: list<string>}  $result
     */
    private function describe(array $result): string
    {
        if ($result['failed'] === []) {
            return 'Sent Meta Conversions API Lead to pixel '.implode(', ', $result['sent']);
        }

        if ($result['sent'] === []) {
            return 'Meta Conversions API Lead rejected by pixel '.implode(', ', $result['failed']);
        }

        return sprintf(
            'Meta Conversions API Lead sent to pixel %s, rejected by %s',
            implode(', ', $result['sent']),
            implode(', ', $result['failed']),
        );
    }
}
