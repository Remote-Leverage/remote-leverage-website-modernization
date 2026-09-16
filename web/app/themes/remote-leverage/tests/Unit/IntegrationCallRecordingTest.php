<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForWebhook;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Infrastructure\Observability\CredentialRegistry;
use App\Infrastructure\Observability\IntegrationCall;
use App\Infrastructure\Observability\IntegrationCallRecorder;
use App\Infrastructure\WordPress\Admin\IntegrationIcons;
use Illuminate\Support\Str;

/*
 * Integration call recording.
 *
 * The log existed before this and said "Dispatched lead.booking_completed outgoing webhook" with
 * a truncated URL — enough to know something was attempted, never enough to know why it failed.
 * These tests hold the line on the two properties that make the replacement worth having: it
 * records what actually crossed the wire, and it does not become a place credentials leak from.
 */

beforeEach(function () {
    IntegrationCall::query()->delete();

    config(['observability.integration_calls.enabled' => true]);
    config(['observability.integration_calls.record_unknown_hosts' => false]);
    config(['observability.integration_calls.max_body_bytes' => 65536]);
    config(['observability.integration_calls.hosts' => [
        'calendly.com' => 'calendly',
        'hubapi.com' => 'hubspot',
    ]]);
    config(['observability.integration_calls.redact_headers' => ['authorization', 'x-api-key']]);
    config(['observability.integration_calls.redact_keys' => ['token', 'client_secret']]);
});

describe('what gets recorded', function () {
    test('a request and its response are stored in full', function () {
        $recorder = new IntegrationCallRecorder;

        $recorder->record(
            method: 'post',
            url: 'https://api.hubapi.com/crm/v3/objects/contacts',
            headers: ['Content-Type' => 'application/json'],
            body: '{"properties":{"email":"a@b.com","intake_form":"yes"}}',
            statusCode: 400,
            responseHeaders: ['Content-Type' => 'application/json'],
            responseBody: '{"message":"Property values were not valid: intake_form"}',
            durationMs: 214,
        );

        $call = IntegrationCall::query()->first();

        // The whole point: the rejected property is legible from the log alone.
        expect($call->integration)->toBe('hubspot')
            ->and($call->method)->toBe('POST')
            ->and($call->status_code)->toBe(400)
            ->and($call->outcome)->toBe('failed')
            ->and($call->duration_ms)->toBe(214)
            ->and($call->request_body)->toContain('intake_form')
            ->and($call->response_body)->toContain('Property values were not valid');
    });

    test('a transport failure is recorded as error, distinct from a rejection', function () {
        // A timeout leaves no response to inspect anywhere else, and "no answer" is a different
        // diagnosis from "an answer we did not like".
        $recorder = new IntegrationCallRecorder;

        $recorder->record(
            method: 'GET',
            url: 'https://api.calendly.com/scheduled_events',
            errorMessage: 'cURL error 28: Operation timed out',
        );

        $call = IntegrationCall::query()->first();

        expect($call->outcome)->toBe('error')
            ->and($call->status_code)->toBeNull()
            ->and($call->error_message)->toContain('timed out');
    });

    test('hosts we do not name are not recorded', function () {
        // Otherwise a single blog-import run buries the integration traffic entirely.
        (new IntegrationCallRecorder)->record('GET', 'https://example.org/some/page');

        expect(IntegrationCall::query()->count())->toBe(0);
    });

    test('an unknown host is recorded when explicitly allowed', function () {
        config(['observability.integration_calls.record_unknown_hosts' => true]);

        (new IntegrationCallRecorder)->record('GET', 'https://example.org/some/page');

        expect(IntegrationCall::query()->first()->integration)->toBe('other');
    });

    test('subdomains of a named host resolve to that integration', function () {
        (new IntegrationCallRecorder)->record('GET', 'https://api.calendly.com/users/me', statusCode: 200);

        expect(IntegrationCall::query()->first()->integration)->toBe('calendly');
    });

    test('ids are collapsed so calls can be grouped', function () {
        $recorder = new IntegrationCallRecorder;

        $recorder->record('GET', 'https://api.calendly.com/scheduled_events/a1b2c3d4-1111-2222-3333-444455556666', statusCode: 200);

        expect(IntegrationCall::query()->first()->operation)
            ->toBe('GET /scheduled_events/{uuid}');
    });

    test('recording never throws into the caller', function () {
        // A broken recorder must not turn a successful sync into a failed one.
        config(['observability.integration_calls.hosts' => 'not-an-array']);

        expect(fn () => (new IntegrationCallRecorder)->record('GET', 'https://api.calendly.com/x'))
            ->not->toThrow(Throwable::class);
    });

    test('recording can be switched off entirely', function () {
        config(['observability.integration_calls.enabled' => false]);

        (new IntegrationCallRecorder)->record('GET', 'https://api.calendly.com/users/me', statusCode: 200);

        expect(IntegrationCall::query()->count())->toBe(0);
    });
});

