<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Listeners\HandleLeadEventsForWebhook;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSubmission;
use Illuminate\Support\Str;

/*
 * Which leads reach sales.
 *
 * The gated download on /impact-report-2026/ asks for a name and an email in exchange for a
 * PDF. It goes through the same CaptureLeadAction as the booking wizard, which is deliberate —
 * one lead path — but it is not a request to be contacted, and the sales fan-out treated it as
 * one: a "NEW LEAD" Slack card carrying two facts and no phone number, plus all three outgoing
 * webhooks, for somebody who had only downloaded a report.
 *
 * The line is drawn once, in LeadSubmission::isSalesEnquiry(), and these tests hold both
 * listeners to it — including the part that matters more: an ordinary lead still gets through.
 */

/** A Slack listener that records what it would send instead of reaching Slack. */
function fanOutSlackListener(): object
{
    return new class(app(LeadActivityLogger::class)) extends HandleLeadEventsForSlack
    {
        public array $sent = [];

        protected function send(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
        ): ?array {
            $this->sent[] = $text;

            return ['ts' => '1726500000.000100', 'channel' => 'C086BBKUXL5'];
        }
    };
}

function fanOutLead(?string $submissionType): Lead
{
    return Lead::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Pam Beesly',
        'first_name' => 'Pam',
        'last_name' => 'Beesly',
        'email' => 'pam@dundermifflin.com',
        'status' => 'captured',
        'submission_type' => $submissionType,
    ]);
}

describe('the sales fan-out', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();

        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);

        // All three webhooks configured, so a silent test means "declined to send" rather
        // than "had nowhere to send it".
        config([
            'services.webhooks.lead_webhook_url' => 'https://n8n.test/webhook/gravityforms-leads',
            'services.webhooks.lead_form_url' => 'https://n8n.test/webhook/lead-form',
            'services.webhooks.hubspot_lead_url' => 'https://n8n.test/webhook/hubspot-lead-creation',
        ]);

        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);
    });

    test('a gated download is not announced in Slack', function () {
        $lead = fanOutLead(LeadSubmission::stored(LeadSubmission::GATED_DOWNLOAD));
        $listener = fanOutSlackListener();

        $listener->handleCreated(new LeadCreated($lead, []));

        expect($listener->sent)->toBe([]);

        // Silence with a reason. A lead that produced no card and no log line is
        // indistinguishable from one whose post failed.
        $log = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Slack')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->outcome)->toBe('skipped')
            ->and($log->description)->toContain('not a sales enquiry');
    });

    test('a gated download fires none of the three outgoing webhooks', function () {
        $lead = fanOutLead(LeadSubmission::stored(LeadSubmission::GATED_DOWNLOAD));
        $lead->refresh();

        $listener = new HandleLeadEventsForWebhook(new LeadActivityLogger);
        $event = new LeadCreated($lead, []);

        $listener->handleCreated($event);            // lead.partial_captured
        $listener->handleLeadFormCaptured($event);   // lead.form_captured
        $listener->handleHubSpotSynced($lead, 'created'); // lead.hubspot_synced

        expect($GLOBALS['_wp_remote_post_calls'])->toBe([]);

        expect(LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Webhook')
            ->where('outcome', 'skipped')
            ->count())->toBe(3);
    });

    test('an ordinary partial capture still reaches Slack and the webhooks', function () {
        $lead = fanOutLead('Partial');
        $lead->refresh();

        $slack = fanOutSlackListener();
        $slack->handleCreated(new LeadCreated($lead, []));

        expect($slack->sent)->toHaveCount(1);

        $webhooks = new HandleLeadEventsForWebhook(new LeadActivityLogger);
        $webhooks->handleCreated(new LeadCreated($lead, []));
        $webhooks->handleLeadFormCaptured(new LeadCreated($lead, []));

        expect($GLOBALS['_wp_remote_post_calls'])->toHaveCount(2);
    });

    test('an unrecognised submission type is treated as a sales enquiry', function () {
        // Silence is the expensive failure: a form that starts writing a word nobody has
        // heard of should still put the lead in front of somebody.
        expect(LeadSubmission::isSalesEnquiry(null))->toBeTrue()
            ->and(LeadSubmission::isSalesEnquiry(''))->toBeTrue()
            ->and(LeadSubmission::isSalesEnquiry('Partial'))->toBeTrue()
            ->and(LeadSubmission::isSalesEnquiry('Final'))->toBeTrue()
            ->and(LeadSubmission::isSalesEnquiry('Webinar Signup'))->toBeTrue()
            ->and(LeadSubmission::isSalesEnquiry('Gated Download'))->toBeFalse()
            // The column is free text; the stored spelling is what a writer gets almost-right.
            ->and(LeadSubmission::isSalesEnquiry('  gated download '))->toBeFalse()
            ->and(LeadSubmission::isSalesEnquiry('gated_download'))->toBeFalse();
    });
});
