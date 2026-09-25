<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CalendlyWebhookController;
use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\PartnerHub\Support\PartnershipSettings;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

describe('CalendlyWebhookController', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();

        config(['services.calendly.webhook_signing_key' => 'calendly_test_key']);
        delete_option(PartnershipSettings::OPTION);
    });

    test('handles invitee.created, updates lead to booked, logs activity, and dispatches LeadBookingCompleted', function () {
        $completedEvents = [];
        Event::listen(LeadBookingCompleted::class, function ($e) use (&$completedEvents) {
            $completedEvents[] = $e;
        });

        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Michael Scott',
            'email' => 'michael.scott@dundermifflin.com',
            'status' => 'booking_pending',
        ]);

        $controller = new CalendlyWebhookController($mockRecordAction, $activityLogger);

        $payload = [
            'event' => 'invitee.created',
            'payload' => [
                'invitee' => [
                    'name' => 'Michael Scott',
                    'email' => 'michael.scott@dundermifflin.com',
                    'uri' => 'https://api.calendly.com/scheduled_events/xyz/invitees/123',
                    'scheduling_url' => 'https://calendly.com/resched/123',
                ],
                'event_type' => [
                    'name' => '45-Min Remote Talent Architecture Consultation',
                ],
                'event' => [
                    'start_time' => '2026-09-15T15:00:00.000000Z',
                ],
            ],
        ];

        $request = signedWebhookRequest('/api/webhooks/calendly', $payload, 'calendly_test_key', 'Calendly-Webhook-Signature');
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['status' => 'received']);

        $lead->refresh();
        expect($lead->status)->toBe('booked');
        expect(count($completedEvents))->toBe(1);
        expect($completedEvents[0]->lead->id)->toBe($lead->id);
    });

    test('handles invitee.canceled, updates lead to canceled, logs activity, and dispatches LeadBookingCanceled', function () {
        $canceledEvents = [];
        Event::listen(LeadBookingCanceled::class, function ($e) use (&$canceledEvents) {
            $canceledEvents[] = $e;
        });

        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Jim Halpert',
            'email' => 'jim.halpert@dundermifflin.com',
            'status' => 'booked',
        ]);

        $controller = new CalendlyWebhookController($mockRecordAction, $activityLogger);

        $payload = [
            'event' => 'invitee.canceled',
            'payload' => [
                'invitee' => ['email' => 'jim.halpert@dundermifflin.com'],
                'cancellation' => [
                    'reason' => 'Schedule conflict with client presentation',
                    'canceler_type' => 'invitee',
                ],
            ],
        ];

        $request = signedWebhookRequest('/api/webhooks/calendly', $payload, 'calendly_test_key', 'Calendly-Webhook-Signature');
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['status' => 'received']);

        $lead->refresh();
        expect($lead->status)->toBe('canceled');
        expect(count($canceledEvents))->toBe(1);
        expect($canceledEvents[0]->lead->id)->toBe($lead->id);
        expect($canceledEvents[0]->reason)->toBe('Schedule conflict with client presentation');
    });

    /*
     * A partnership call booked from /become-a-partner/ goes through the same webhook, and its
     * invitee may also be a lead. Neither shape of body may mark that lead booked or canceled.
     */
    $partnershipCase = function (array $payload, string $event = 'invitee.created'): array {
        $completed = [];
        $canceled = [];
        Event::listen(LeadBookingCompleted::class, function ($e) use (&$completed) {
            $completed[] = $e;
        });
        Event::listen(LeadBookingCanceled::class, function ($e) use (&$canceled) {
            $canceled[] = $e;
        });

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pam Beesly',
            'email' => 'pam@agency.example',
            'status' => 'contacted',
        ]);

        $recorder = test()->createMock(RecordBehaviorEventAction::class);
        $recorder->expects(test()->never())->method('execute');

        $controller = new CalendlyWebhookController($recorder, new LeadActivityLogger);
        $response = $controller->handle(signedWebhookRequest('/api/webhooks/calendly', ['event' => $event, 'payload' => $payload], 'calendly_test_key', 'Calendly-Webhook-Signature'));

        return [$response, $lead->refresh(), $completed, $canceled];
    };

    test('a v1 partnership booking, matched on the scheduling page slug, leaves the lead alone', function () use ($partnershipCase) {
        partnershipSettings(['calendly_url' => 'https://calendly.com/remoteleverage/partnership-call']);

        [$response, $lead, $completed] = $partnershipCase([
            'invitee' => ['email' => 'pam@agency.example', 'uri' => 'https://api.calendly.com/scheduled_events/a/invitees/b'],
            'event_type' => ['slug' => 'partnership-call', 'name' => 'Partnership Call'],
        ]);

        expect($response->getData(true))->toBe(['status' => 'received'])
            ->and($lead->status)->toBe('contacted')
            ->and($completed)->toBe([]);
    });

    test('a v2 partnership booking, matched on the event type URI, leaves the lead alone', function () use ($partnershipCase) {
        partnershipSettings(['calendly_event_type' => 'https://api.calendly.com/event_types/partner-123']);

        [, $lead, $completed] = $partnershipCase([
            'email' => 'pam@agency.example',
            'invitee' => ['email' => 'pam@agency.example'],
            'scheduled_event' => ['event_type' => 'https://api.calendly.com/event_types/partner-123'],
        ]);

        expect($lead->status)->toBe('contacted')
            ->and($completed)->toBe([]);
    });

    test('cancelling a partnership call does not cancel the lead either', function () use ($partnershipCase) {
        partnershipSettings(['calendly_url' => 'https://calendly.com/remoteleverage/partnership-call']);

        [, $lead, , $canceled] = $partnershipCase([
            'invitee' => ['email' => 'pam@agency.example'],
            'event_type' => ['slug' => 'partnership-call'],
            'cancellation' => ['reason' => 'Conflict'],
        ], 'invitee.canceled');

        expect($lead->status)->toBe('contacted')
            ->and($canceled)->toBe([]);
    });

    test('a sales booking still books the lead with the partnership call configured', function () {
        partnershipSettings([
            'calendly_url' => 'https://calendly.com/remoteleverage/partnership-call',
            'calendly_event_type' => 'https://api.calendly.com/event_types/partner-123',
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pam Beesly',
            'email' => 'pam@agency.example',
            'status' => 'booking_pending',
        ]);

        $controller = new CalendlyWebhookController($this->createMock(RecordBehaviorEventAction::class), new LeadActivityLogger);
        $controller->handle(signedWebhookRequest('/api/webhooks/calendly', [
            'event' => 'invitee.created',
            'payload' => [
                'invitee' => ['email' => 'pam@agency.example', 'uri' => 'https://api.calendly.com/scheduled_events/c/invitees/d'],
                'event_type' => ['slug' => '45-min-consultation', 'uuid' => 'sales-t10'],
                'scheduled_event' => ['event_type' => 'https://api.calendly.com/event_types/sales-t10'],
            ],
        ], 'calendly_test_key', 'Calendly-Webhook-Signature'));

        expect($lead->refresh()->status)->toBe('booked');
    });

    test('handles unhandled webhook events gracefully without throwing exceptions', function () {
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);
        $activityLogger = new LeadActivityLogger;

        $controller = new CalendlyWebhookController($mockRecordAction, $activityLogger);

        $payload = [
            'event' => 'routing_form_submission.created',
            'payload' => [],
        ];

        $request = signedWebhookRequest('/api/webhooks/calendly', $payload, 'calendly_test_key', 'Calendly-Webhook-Signature');
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['status' => 'received']);
    });
});
