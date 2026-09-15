<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CalendlyWebhookController;
use App\Application\Http\Controllers\StripeWebhookController;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use Illuminate\Support\Str;

/**
 * Both webhook endpoints mutate money-adjacent state — the Stripe one forwards a "payment
 * succeeded" to the onboarding automations, the Calendly one flips a lead to booked/canceled.
 * They previously accepted unsigned payloads when no secret was configured. These tests pin
 * the refusal, so the fail-open behaviour cannot come back unnoticed.
 */
describe('webhook endpoints fail closed', function () {
    test('stripe refuses with 503 when no signing secret is configured', function () {
        config(['services.stripe.webhook_secret' => '']);

        $referrer = Referrer::create([
            'name' => 'Unverified',
            'email' => 'unverified@test.com',
            'referral_code' => 'unverified',
            'stripe_account_id' => 'acct_unverified',
            'status' => 'pending',
        ]);

        $request = signedWebhookRequest('/api/webhooks/stripe', [
            'type' => 'account.updated',
            'data' => ['object' => ['id' => 'acct_unverified', 'payouts_enabled' => true]],
        ], 'anything', 'Stripe-Signature');

        $response = (new StripeWebhookController)->handle($request);

        expect($response->getStatusCode())->toBe(503);

        // The refusal must be total: the event body is never acted on.
        $referrer->refresh();
        expect($referrer->status)->toBe('pending');
    });

    test('stripe rejects a forged payment_intent.succeeded with 403', function () {
        config(['services.stripe.webhook_secret' => 'whsec_real_secret']);

        $request = signedWebhookRequest('/api/webhooks/stripe', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_forged', 'amount' => 10000]],
        ], 'whsec_the_attackers_guess', 'Stripe-Signature');

        $response = (new StripeWebhookController)->handle($request);

        expect($response->getStatusCode())->toBe(403);
        expect($response->getData(true))->toBe(['error' => 'Signature verification failed.']);
    });

    test('calendly refuses with 503 when no signing key is configured', function () {
        config(['services.calendly.webhook_signing_key' => '']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pam Beesly',
            'email' => 'pam@dundermifflin.com',
            'status' => 'booking_pending',
        ]);

        $controller = new CalendlyWebhookController(
            $this->createMock(RecordBehaviorEventAction::class),
            new LeadActivityLogger,
        );

        $request = signedWebhookRequest('/api/webhooks/calendly', [
            'event' => 'invitee.created',
            'payload' => ['invitee' => ['email' => 'pam@dundermifflin.com', 'uri' => 'forged']],
        ], 'anything', 'Calendly-Webhook-Signature');

        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(503);

        $lead->refresh();
        expect($lead->status)->toBe('booking_pending');
    });

    test('calendly rejects a forged booking with 403 and leaves the lead untouched', function () {
        config(['services.calendly.webhook_signing_key' => 'calendly_real_key']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Jim Halpert',
            'email' => 'jim@dundermifflin.com',
            'status' => 'booking_pending',
        ]);

        $controller = new CalendlyWebhookController(
            $this->createMock(RecordBehaviorEventAction::class),
            new LeadActivityLogger,
        );

        $request = signedWebhookRequest('/api/webhooks/calendly', [
            'event' => 'invitee.created',
            'payload' => ['invitee' => ['email' => 'jim@dundermifflin.com', 'uri' => 'forged']],
        ], 'calendly_wrong_key', 'Calendly-Webhook-Signature');

        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(403);

        $lead->refresh();
        expect($lead->status)->toBe('booking_pending');
    });
});
