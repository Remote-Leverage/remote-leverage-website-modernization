<?php

declare(strict_types=1);

use App\Application\Http\Support\WebhookSignature;

describe('WebhookSignature', function () {
    test('accepts a signature it generated', function () {
        $body = '{"type":"payment_intent.succeeded"}';

        expect(WebhookSignature::verify($body, WebhookSignature::sign($body, 'secret'), 'secret'))->toBeNull();
    });

    test('refuses when no secret is configured, so a caller that forgets the guard still fails closed', function () {
        $body = '{}';

        expect(WebhookSignature::verify($body, WebhookSignature::sign($body, ''), ''))
            ->toBe('No signing secret configured.');
    });

    test('rejects a missing header', function () {
        expect(WebhookSignature::verify('{}', '', 'secret'))->toBe('Missing signature header.');
    });

    test('rejects a malformed header', function () {
        expect(WebhookSignature::verify('{}', 'garbage', 'secret'))->toBe('Invalid signature header format.');
        expect(WebhookSignature::verify('{}', 't=123', 'secret'))->toBe('Invalid signature header format.');
    });

    test('rejects a replayed event outside the tolerance window', function () {
        $body = '{}';
        $stale = time() - (WebhookSignature::TOLERANCE + 1);

        expect(WebhookSignature::verify($body, WebhookSignature::sign($body, 'secret', $stale), 'secret'))
            ->toBe('Webhook timestamp outside tolerance window.');
    });

    test('rejects a signature made with a different secret', function () {
        $body = '{}';

        expect(WebhookSignature::verify($body, WebhookSignature::sign($body, 'attacker'), 'secret'))
            ->toBe('Webhook signature does not match.');
    });

    test('rejects a body tampered with after signing', function () {
        $signature = WebhookSignature::sign('{"amount":100}', 'secret');

        expect(WebhookSignature::verify('{"amount":1}', $signature, 'secret'))
            ->toBe('Webhook signature does not match.');
    });
});
