<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CalendlyWebhookController;
use App\Domains\Lead\Events\LeadBookingCanceled;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

describe('CalendlyWebhookController', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();
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

        $request = Request::create('/api/webhooks/calendly', 'POST', $payload);
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

        $request = Request::create('/api/webhooks/calendly', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['status' => 'received']);

        $lead->refresh();
        expect($lead->status)->toBe('canceled');
        expect(count($canceledEvents))->toBe(1);
        expect($canceledEvents[0]->lead->id)->toBe($lead->id);
        expect($canceledEvents[0]->reason)->toBe('Schedule conflict with client presentation');
    });

    test('handles unhandled webhook events gracefully without throwing exceptions', function () {
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);
        $activityLogger = new LeadActivityLogger;

        $controller = new CalendlyWebhookController($mockRecordAction, $activityLogger);

        $payload = [
            'event' => 'routing_form_submission.created',
            'payload' => [],
        ];

        $request = Request::create('/api/webhooks/calendly', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe(['status' => 'received']);
    });
});
