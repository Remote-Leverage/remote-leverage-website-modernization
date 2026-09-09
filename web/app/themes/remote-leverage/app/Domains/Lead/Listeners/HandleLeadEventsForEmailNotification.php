<?php

declare(strict_types=1);

namespace App\Domains\Lead\Listeners;

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
use Illuminate\Support\Facades\Log;

class HandleLeadEventsForEmailNotification
{
    public function __construct(
        protected LeadActivityLogger $activityLogger,
        protected LeadSettingsService $settings,
    ) {}

    /**
     * Notify admin-configured recipients (WR-102, ADR-0008 § Form Configuration) when a new lead is captured.
     */
    public function handleCreated(LeadCreated $event): void
    {
        $recipients = $this->settings->get()['notification_emails'] ?? [];

        if (empty($recipients)) {
            return;
        }

        $success = $this->sendNotification($recipients, $event->lead);

        $this->activityLogger->logConsumption(
            leadId: $event->lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'EmailNotification',
            outcome: $success ? 'succeeded' : 'failed',
            description: $success
                ? 'Dispatched new-lead email notification to '.count($recipients).' recipient(s)'
                : 'Failed to dispatch new-lead email notification',
            payload: ['recipients' => $recipients]
        );
    }

    protected function sendNotification(array $recipients, Lead $lead): bool
    {
        if (! function_exists('wp_mail')) {
            return false;
        }

        try {
            $subject = "New Lead Captured: {$lead->name} ({$lead->email})";
            $body = "A new lead was captured on Remote Leverage.\n\n"
                ."Name: {$lead->name}\n"
                ."Email: {$lead->email}\n"
                ."Phone: {$lead->phone}\n"
                ."Source: {$lead->source_type} / {$lead->source_id}\n"
                ."Status: {$lead->status}\n";

            return (bool) wp_mail($recipients, $subject, $body);
        } catch (\Throwable $e) {
            Log::error("HandleLeadEventsForEmailNotification: Exception notifying recipients for lead #{$lead->id}: ".$e->getMessage());

            return false;
        }
    }
}
