<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Gateways\MetaConversionsApiClient;
use App\Domains\Tracking\Listeners\SendBookingToMetaConversionsApi;
use App\Domains\Tracking\Listeners\SendLeadToMetaConversionsApi;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
 * Meta's server-side `Lead`.
 *
 * Guards the integration that was missing between the 2026-09-17 cutover and 2026-09-18, when
 * Ads Manager reported 0 conversions against ~12 real ones. The old suite was fully green
 * throughout, because it only ever asserted the PageView that did exist — so these tests assert
 * the thing that was actually absent: that a conversion event leaves the building, and that the
 * lead timeline says so either way.
 */

beforeEach(function () {
    // The Http facade caches its Factory for the life of the process, and fake() binds the
    // factory into the container too — so without a hard swap every test inherits the previous
    // test's stub closures and the first catch-all registered wins for the whole file.
    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);

    LeadActivityLog::truncate();
    Lead::truncate();

    config(['pixels.meta.pixel_ids' => ['1430907207548734', '1482937899395718']]);
    config(['services.meta_capi.access_token' => 'EAAT-test-token']);
    config(['services.meta_capi.api_version' => 'v11.0']);
    config(['services.meta_capi.enabled' => true]);
    config(['services.meta_capi.test_event_code' => '']);
});

function metaLead(array $overrides = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Sarah Connor',
        'first_name' => ' Sarah ',
        'last_name' => "O'Connor",
        'email' => '  Sarah.Connor@Cyberdyne.IO. ',
        'phone' => '(305) 555-0199',
        'phone_country' => '+1',
        'ip_address' => '203.0.113.9',
        'fbclid' => 'IwAR-click-123',
        'landing_url' => 'https://remoteleverage.com/hire-va-4/?fbclid=IwAR-click-123',
        'source_type' => 'paid_social',
        'status' => 'captured',
        'device_id' => '7f3c2a10-5b1e-4c9d-9a2f-0e8b6d4c1a77',
        'country' => 'US',
        'attribution' => [
            'user_agent' => 'Mozilla/5.0 (Macintosh)',
            'handl' => ['_fbp' => 'fb.1.1700000000000.987654321'],
        ],
    ], $overrides));
}

