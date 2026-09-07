<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CalendlyWebhookController;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use Illuminate\Http\Request;

describe('CalendlyWebhookController', function () {
    test('handles invitee.created webhook event and dispatches behavioral event', function () {
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);

        $mockRecordAction->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (AnalyticsEventData $event) {
                return $event->event === 'Consultation Scheduled'
                    && $event->distinctId === 'michael.scott@dundermifflin.com'
                    && $event->properties['invitee_name'] === 'Michael Scott'
                    && $event->properties['event_type'] === '45-Min Remote Talent Architecture Consultation'
                    && $event->properties['scheduled_time'] === '2026-09-15T15:00:00.000000Z';
            }));

        $controller = new CalendlyWebhookController($mockRecordAction);

        $payload = [
            'event' => 'invitee.created',
            'payload' => [
                'invitee' => [
                    'name' => 'Michael Scott',
                    'email' => 'michael.scott@dundermifflin.com',
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

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['status' => 'received']);
    });

    test('ignores unhandled webhook events gracefully without throwing exceptions', function () {
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);
        $mockRecordAction->expects($this->never())->method('execute');

        $controller = new CalendlyWebhookController($mockRecordAction);

        $payload = [
            'event' => 'invitee.canceled',
            'payload' => [
                'invitee' => ['email' => 'jim.halpert@dundermifflin.com'],
            ],
        ];

        $request = Request::create('/api/webhooks/calendly', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['status' => 'received']);
    });
});
