<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Listeners;

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Scheduling\Support\SchedulerLink;
use App\Infrastructure\Queue\Deferred;

/**
 * On a completed booking: stamp the "add to calendar" link, then carry it to HubSpot.
 *
 * The legacy site put this URL on every booked entry (Gravity Forms field 66) and its HubSpot
 * feed sent it as `schedule_link` — the one booking-specific field the CRM ever received. v2
 * never set it: 0 of the first 597 site bookings had one.
 *
 * The stamp is synchronous, so every after-response consumer (LeadCreated's own HubSpot sync,
 * Slack, the outgoing webhook) reads it. The re-sync covers bookings confirmed later by the
 * retry ladder, after LeadCreated's sync already ran. It deliberately does not notify the n8n
 * `hubspot-lead-creation` flow: that already fired for this contact, and a second call there is a
 * second downstream record.
 */
class StampSchedulerLinkOnBooking
{
    public function handle(LeadBookingCompleted $event): void
    {
        $link = $event->provider === 'calendly' ? SchedulerLink::forMeeting($event->meetingId) : null;

        if ($link === null || $event->lead->is_blocked) {
            return;
        }

        // Always the newest booking's: a rebook must not keep pointing at the old meeting.
        if ($event->lead->scheduler_link !== $link) {
            $event->lead->forceFill(['scheduler_link' => $link])->saveQuietly();
        }

        Deferred::call(self::class, 'syncToHubSpot', [(int) $event->lead->id]);
    }

    /** Push the stamped link to the lead's HubSpot contact. Runs after the response. */
    public function syncToHubSpot(int $leadId): void
    {
        $lead = Lead::find($leadId);

        if (! $lead instanceof Lead || $lead->is_blocked) {
            return;
        }

        $contactId = app(HubSpotGateway::class)->syncContact($lead);

        app(LeadActivityLogger::class)->logConsumption(
            leadId: $lead->id,
            eventType: 'LeadBookingCompleted',
            actorDomain: 'HubSpot',
            outcome: $contactId ? 'succeeded' : 'failed',
            description: $contactId
                ? "Sent the booking's schedule link to HubSpot (ID: {$contactId})"
                : 'HubSpot schedule link sync failed',
            payload: ['hubspot_contact_id' => $contactId, 'schedule_link' => $lead->scheduler_link],
        );
    }
}