describe('MetaConversionsApiClient', function () {
    test('sends the Lead to every configured pixel', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $result = (new MetaConversionsApiClient)->sendLead(metaLead());

        expect($result['sent'])->toBe(['1430907207548734', '1482937899395718'])
            ->and($result['failed'])->toBe([])
            ->and($result['skipped'])->toBeFalse();

        Http::assertSentCount(2);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v11.0/1430907207548734/events');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v11.0/1482937899395718/events');
    });

    test('hashes PII and leaves the match identifiers raw', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        (new MetaConversionsApiClient)->sendLead(metaLead());

        Http::assertSent(function (Request $r) {
            $user = $r->data()['data'][0]['user_data'];

            // Normalisation is ported from HandL: email lowercased and stripped of a trailing
            // dot, names stripped to [a-z], phone stripped of punctuation and given its country
            // code. Meta matches on the hash, so a different normalisation is a silent non-match.
            return $user['em'] === hash('sha256', 'sarah.connor@cyberdyne.io')
                && $user['fn'] === hash('sha256', 'sarah')
                && $user['ln'] === hash('sha256', 'oconnor')
                && $user['ph'] === hash('sha256', '13055550199')
                // ISO-2 country, lowercased — Meta's format.
                && $user['country'] === hash('sha256', 'us')
                // The rl_vid visitor cookie, so every event for this person shares one id.
                && $user['external_id'] === hash('sha256', '7f3c2a10-5b1e-4c9d-9a2f-0e8b6d4c1a77')
                // Raw, never hashed — hashing these destroys the match with no error anywhere.
                && $user['fbp'] === 'fb.1.1700000000000.987654321'
                && $user['client_ip_address'] === '203.0.113.9'
                && $user['client_user_agent'] === 'Mozilla/5.0 (Macintosh)';
        });
    });

    test('rebuilds fbc from a bare fbclid when the _fbc cookie never arrived', function () {
        Http::fake(fn () => Http::response([], 200));

        $lead = metaLead(['fbc' => null]);
        (new MetaConversionsApiClient)->sendLead($lead);

        Http::assertSent(function (Request $r) use ($lead) {
            $expected = sprintf('fb.1.%d.%s', $lead->created_at->getTimestamp() * 1000, 'IwAR-click-123');

            return $r->data()['data'][0]['user_data']['fbc'] === $expected;
        });
    });

    test('prefers a real _fbc over a derived one', function () {
        Http::fake(fn () => Http::response([], 200));

        (new MetaConversionsApiClient)->sendLead(metaLead(['fbc' => 'fb.1.1699999999999.realcookie']));

        Http::assertSent(fn (Request $r) => $r->data()['data'][0]['user_data']['fbc'] === 'fb.1.1699999999999.realcookie');
    });

    test('carries action_source, event_source_url and a stable event_id', function () {
        Http::fake(fn () => Http::response([], 200));

        $lead = metaLead();
        $result = (new MetaConversionsApiClient)->sendLead($lead);

        expect($result['event_id'])->toBe('lead-'.$lead->uuid);

        Http::assertSent(function (Request $r) use ($lead) {
            $event = $r->data()['data'][0];

            return $event['event_name'] === 'Lead'
                && $event['action_source'] === 'website'
                && $event['event_id'] === 'lead-'.$lead->uuid
                && $event['event_source_url'] === 'https://remoteleverage.com/hire-va-4/?fbclid=IwAR-click-123'
                && $event['event_time'] === $lead->created_at->getTimestamp();
        });
    });

    test('sends the access token in the JSON body, never the query string', function () {
        Http::fake(fn () => Http::response([], 200));

        (new MetaConversionsApiClient)->sendLead(metaLead());

        // IntegrationCallRecorder writes every one of these calls to rl_integration_calls and
        // redacts `access_token` by JSON key. In the query string it would be logged in plaintext,
        // once per lead, into a table the admin renders.
        Http::assertSent(fn (Request $r) => $r->data()['access_token'] === 'EAAT-test-token'
            && ! str_contains($r->url(), 'access_token'));
    });

    test('sends nothing when the token is missing', function () {
        config(['services.meta_capi.access_token' => '']);
        Http::fake(fn () => Http::response([], 200));

        $result = (new MetaConversionsApiClient)->sendLead(metaLead());

        expect($result['skipped'])->toBeTrue()->and($result['sent'])->toBe([]);
        Http::assertNothingSent();
    });

    test('reports a rejected pixel as failed without throwing', function () {
        Http::fake(fn (Request $r) => str_contains($r->url(), '1430907207548734')
            ? Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)
            : Http::response([], 200));

        $result = (new MetaConversionsApiClient)->sendLead(metaLead());

        expect($result['failed'])->toBe(['1430907207548734'])
            ->and($result['sent'])->toBe(['1482937899395718'])
            ->and($result['errors'][0])->toContain('Invalid OAuth access token');
    });

    test('ignores malformed pixel ids', function () {
        config(['pixels.meta.pixel_ids' => ['1430907207548734', 'not-a-pixel', '123']]);
        Http::fake(fn () => Http::response([], 200));

        (new MetaConversionsApiClient)->sendLead(metaLead());

        Http::assertSentCount(1);
    });
});

describe('SendLeadToMetaConversionsApi timeline entry', function () {
    test('records a succeeded Meta entry on the lead timeline', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $lead = metaLead();
        (new SendLeadToMetaConversionsApi(new MetaConversionsApiClient, new LeadActivityLogger))
            ->handle(new LeadCreated($lead));

        $log = LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->first();

        expect($log)->not->toBeNull()
            ->and($log->event_type)->toBe('LeadCreated')
            ->and($log->outcome)->toBe('succeeded')
            ->and($log->stage)->toBe('consumption')
            ->and($log->description)->toContain('1430907207548734')
            ->and($log->payload['pixels_sent'])->toBe(['1430907207548734', '1482937899395718'])
            ->and($log->payload['event_id'])->toBe('lead-'.$lead->uuid)
            // The match-key breakdown is the first thing to read when Meta's match rate drops.
            ->and($log->payload['match_keys']['fbc'])->toBeTrue()
            ->and($log->payload['match_keys']['email'])->toBeTrue();
    });

    test('records a skipped entry that names the consequence when unconfigured', function () {
        config(['services.meta_capi.access_token' => '']);
        Http::fake(fn () => Http::response([], 200));

        $lead = metaLead();
        (new SendLeadToMetaConversionsApi(new MetaConversionsApiClient, new LeadActivityLogger))
            ->handle(new LeadCreated($lead));

        $log = LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->first();

        expect($log->outcome)->toBe('skipped')
            ->and($log->description)->toContain('Facebook will not count this conversion');
    });

    test('records a failed entry when Meta rejects the event', function () {
        Http::fake(fn () => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400));

        $lead = metaLead();
        (new SendLeadToMetaConversionsApi(new MetaConversionsApiClient, new LeadActivityLogger))
            ->handle(new LeadCreated($lead));

        $log = LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->first();

        expect($log->outcome)->toBe('failed')
            ->and($log->description)->toContain('rejected')
            ->and($log->payload['pixels_failed'])->toBe(['1430907207548734', '1482937899395718']);
    });

    test('a Meta outage never bubbles out of the listener', function () {
        Http::fake(fn () => throw new RuntimeException('connection reset'));

        $lead = metaLead();

        expect(fn () => (new SendLeadToMetaConversionsApi(new MetaConversionsApiClient, new LeadActivityLogger))
            ->handle(new LeadCreated($lead)))->not->toThrow(Throwable::class);

        $log = LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->first();

        expect($log->outcome)->toBe('failed');
    });
});

