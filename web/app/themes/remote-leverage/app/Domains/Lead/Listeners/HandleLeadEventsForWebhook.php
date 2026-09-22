<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The three outgoing lead webhooks: `lead_webhook_url` (the whole lead), `lead_form_url` (the
 * n8n `lead-form` flow) and `hubspot_lead_url` (the `hubspot-lead-creation` flow).
 *
 * All three exist to put a lead in front of the people who work it, which is why all three are
 * gated on {@see LeadSubmission::isSalesEnquiry()} — the same line the Slack alert draws, drawn
 * in the same place so the two cannot disagree. A gated-download lead is captured, stored and
 * synced to HubSpot; it just does not get announced, because there is nothing on it to act on.
 */
class HandleLeadEventsForWebhook
{
    /** The `submission_type` value that means the form was finished, not dropped. */
    private const COMPLETED = 'Final';

    /**
     * "EST" as the business means it: New York wall-clock time, which is EDT from March to
     * November. A fixed -05:00 would read an hour behind everyone's actual clock for most of
     * the year. The same zone the booking wizard, the cost alerts and the BigQuery views use.
     */
    private const EASTERN = 'America/New_York';

    public function __construct(
        protected LeadActivityLogger $activityLogger,
    ) {}

    /**
     * Handle LeadCreated event — the step-one partial capture *and* the completed submission.
     *
     * Both go out under `lead.partial_captured`, which is a name the wire is stuck with: the
     * n8n flow on the other end filters on it, so renaming the second one would stop it seeing
     * completed submissions until the flow grew a branch. What separates them is
     * `lead.submission_type` — `Partial` on the step-one capture, `Final` once the form is
     * completed — which is the same column the legacy Gravity Forms feed branched on. It is in
     * the payload because the payload is now the whole lead; it was not before, and a
     * consumer therefore could not tell the two apart at all.
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

        // Only leads somebody is going to work. See the class docblock.
        if (! $this->isSalesEnquiry($event->lead, 'lead.partial_captured')) {
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
     * The n8n `lead-form` flow: a lead was captured.
     *
     * Separate from the `lead.partial_captured` feed above rather than a branch of it. That one
     * is the Gravity Forms replacement and carries the whole lead; this one carries two fields
     * and exists so a flow can act on a capture without parsing 60 columns it does not read.
     * Folding them together would mean one URL's consumers constraining the other's payload.
     *
     * **Partial captures only.** `LeadCreated` is also raised for the completed submission, and
     * firing on both would POST twice for every lead that books — the same duplicate
     * `HandleLeadEventsForSlack` suppresses, and for the same reason.
     */
    public function handleLeadFormCaptured(LeadCreated $event): void
    {
        // Shadow ban, as above: stored, and silent downstream.
        if ($event->lead->is_blocked) {
            return;
        }

        if (! $this->isSalesEnquiry($event->lead, 'lead.form_captured')) {
            return;
        }

        if (! $this->isPartial($event->lead)) {
            return;
        }

        $url = (string) config('services.webhooks.lead_form_url');

        if ($url === '') {
            return;
        }

        $payload = array_merge([
            'event' => 'lead.form_captured',
            'lead_id' => $event->lead->id,
            'email' => (string) $event->lead->email,
            'submission_type' => (string) $event->lead->submission_type,
        ], $this->easternStamp('captured_at_est', $event->lead->created_at));

        $this->deliver($event->lead, $url, $payload, 'lead.form_captured', 'LeadCreated');
    }

    /**
     * The n8n `hubspot-lead-creation` flow: the contact reached the CRM.
     *
     * Called from `LeadServiceProvider` immediately after `HubSpotGateway::syncContact()`
     * rather than off an event, because the contact id it reports only exists at that point —
     * `LeadCreated` fires before the sync, so a listener on it would send a link to nothing.
     *
     * `$action` is `created` or `updated`; both send, which is what was asked for. The link is
     * built by the model, so it is the same URL the Slack card and the admin already use.
     *
     * **Partial captures only**, matching `handleLeadFormCaptured()` — the sync also runs on
     * the completed submission, and that one is deliberately not announced.
     */
    public function handleHubSpotSynced(Lead $lead, ?string $action = null): void
    {
        if ($lead->is_blocked) {
            return;
        }

        if (! $this->isSalesEnquiry($lead, 'lead.hubspot_synced')) {
            return;
        }

        if (! $this->isPartial($lead)) {
            return;
        }

        $url = (string) config('services.webhooks.hubspot_lead_url');
        $contactUrl = $lead->hubspotContactUrl();

        /*
         * No link, no send. `hubspotContactUrl()` returns null when the portal id is missing
         * as well as when the contact id is, and the entire point of this payload is the link
         * — posting `null` would have the flow announce a contact nobody can open.
         */
        if ($url === '' || $contactUrl === null) {
            return;
        }

        /*
         * Two timestamps, because they answer two different questions and the gap between
         * them is the interesting part — a `synced_at` well after `captured_at` is a HubSpot
         * call that queued or retried.
         *
         * `synced_at_est` is `now()` here, and that is correct rather than a shortcut: this
         * runs inside the same deferred job as the `syncContact()` call that just returned,
         * so "now" *is* when the contact reached the portal. There is no column holding it —
         * HubSpot's own `lastmodifieddate` would cost a round trip to read back.
         *
         * `captured_at_est` is the same field, same format, that `lead-form` sends, so one
         * n8n sub-workflow can read a capture time out of either payload without branching.
         */
        $payload = array_merge(
            [
                'event' => 'hubspot.contact_synced',
                'action' => $action ?? 'synced',
                'lead_id' => $lead->id,
                'email' => (string) $lead->email,
                'hubspot_contact_id' => (string) $lead->hubspot_contact_id,
                'hubspot_contact_url' => $contactUrl,
            ],
            $this->easternStamp('synced_at_est'),
            $this->easternStamp('captured_at_est', $lead->created_at),
        );

        $this->deliver($lead, $url, $payload, 'hubspot.contact_synced', 'LeadCreated');
    }

