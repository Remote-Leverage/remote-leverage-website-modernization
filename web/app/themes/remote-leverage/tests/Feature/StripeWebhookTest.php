<?php

declare(strict_types=1);

use App\Application\Http\Controllers\StripeWebhookController;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Http\Request;

describe('StripeWebhookController', function () {
    beforeEach(function () {
        Referrer::truncate();
        Payout::truncate();
    });

    test('account.updated enables referrer when payouts_enabled is true', function () {
        $referrer = Referrer::create([
            'name' => 'Stripe Referrer 1',
            'email' => 'referrer1@test.com',
            'referral_code' => 'r1',
            'stripe_account_id' => 'acct_12345',
            'status' => 'pending',
        ]);

        $controller = new StripeWebhookController;

        $payload = [
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_12345',
                    'payouts_enabled' => true,
                ],
            ],
        ];

        $request = Request::create('/api/webhooks/stripe', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['status' => 'success']);

        $referrer->refresh();
        expect($referrer->status)->toBe('active');
    });

    test('account.updated sets referrer to pending when payouts_enabled is false', function () {
        $referrer = Referrer::create([
            'name' => 'Stripe Referrer 2',
            'email' => 'referrer2@test.com',
            'referral_code' => 'r2',
            'stripe_account_id' => 'acct_98765',
            'status' => 'active',
        ]);

        $controller = new StripeWebhookController;

        $payload = [
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_98765',
                    'payouts_enabled' => false,
                ],
            ],
        ];

        $request = Request::create('/api/webhooks/stripe', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);

        $referrer->refresh();
        expect($referrer->status)->toBe('pending');
    });

    test('transfer.paid marks payout as completed in database', function () {
        $referrer = Referrer::create([
            'name' => 'Referrer Payout',
            'email' => 'payout@test.com',
            'referral_code' => 'pp',
        ]);

        $payout = Payout::create([
            'referrer_id' => $referrer->id,
            'amount' => 450.00,
            'currency' => 'USD',
            'status' => 'pending',
            'stripe_transfer_id' => 'tr_paid_123',
        ]);

        $controller = new StripeWebhookController;

        $payload = [
            'type' => 'transfer.paid',
            'data' => [
                'object' => [
                    'id' => 'tr_paid_123',
                ],
            ],
        ];

        $request = Request::create('/api/webhooks/stripe', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);

        $payout->refresh();
        expect($payout->status)->toBe('completed');
    });

    test('transfer.failed marks payout as failed with reason', function () {
        $referrer = Referrer::create([
            'name' => 'Referrer Failed',
            'email' => 'failed@test.com',
            'referral_code' => 'pf',
        ]);

        $payout = Payout::create([
            'referrer_id' => $referrer->id,
            'amount' => 600.00,
            'currency' => 'USD',
            'status' => 'pending',
            'stripe_transfer_id' => 'tr_fail_456',
        ]);

        $controller = new StripeWebhookController;

        $payload = [
            'type' => 'transfer.failed',
            'data' => [
                'object' => [
                    'id' => 'tr_fail_456',
                    'failure_message' => 'Bank account closed or invalid routing number',
                ],
            ],
        ];

        $request = Request::create('/api/webhooks/stripe', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);

        $payout->refresh();
        expect($payout->status)->toBe('failed');
        expect($payout->notes)->toBe('Bank account closed or invalid routing number');
    });

    test('returns success response for unhandled webhook events without throwing', function () {
        $controller = new StripeWebhookController;

        $payload = [
            'type' => 'charge.succeeded',
            'data' => ['object' => ['id' => 'ch_123']],
        ];

        $request = Request::create('/api/webhooks/stripe', 'POST', $payload);
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toBe(['status' => 'success']);
    });
});