describe('SendBookingToMetaConversionsApi', function () {
    /*
     * The booking conversion. Calendly's own Meta Pixel integration cannot fire it for these
     * bookings — the wizard books through Calendly's API and no Calendly page ever loads — so
     * this is the only way `invitee_meeting_scheduled` reaches the pixel.
     */
    function sendBooking(Lead $lead): void
    {
        (new SendBookingToMetaConversionsApi(new MetaConversionsApiClient, new LeadActivityLogger))
            ->handle(new LeadBookingCompleted(lead: $lead, meetingId: 'https://api.calendly.com/scheduled_events/abc/invitees/def', provider: 'calendly'));
    }

    test('sends invitee_meeting_scheduled to every pixel, timed at the booking', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $lead = metaLead(['created_at' => now()->subDays(2)]);
        sendBooking($lead);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $r) use ($lead) {
            $event = $r->data()['data'][0];

            return $event['event_name'] === 'invitee_meeting_scheduled'
                && $event['event_id'] === 'booking-'.$lead->uuid
                // The booking's own time, not the lead's capture two days earlier.
                && abs($event['event_time'] - time()) < 60
                && isset($event['user_data']['em'], $event['user_data']['fbc']);
        });
    });

    test('records a succeeded Meta entry under LeadBookingCompleted', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $lead = metaLead();
        sendBooking($lead);

        $log = LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->first();

        expect($log->event_type)->toBe('LeadBookingCompleted')
            ->and($log->outcome)->toBe('succeeded')
            ->and($log->payload['event_name'])->toBe('invitee_meeting_scheduled')
            ->and($log->payload['provider'])->toBe('calendly')
            ->and($log->payload['pixels_sent'])->toBe(['1430907207548734', '1482937899395718']);
    });

    test('the webhook reporting the same booking again does not send it twice', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $lead = metaLead();
        sendBooking($lead); // the wizard's API booking
        sendBooking($lead); // Calendly's invitee.created for the same meeting

        Http::assertSentCount(2); // one per pixel, once
    });

    test('a failed send leaves the next report free to retry', function () {
        Http::fake(fn () => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400));

        $lead = metaLead();
        sendBooking($lead);

        Facade::clearResolvedInstance(HttpFactory::class);
        Http::swap(new HttpFactory);
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        sendBooking($lead);

        Http::assertSentCount(2);
        expect(LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->pluck('outcome')->all())
            ->toBe(['failed', 'succeeded']);
    });

    test('the booking carries the same external_id as the lead, and records its match keys', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $lead = metaLead();
        sendBooking($lead);

        Http::assertSent(fn (Request $r) => $r->data()['data'][0]['user_data']['external_id']
            === hash('sha256', '7f3c2a10-5b1e-4c9d-9a2f-0e8b6d4c1a77'));

        $log = LeadActivityLog::query()->where('lead_id', $lead->id)->where('actor_domain', 'Meta')->first();

        expect($log->payload['match_keys'])->toMatchArray(['external_id' => true, 'country' => true, 'fbp' => true]);
    });

    test('a lead from before the visitor cookie falls back to its uuid as external_id', function () {
        Http::fake(fn () => Http::response(['events_received' => 1], 200));

        $lead = metaLead(['device_id' => null]);
        (new MetaConversionsApiClient)->sendLead($lead);

        Http::assertSent(fn (Request $r) => $r->data()['data'][0]['user_data']['external_id']
            === hash('sha256', strtolower($lead->uuid)));
    });

    test('the booking never shares an event id with the lead', function () {
        $lead = metaLead();
        $client = new MetaConversionsApiClient;

        expect($client->bookingEventId($lead))->not->toBe($client->eventId($lead));
    });
});