describe('credentials', function () {
    test('a bearer token is never stored, but is still identifiable', function () {
        $recorder = new IntegrationCallRecorder;
        $token = 'eyJraWQiOiIxY2UxZTEzNjE3ZGNmNzY2YjNjZWJjY2Y4ZGM1YmFmYThhNjVlNjg0MDIzZjdjMzJiZTgz';

        $recorder->record(
            method: 'GET',
            url: 'https://api.calendly.com/users/me',
            headers: ['Authorization' => 'Bearer '.$token],
            statusCode: 200,
        );

        $stored = IntegrationCall::query()->first()->request_headers['Authorization'];

        expect($stored)->not->toContain($token)
            ->and($stored)->toStartWith('Bearer ')
            ->and($stored)->toContain('sha256:');
    });

    test('the same token fingerprints the same way and a different one does not', function () {
        // This is what makes "which token did Calendly fall through to?" answerable.
        $recorder = new IntegrationCallRecorder;

        expect($recorder->fingerprint('token-aaaaaaaaaaaa'))->toBe($recorder->fingerprint('token-aaaaaaaaaaaa'))
            ->and($recorder->fingerprint('token-aaaaaaaaaaaa'))->not->toBe($recorder->fingerprint('token-bbbbbbbbbbbb'));
    });

    test('a short secret is masked entirely', function () {
        // Showing the last four of a six-character secret gives away most of it.
        expect((new IntegrationCallRecorder)->fingerprint('abc123'))->not->toContain('c123');
    });

    test('secrets nested in a JSON body are redacted', function () {
        $recorder = new IntegrationCallRecorder;

        $recorder->record(
            method: 'POST',
            url: 'https://api.hubapi.com/oauth/v1/token',
            body: '{"grant_type":"refresh","auth":{"client_secret":"sk_live_abcdefghijklmnop"}}',
            statusCode: 200,
        );

        $body = IntegrationCall::query()->first()->request_body;

        expect($body)->not->toContain('sk_live_abcdefghijklmnop')
            ->and($body)->toContain('grant_type');
    });

    test('a token in the query string is redacted', function () {
        $recorder = new IntegrationCallRecorder;

        $recorder->record('GET', 'https://api.calendly.com/x?token=supersecretvalue123&page=2', statusCode: 200);

        $url = IntegrationCall::query()->first()->url;

        expect($url)->not->toContain('supersecretvalue123')
            ->and($url)->toContain('page=2');
    });

    test('a non-JSON body is kept verbatim rather than mangled', function () {
        $recorder = new IntegrationCallRecorder;

        $recorder->record(
            method: 'POST',
            url: 'https://api.calendly.com/x',
            body: 'plain=text&and=more',
            statusCode: 500,
            responseBody: '<html><body>502 Bad Gateway</body></html>',
        );

        $call = IntegrationCall::query()->first();

        expect($call->request_body)->toBe('plain=text&and=more')
            ->and($call->response_body)->toContain('502 Bad Gateway');
    });
});