    /**
     * One instant, rendered three ways under `$prefix`, in Eastern wall-clock time.
     *
     * `$prefix` is the plain `Y-m-d H:i:s` string the flows format into their messages;
     * `{$prefix}_iso` carries the offset, so a consumer that wants a real instant back is not
     * left parsing a naive string; `{$prefix}_abbreviation` says whether daylight time was in
     * force. That last one earns its place because `America/New_York` reads EDT for most of
     * the year, and a field labelled only "est" invites a consumer to assume UTC-5.
     *
     * `$when` null means now. Pass the stored time wherever one exists: a send deferred with
     * `afterResponse()` happens seconds after the thing it describes, and unboundedly later
     * if it is ever really queued or retried.
     *
     * @return array<string, string>
     */
    protected function easternStamp(string $prefix, mixed $when = null): array
    {
        $at = ($when ? Carbon::parse($when) : Carbon::now())->setTimezone(self::EASTERN);

        return [
            $prefix => $at->format('Y-m-d H:i:s'),
            $prefix.'_iso' => $at->toIso8601String(),
            $prefix.'_abbreviation' => $at->format('T'),
        ];
    }

    /**
     * Is this the step-one capture rather than the completed submission?
     *
     * Case-insensitive and trimmed because two writers fill this column — the booking wizard
     * and the Gravity import — and only their agreement on the word is guaranteed, not on its
     * spelling. An empty value counts as partial: that is a lead that never reached step two,
     * and treating "unset" as "completed" would silence the flows for exactly the leads they
     * exist to report.
     */
    protected function isPartial(Lead $lead): bool
    {
        return strcasecmp(trim((string) $lead->submission_type), self::COMPLETED) !== 0;
    }

    /**
     * Is this a lead the flows on the other end exist to work — and log it when it is not.
     *
     * The log line is the point of wrapping {@see LeadSubmission::isSalesEnquiry()} here rather
     * than calling it three times inline. A webhook that did not fire leaves no trace anywhere
     * else, so without this the lead's timeline shows a capture and then nothing, which reads
     * identically to a delivery that failed silently.
     */
    protected function isSalesEnquiry(Lead $lead, string $eventName): bool
    {
        $submissionType = (string) $lead->submission_type;

        if (LeadSubmission::isSalesEnquiry($submissionType)) {
            return true;
        }

        $this->activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: $this->eventTypeFor($eventName),
            actorDomain: 'Webhook',
            outcome: 'skipped',
            description: "Not sent: {$submissionType} is not a sales enquiry",
            payload: ['event' => $eventName, 'submission_type' => $submissionType],
        );

        return false;
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
     *
     * The URL comes from `config/services.php`, which now carries the n8n endpoint as a
     * committed default; `LEAD_WEBHOOK_URL` overrides it and the wp-admin setting is the last
     * resort. Blanking the env var in an environment that also has no setting is still how the
     * feed is switched off.
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

        /*
         * The whole lead, not a curated subset.
         *
         * This used to be a hand-written list of 27 columns, which is a second schema kept in
         * sync by hand: every attribution column added since — `submission_type`, `device_id`,
         * `li_fat_id`, `fbc`, `oppref`, `partner`, `ip_address`, the HubSpot lifecycle mirror —
         * existed on the lead and never reached n8n, and nothing failed to say so. Sending
         * `toArray()` means a new column is on the wire the moment it is on the model.
         *
         * The key set is whatever the model holds, so a caller must hand this a hydrated lead
         * rather than the return of a bare `create()` — every current one does
         * (`CaptureLeadAction` refreshes after `IdentityResolver`, the Calendly webhook reads
         * the row back), and a half-hydrated one would quietly ship a shorter payload.
         */
        $payload = [
            'event' => $eventName,
            'timestamp' => Carbon::now()->toIso8601String(),
            'lead' => $lead->toArray(),
            'context' => $context,
        ];

        $this->deliver($lead, $webhookUrl, $payload, $eventName, $this->eventTypeFor($eventName));
    }

    /**
     * POST one JSON payload and record what came back on the lead's timeline.
     *
     * Shared by all three feeds. It was the tail of `dispatchWebhook()` and is factored out
     * unchanged — the `lead-form` and `hubspot-lead-creation` flows want the same delivery
     * semantics and the same audit entry, and a second copy of this is a second place for the
     * "logged a send that never happened" bug to come back.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function deliver(
        Lead $lead,
        string $webhookUrl,
        array $payload,
        string $eventName,
        string $eventType,
    ): void {
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
                    eventType: $eventType,
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
                eventType: $eventType,
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