describe('size', function () {
    test('an oversized body is truncated', function () {
        config(['observability.integration_calls.max_body_bytes' => 500]);

        (new IntegrationCallRecorder)->record(
            method: 'GET',
            url: 'https://api.calendly.com/x',
            statusCode: 200,
            responseBody: str_repeat('x', 5000),
        );

        $body = IntegrationCall::query()->first()->response_body;

        expect(strlen($body))->toBeLessThan(700)
            ->and($body)->toContain('truncated');
    });
});

describe('timing', function () {
    test('duration is measured across the send and receive events', function () {
        // Laravel wraps the request in a new object for each event, so timing cannot key on
        // object identity — this is the regression guard for that.
        $recorder = new IntegrationCallRecorder;

        $recorder->markSent('GET', 'https://api.calendly.com/users/me');
        $elapsed = $recorder->elapsed('GET', 'https://api.calendly.com/users/me');

        expect($elapsed)->toBeInt()->toBeGreaterThanOrEqual(0);
    });

    test('a response we never saw leave has no duration rather than a wrong one', function () {
        expect((new IntegrationCallRecorder)->elapsed('GET', 'https://api.calendly.com/x'))->toBeNull();
    });
});

describe('timeline icons', function () {
    test('every actor domain and integration resolves to a symbol', function () {
        // A missing mapping falls back silently, so the guard is that each known source maps to
        // something deliberate rather than to the default.
        $expected = [
            'Lead' => 'rl',
            'Scheduling' => 'calendly',
            'Tracking' => 'customerio',
            'Slack' => 'slack',
            'OutgoingWebhook' => 'webhook',
            'EmailNotification' => 'email',
            'hubspot' => 'hubspot',
            'stripe' => 'stripe',
        ];

        foreach ($expected as $key => $symbol) {
            expect(IntegrationIcons::symbolFor($key))->toBe($symbol);
        }
    });

    test('the sprite defines every symbol the map refers to', function () {
        // A <use> pointing at a symbol that was never defined renders as nothing at all.
        $sprite = IntegrationIcons::sprite();

        foreach (array_unique(array_values(IntegrationIcons::MAP)) as $symbol) {
            expect($sprite)->toContain('id="rl-ico-'.$symbol.'"');
        }
    });

    test('icons are 16px', function () {
        expect(IntegrationIcons::icon('hubspot'))->toContain('width="16"')->toContain('height="16"');
    });
});

describe('the outgoing webhook log tells the truth', function () {
    beforeEach(function () {
        config(['services.webhooks.lead_webhook_url' => 'https://api.example.com/webhooks/leads']);
        unset($GLOBALS['_wp_remote_post_response']);
    });

    afterEach(function () {
        unset($GLOBALS['_wp_remote_post_response']);
    });

    test('a rejected delivery is logged as failed, with the status and the body', function () {
        // The entry previously read "Dispatched lead.booking_completed outgoing webhook" whether
        // the endpoint accepted it or returned a 500, which is the complaint this work started
        // from: the log recorded the intention, never the result.
        $GLOBALS['_wp_remote_post_response'] = [
            'response' => ['code' => 500],
            'body' => '{"error":"workflow not found"}',
            'headers' => [],
        ];

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Webhook Test',
            'email' => 'webhook-test@example.com',
            'status' => 'captured',
        ]);

        $listener = new HandleLeadEventsForWebhook(
            new LeadActivityLogger
        );

        $listener->handleCreated(new LeadCreated($lead, []));

        $log = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'OutgoingWebhook')
            ->first();

        expect($log->outcome)->toBe('failed')
            ->and($log->payload['status_code'])->toBe(500)
            ->and($log->payload['response_body'])->toContain('workflow not found');
    });

    test('a successful delivery records the status code', function () {
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Webhook Ok',
            'email' => 'webhook-ok@example.com',
            'status' => 'captured',
        ]);

        $listener = new HandleLeadEventsForWebhook(
            new LeadActivityLogger
        );

        $listener->handleCreated(new LeadCreated($lead, []));

        $log = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'OutgoingWebhook')
            ->first();

        expect($log->outcome)->toBe('succeeded')
            ->and($log->payload['status_code'])->toBe(200)
            ->and($log->description)->toContain('HTTP 200');
    });
});

describe('naming the account a call authenticated as', function () {
    test('a labelled credential is recorded by name', function () {
        // The question these logs get asked is "whose token is rate limited?", and the answer
        // has to be a person to contact, not a slot number.
        $registry = new CredentialRegistry;
        $registry->register('calendly-token-one', 'admin@remoteleverage.com (Primary)');

        $recorder = new IntegrationCallRecorder($registry);

        $recorder->record(
            method: 'GET',
            url: 'https://api.calendly.com/scheduled_events',
            headers: ['Authorization' => 'Bearer calendly-token-one'],
            statusCode: 429,
        );

        $call = IntegrationCall::query()->first();

        expect($call->credential_label)->toBe('admin@remoteleverage.com (Primary)')
            ->and($call->outcome)->toBe('failed')
            ->and($call->status_code)->toBe(429);
    });

    test('two tokens in the pool are told apart by account', function () {
        $registry = new CredentialRegistry;
        $registry->register('token-a', 'admin@remoteleverage.com');
        $registry->register('token-b', 'sales@remoteleverage.com');

        $recorder = new IntegrationCallRecorder($registry);

        $recorder->record('GET', 'https://api.calendly.com/x', ['Authorization' => 'Bearer token-a'], statusCode: 429);
        $recorder->record('GET', 'https://api.calendly.com/x', ['Authorization' => 'Bearer token-b'], statusCode: 200);

        $rateLimited = IntegrationCall::query()->where('status_code', 429)->first();

        expect($rateLimited->credential_label)->toBe('admin@remoteleverage.com');
    });

    test('an unknown credential leaves the name empty rather than guessing', function () {
        $recorder = new IntegrationCallRecorder(new CredentialRegistry);

        $recorder->record('GET', 'https://api.calendly.com/x', ['Authorization' => 'Bearer nobody-claims-this'], statusCode: 200);

        expect(IntegrationCall::query()->first()->credential_label)->toBeNull();
    });

    test('the name is resolved without ever storing the secret', function () {
        $registry = new CredentialRegistry;
        $registry->register('supersecrettokenvalue', 'admin@remoteleverage.com');

        $recorder = new IntegrationCallRecorder($registry);
        $recorder->record('GET', 'https://api.calendly.com/x', ['Authorization' => 'Bearer supersecrettokenvalue'], statusCode: 200);

        $call = IntegrationCall::query()->first();

        expect($call->credential_label)->toBe('admin@remoteleverage.com')
            ->and(json_encode($call->request_headers))->not->toContain('supersecrettokenvalue');
    });

    test('a resolver runs once and can be flushed when the pool is edited', function () {
        // A map built once at boot would keep naming whoever held that slot before an edit.
        $runs = 0;
        $registry = new CredentialRegistry;

        $registry->registerResolver(function () use (&$runs) {
            $runs++;

            return ['tok' => 'first@example.com'];
        });

        expect($registry->labelFor('tok'))->toBe('first@example.com')
            ->and($registry->labelFor('tok'))->toBe('first@example.com')
            ->and($runs)->toBe(1);

        $registry->flush();

        expect($registry->labelFor('tok'))->toBe('first@example.com')
            ->and($runs)->toBe(2);
    });

    test('a failing resolver does not lose the log entry', function () {
        $registry = new CredentialRegistry;
        $registry->registerResolver(fn () => throw new RuntimeException('pool unreadable'));

        $recorder = new IntegrationCallRecorder($registry);
        $recorder->record('GET', 'https://api.calendly.com/x', ['Authorization' => 'Bearer t'], statusCode: 200);

        expect(IntegrationCall::query()->count())->toBe(1)
            ->and(IntegrationCall::query()->first()->credential_label)->toBeNull();
    });
});
