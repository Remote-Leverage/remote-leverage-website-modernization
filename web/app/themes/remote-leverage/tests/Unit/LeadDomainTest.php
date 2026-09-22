<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\ProcessAbandonedLeadsAction;
use App\Domains\Lead\Actions\PurgeOldLeadsAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadAbandoned;
use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Events\LeadFormSubmitted;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Listeners\HandleLeadEventsForWebhook;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use App\Infrastructure\WordPress\Admin\LeadsAdminDashboard;
use Carbon\Carbon;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

describe('Lead Domain', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();
    });

    test('PhoneValidationService validates and formats US and international numbers to E.164', function () {
        $service = new PhoneValidationService;

        // Valid US number
        $usResult = $service->validateAndFormat('(305) 555-0199', 'US');
        expect($usResult['isValid'])->toBeTrue()
            ->and($usResult['e164'])->toBe('+13055550199')
            ->and($usResult['countryCode'])->toBe('US');

        // Valid UK number
        $ukResult = $service->validateAndFormat('+44 20 7946 0991', 'GB');
        expect($ukResult['isValid'])->toBeTrue()
            ->and($ukResult['e164'])->toBe('+442079460991')
            ->and($ukResult['countryCode'])->toBe('GB');

        // Invalid number
        $invalidResult = $service->validateAndFormat('12345', 'US');
        expect($invalidResult['isValid'])->toBeFalse()
            ->and($invalidResult['error'])->not->toBeNull();
    });

    test('LeadCaptureData instantiates correctly and serializes to array', function () {
        $dto = LeadCaptureData::fromArray([
            'name' => 'Elena Rostova',
            'email' => 'elena@novacapital.com',
            'phone' => '+1 (305) 555-0188',
            'company' => 'Nova Capital',
            'role_needed' => 'Executive Assistant',
            'weekly_hours' => '40',
            'start_date' => 'Immediately',
            'referral_code' => 'apex-partner',
        ]);

        expect($dto->name)->toBe('Elena Rostova')
            ->and($dto->email)->toBe('elena@novacapital.com')
            ->and($dto->company)->toBe('Nova Capital')
            ->and($dto->referralCode)->toBe('apex-partner');

        $array = $dto->toArray();
        expect($array['email'])->toBe('elena@novacapital.com')
            ->and($array['role_needed'])->toBe('Executive Assistant');
    });

    test('CaptureLeadAction validates, stamps attribution, persists lead, logs dispatch, and fires LeadCreated', function () {
        $submittedEvents = [];
        $createdEvents = [];
        Event::listen(LeadFormSubmitted::class, function ($e) use (&$submittedEvents) {
            $submittedEvents[] = $e;
        });
        Event::listen(LeadCreated::class, function ($e) use (&$createdEvents) {
            $createdEvents[] = $e;
        });

        $attributionEngine = new AttributionEngine;
        $phoneValidator = new PhoneValidationService;
        $activityLogger = new LeadActivityLogger;

        $action = new CaptureLeadAction($attributionEngine, $phoneValidator, $activityLogger);

        $dto = LeadCaptureData::fromArray([
            'name' => 'Marcus Aurelius',
            'email' => 'marcus@rome.org',
            'phone' => '+1 305 555 0123',
            'company' => 'Imperial Operations',
            'role_needed' => 'Operations Manager',
            'weekly_hours' => '40',
            'start_date' => 'Immediately',
            'referral_code' => 'partner-capital',
            'preferred_slot' => '2026-09-15T15:00:00Z',
        ]);

        $lead = $action->execute($dto);

        expect($lead->exists)->toBeTrue()
            ->and($lead->email)->toBe('marcus@rome.org')
            ->and($lead->first_name)->toBe('Marcus')
            ->and($lead->last_name)->toBe('Aurelius')
            ->and($lead->source_type)->toBe('partnership')
            ->and($lead->source_id)->toBe('partner-capital')
            ->and($lead->status)->toBe('booking_pending');

        // Check dual logging Stage 1 (dispatch)
        $dispatchLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('stage', 'dispatch')
            ->where('event_type', 'LeadCreated')
            ->first();

        expect($dispatchLog)->not->toBeNull()
            ->and($dispatchLog->actor_domain)->toBe('Lead')
            ->and($dispatchLog->outcome)->toBe('succeeded');

        expect(count($submittedEvents))->toBe(1);
        expect(count($createdEvents))->toBe(1);
        expect($createdEvents[0]->lead->id)->toBe($lead->id);
        expect($createdEvents[0]->context['preferred_slot'])->toBe('2026-09-15T15:00:00Z');
    });

    test('PurgeOldLeadsAction enforces 30-day minimum retention floor', function () {
        // Create an old lead (40 days old)
        $oldLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old Lead',
            'email' => 'old@archive.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $oldLead->timestamps = false;
        $oldLead->created_at = Carbon::now()->subDays(40);
        $oldLead->updated_at = Carbon::now()->subDays(40);
        $oldLead->save();

        // Create a recent lead (10 days old)
        $recentLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Recent Lead',
            'email' => 'recent@current.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $recentLead->timestamps = false;
        $recentLead->created_at = Carbon::now()->subDays(10);
        $recentLead->updated_at = Carbon::now()->subDays(10);
        $recentLead->save();

        $purgeAction = new PurgeOldLeadsAction;

        // Execute purge with 30-day default window
        $purgedCount = $purgeAction->execute();

        expect($purgedCount)->toBe(1);
        expect(Lead::query()->find($oldLead->id))->toBeNull();
        expect(Lead::query()->find($recentLead->id))->not->toBeNull();
    });

    test('ProcessAbandonedLeadsAction marks stale unbooked leads abandoned, dispatches LeadAbandoned, and logs dispatch', function () {
        $abandonedEvents = [];
        Event::listen(LeadAbandoned::class, function ($e) use (&$abandonedEvents) {
            $abandonedEvents[] = $e;
        });

        // Stale lead: captured 3 hours ago, never booked
        $staleLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stale Lead',
            'email' => 'stale@lead.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $staleLead->timestamps = false;
        $staleLead->created_at = Carbon::now()->subHours(3);
        $staleLead->updated_at = Carbon::now()->subHours(3);
        $staleLead->save();

        // Fresh lead: captured 30 minutes ago, still within the timeout window
        $freshLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Fresh Lead',
            'email' => 'fresh@lead.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);
        $freshLead->timestamps = false;
        $freshLead->created_at = Carbon::now()->subMinutes(30);
        $freshLead->updated_at = Carbon::now()->subMinutes(30);
        $freshLead->save();

        // Stale but already booked: must be excluded regardless of age
        $bookedLead = new Lead([
            'uuid' => (string) Str::uuid(),
            'name' => 'Booked Lead',
            'email' => 'booked@lead.com',
            'source_type' => 'organic',
            'status' => 'booked',
        ]);
        $bookedLead->timestamps = false;
        $bookedLead->created_at = Carbon::now()->subHours(5);
        $bookedLead->updated_at = Carbon::now()->subHours(5);
        $bookedLead->save();

        $action = new ProcessAbandonedLeadsAction(new LeadActivityLogger);
        $abandonedCount = $action->execute(2);

        expect($abandonedCount)->toBe(1);
        expect($staleLead->fresh()->status)->toBe('abandoned');
        expect($freshLead->fresh()->status)->toBe('captured');
        expect($bookedLead->fresh()->status)->toBe('booked');

        expect(count($abandonedEvents))->toBe(1);
        expect($abandonedEvents[0]->lead->id)->toBe($staleLead->id);

        $dispatchLog = LeadActivityLog::query()
            ->where('lead_id', $staleLead->id)
            ->where('stage', 'dispatch')
            ->where('event_type', 'LeadAbandoned')
            ->first();

        expect($dispatchLog)->not->toBeNull()
            ->and($dispatchLog->actor_domain)->toBe('Lead')
            ->and($dispatchLog->outcome)->toBe('succeeded');
    });

    test('CaptureLeadAction updates existing lead on final submission without duplicating rows', function () {
        $attributionEngine = new AttributionEngine;
        $phoneValidator = new PhoneValidationService;
        $activityLogger = new LeadActivityLogger;
        $action = new CaptureLeadAction($attributionEngine, $phoneValidator, $activityLogger);

        // 1. Partial Step 1 submission
        $step1Dto = LeadCaptureData::fromArray([
            'name' => 'Sarah Jenkins',
            'first_name' => 'Sarah',
            'last_name' => 'Jenkins',
            'email' => 'sarah@growthco.io',
            'phone' => '+1 (305) 555-0199',
            'monthly_revenue' => '$10k to $50k Per Month',
            'extra_data' => [
                'source_form' => 'MultistepBookingWizard',
                'submission_type' => 'Partial',
            ],
        ]);

        $partialLead = $action->execute($step1Dto);
        expect(Lead::query()->count())->toBe(1)
            ->and($partialLead->status)->toBe('captured');

        // 2. Final Step 4 booking submission using the existing lead ID
        $step4Dto = LeadCaptureData::fromArray([
            'name' => 'Sarah Jenkins',
            'first_name' => 'Sarah',
            'last_name' => 'Jenkins',
            'email' => 'sarah@growthco.io',
            'phone' => '+1 (305) 555-0199',
            'monthly_revenue' => '$10k to $50k Per Month',
            'preferred_slot' => '2026-09-15T14:00:00Z',
            'notes' => 'Looking to hire an executive assistant.',
            'extra_data' => [
                'source_form' => 'MultistepBookingWizard',
                'lead_id' => $partialLead->id,
            ],
        ]);

        $finalLead = $action->execute($step4Dto);

        // Verify no duplicate row was created; existing row updated
        expect(Lead::query()->count())->toBe(1)
            ->and($finalLead->id)->toBe($partialLead->id)
            ->and($finalLead->status)->toBe('booking_pending')
            ->and($finalLead->notes)->toBe('Looking to hire an executive assistant.');
    });

    test('HandleLeadEventsForSlack dispatches and logs consumption for partial and final events', function () {
        // SlackTransport posts through the Http facade, which keeps its Factory for the process.
        // Without a fresh swap this test inherits whatever an earlier file faked — a 503, a
        // thrown connection — and logs outcome=failed even though the listener itself succeeded.
        Facade::clearResolvedInstance(HttpFactory::class);
        Http::swap(new HttpFactory);
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        config([
            'services.slack.webhook_url' => 'https://hooks.slack.com/services/test/123',
            'services.slack.bot_token' => '',
            // The booking alert is on by default; this test covers both halves.
            'services.slack.notify_on_booking' => true,
        ]);
        $activityLogger = new LeadActivityLogger;
        $listener = new HandleLeadEventsForSlack($activityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Doe',
            'email' => 'john@startup.com',
            'monthly_revenue' => '$50k-$100k Per Month',
            'source_type' => 'ad',
            'source_id' => 'google',
            'status' => 'captured',
        ]);

        // Partial capture event
        $listener->handleCreated(new LeadCreated($lead, ['preferred_slot' => null]));

        $partialLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Slack')
            ->where('event_type', 'LeadCreated')
            ->first();

        expect($partialLog)->not->toBeNull()
            ->and($partialLog->stage)->toBe('consumption');

        // Final booking event
        $lead->status = 'booked';
        $lead->save();

        $bookingCompleted = new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'meet-1234',
            provider: 'calendly',
            meetUrl: 'https://meet.google.com/abc-def-ghi',
            startTime: '2026-09-15T15:00:00Z'
        );

        $listener->handleBookingCompleted($bookingCompleted);

        $finalLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Slack')
            ->where('event_type', 'LeadBookingCompleted')
            ->first();

        expect($finalLog)->not->toBeNull()
            ->and($finalLog->outcome)->toBe('succeeded');
    });

    test('outgoing webhook posts the whole lead row, so Partial and Final are distinguishable', function () {
        config(['services.webhooks.lead_webhook_url' => 'https://n8n.test/webhook/gravityforms-leads']);
        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);

        $listener = new HandleLeadEventsForWebhook(new LeadActivityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Priya Raman',
            'email' => 'priya@northwind.io',
            'status' => 'captured',
            'submission_type' => 'Partial',
            // Columns the hand-picked payload never carried, which is the whole point of the
            // change: they were on the lead and never reached n8n.
            'device_id' => 'dev-7781',
            'ip_address' => '203.0.113.9',
            'li_fat_id' => 'li-4412',
            'attribution' => ['first_utm_source' => 'linkedin'],
        ]);

        /*
         * Refreshed first, the way production hands this listener its lead.
         *
         * `create()` leaves the model holding only the attributes that were written, so an
         * un-refreshed one serialises without its untouched nullable columns. Every real caller
         * reloads before the event — `CaptureLeadAction` refreshes after `IdentityResolver`,
         * `CalendlyWebhookController` reads the row back — so the payload carries the full
         * column set. Asserting against a half-hydrated model would test the wrong shape.
         */
        $lead->refresh();

        $listener->handleCreated(new LeadCreated($lead, ['preferred_slot' => null]));

        // The completed submission re-runs CaptureLeadAction against the same row, which flips
        // submission_type to Final and dispatches LeadCreated a second time. Both posts go out
        // under the same event name; only the column tells them apart.
        $lead->update(['submission_type' => 'Final', 'status' => 'booking_pending']);
        $listener->handleCreated(new LeadCreated($lead, ['preferred_slot' => '2026-10-01T15:00:00Z']));

        expect($GLOBALS['_wp_remote_post_calls'])->toHaveCount(2)
            ->and($GLOBALS['_wp_remote_post_calls'][0]['url'])->toBe('https://n8n.test/webhook/gravityforms-leads');

        [$partial, $final] = array_map(
            static fn (array $call): array => json_decode((string) $call['args']['body'], true, 512, JSON_THROW_ON_ERROR),
            $GLOBALS['_wp_remote_post_calls']
        );

        expect($partial['event'])->toBe('lead.partial_captured')
            ->and($final['event'])->toBe('lead.partial_captured')
            ->and($partial['lead']['submission_type'])->toBe('Partial')
            ->and($final['lead']['submission_type'])->toBe('Final')
            ->and($final['context']['preferred_slot'])->toBe('2026-10-01T15:00:00Z');

        foreach (['device_id', 'ip_address', 'li_fat_id', 'attribution', 'hubspot_lifecycle_stage', 'is_blocked'] as $column) {
            expect($partial['lead'])->toHaveKey($column);
        }

        expect($partial['lead']['device_id'])->toBe('dev-7781')
            ->and($partial['lead']['ip_address'])->toBe('203.0.113.9')
            ->and($partial['lead']['attribution'])->toBe(['first_utm_source' => 'linkedin']);
    });

    test('the outgoing lead webhook defaults to the n8n endpoint with nothing configured', function () {
        $services = require __DIR__.'/../../config/services.php';

        expect($services['webhooks']['lead_webhook_url'])
            ->toBe('https://n8n.srv1338052.hstgr.cloud/webhook/gravityforms-leads');
    });

    test('the lead-form webhook sends the email and the Eastern capture time, on the partial only', function () {
        config(['services.webhooks.lead_form_url' => 'https://n8n.test/webhook/lead-form']);
        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);

        $listener = new HandleLeadEventsForWebhook(new LeadActivityLogger);

        // 18:33:07 UTC on a July day is 14:33:07 in New York — daylight time, UTC-4. Picked
        // deliberately: a fixed -05:00 offset would render 13:33:07 here and the assertion
        // below is what says which reading of "EST" shipped.
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tomas Lindqvist',
            'email' => 'tomas@arvoretail.se',
            'status' => 'captured',
            'submission_type' => 'Partial',
        ]);

        // Forced rather than passed to create(): `created_at` is not fillable, so handing it
        // to create() is silently dropped and the row gets "now" — which would have this test
        // pass against a hard-coded offset on a summer afternoon and fail in December.
        $lead->forceFill(['created_at' => Carbon::parse('2026-07-14 18:33:07', 'UTC')])->saveQuietly();
        $lead->refresh();

        $listener->handleLeadFormCaptured(new LeadCreated($lead, []));

        expect($GLOBALS['_wp_remote_post_calls'])->toHaveCount(1)
            ->and($GLOBALS['_wp_remote_post_calls'][0]['url'])->toBe('https://n8n.test/webhook/lead-form');

        $body = json_decode((string) $GLOBALS['_wp_remote_post_calls'][0]['args']['body'], true, 512, JSON_THROW_ON_ERROR);

        expect($body['event'])->toBe('lead.form_captured')
            ->and($body['email'])->toBe('tomas@arvoretail.se')
            ->and($body['captured_at_est'])->toBe('2026-07-14 14:33:07')
            ->and($body['captured_at_est_iso'])->toBe('2026-07-14T14:33:07-04:00')
            ->and($body['captured_at_est_abbreviation'])->toBe('EDT')
            ->and($body['submission_type'])->toBe('Partial');

        // The completed submission raises LeadCreated again against the same row. This flow is
        // the capture notification, so the second one is not sent — one POST per lead.
        $lead->update(['submission_type' => 'Final', 'status' => 'booking_pending']);
        $listener->handleLeadFormCaptured(new LeadCreated($lead->refresh(), []));

        expect($GLOBALS['_wp_remote_post_calls'])->toHaveCount(1);
    });

    test('a winter capture reads EST, so the label is not hard-coded', function () {
        config(['services.webhooks.lead_form_url' => 'https://n8n.test/webhook/lead-form']);
        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Winter Capture',
            'email' => 'winter@northwind.io',
            'status' => 'captured',
            'submission_type' => 'Partial',
        ]);

        $lead->forceFill(['created_at' => Carbon::parse('2026-01-14 18:33:07', 'UTC')])->saveQuietly();

        (new HandleLeadEventsForWebhook(new LeadActivityLogger))
            ->handleLeadFormCaptured(new LeadCreated($lead->refresh(), []));

        $body = json_decode((string) $GLOBALS['_wp_remote_post_calls'][0]['args']['body'], true, 512, JSON_THROW_ON_ERROR);

        expect($body['captured_at_est'])->toBe('2026-01-14 13:33:07')
            ->and($body['captured_at_est_abbreviation'])->toBe('EST');
    });

    test('the hubspot webhook sends the email and a link to the contact record', function () {
        config([
            'services.webhooks.hubspot_lead_url' => 'https://n8n.test/webhook/hubspot-lead-creation',
            'services.hubspot.portal_id' => '243484989',
        ]);
        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);

        $listener = new HandleLeadEventsForWebhook(new LeadActivityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dara Okonkwo',
            'email' => 'dara@kestrellabs.com',
            'status' => 'captured',
            'submission_type' => 'Partial',
            'hubspot_contact_id' => '80125512',
        ]);

        $lead->forceFill(['created_at' => Carbon::parse('2026-07-14 18:33:07', 'UTC')])->saveQuietly();
        $lead->refresh();

        $listener->handleHubSpotSynced($lead, 'created');

        expect($GLOBALS['_wp_remote_post_calls'])->toHaveCount(1)
            ->and($GLOBALS['_wp_remote_post_calls'][0]['url'])->toBe('https://n8n.test/webhook/hubspot-lead-creation');

        $body = json_decode((string) $GLOBALS['_wp_remote_post_calls'][0]['args']['body'], true, 512, JSON_THROW_ON_ERROR);

        expect($body['event'])->toBe('hubspot.contact_synced')
            ->and($body['action'])->toBe('created')
            ->and($body['email'])->toBe('dara@kestrellabs.com')
            ->and($body['hubspot_contact_url'])->toBe('https://app.hubspot.com/contacts/243484989/record/0-1/80125512');

        /*
         * Two stamps, and they are not the same instant. `captured_at_est` is the lead's own
         * created_at in the same shape `lead-form` sends, so one n8n sub-workflow reads either
         * payload; `synced_at_est` is when the contact actually reached the portal, which is
         * now — this runs in the same deferred job as the sync that just returned.
         */
        expect($body['captured_at_est'])->toBe('2026-07-14 14:33:07')
            ->and($body['captured_at_est_iso'])->toBe('2026-07-14T14:33:07-04:00')
            ->and($body['captured_at_est_abbreviation'])->toBe('EDT')
            ->and($body['synced_at_est'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')
            ->and($body['synced_at_est_abbreviation'])->toBeIn(['EST', 'EDT'])
            ->and($body['synced_at_est'])->not->toBe($body['captured_at_est']);

        // An update is the returning-lead case and sends too, tagged so the flow can branch.
        $listener->handleHubSpotSynced($lead, 'updated');

        $second = json_decode((string) $GLOBALS['_wp_remote_post_calls'][1]['args']['body'], true, 512, JSON_THROW_ON_ERROR);

        expect($second['action'])->toBe('updated');
    });

    test('the hubspot webhook stays silent without a link to send', function () {
        config([
            'services.webhooks.hubspot_lead_url' => 'https://n8n.test/webhook/hubspot-lead-creation',
            'services.hubspot.portal_id' => '243484989',
        ]);
        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);

        $listener = new HandleLeadEventsForWebhook(new LeadActivityLogger);

        // Never reached the CRM: the payload is the link, and a null one would have the flow
        // announce a contact nobody can open.
        $unsynced = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unsynced Lead',
            'email' => 'unsynced@northwind.io',
            'status' => 'captured',
            'submission_type' => 'Partial',
        ]);

        $listener->handleHubSpotSynced($unsynced->refresh(), 'created');

        // Synced, but on the completed submission rather than the capture.
        $final = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Final Lead',
            'email' => 'final@northwind.io',
            'status' => 'booking_pending',
            'submission_type' => 'Final',
            'hubspot_contact_id' => '80125513',
        ]);

        $listener->handleHubSpotSynced($final->refresh(), 'updated');

        expect($GLOBALS['_wp_remote_post_calls'])->toBeEmpty();
    });

    test('a blocked lead reaches neither n8n flow', function () {
        config([
            'services.webhooks.lead_form_url' => 'https://n8n.test/webhook/lead-form',
            'services.webhooks.hubspot_lead_url' => 'https://n8n.test/webhook/hubspot-lead-creation',
            'services.hubspot.portal_id' => '243484989',
        ]);
        $GLOBALS['_wp_remote_post_calls'] = [];
        unset($GLOBALS['_wp_remote_post_response']);

        $listener = new HandleLeadEventsForWebhook(new LeadActivityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Banned Person',
            'email' => 'banned@throwaway.test',
            'status' => 'captured',
            'submission_type' => 'Partial',
            'hubspot_contact_id' => '80125514',
        ]);

        $lead->forceFill(['is_blocked' => true])->saveQuietly();
        $lead->refresh();

        $listener->handleLeadFormCaptured(new LeadCreated($lead, []));
        $listener->handleHubSpotSynced($lead, 'created');

        expect($GLOBALS['_wp_remote_post_calls'])->toBeEmpty();
    });

    test('both n8n endpoints are committed in config, with no env or setting to fill in', function () {
        $services = require __DIR__.'/../../config/services.php';

        expect($services['webhooks']['lead_form_url'])
            ->toBe('https://n8n.srv1338052.hstgr.cloud/webhook/lead-form')
            ->and($services['webhooks']['hubspot_lead_url'])
            ->toBe('https://n8n.srv1338052.hstgr.cloud/webhook/hubspot-lead-creation');
    });

    test('the lead detail view links out to the HubSpot contact record', function () {
        config(['services.hubspot.portal_id' => '243484989']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dara Okonkwo',
            'email' => 'dara@kestrellabs.com',
            'status' => 'captured',
            'submission_type' => 'Partial',
            'hubspot_contact_id' => '80125512',
            'hubspot_lifecycle_stage' => 'lead',
        ]);

        ob_start();
        (new LeadsAdminDashboard)->renderLeadDetail((int) $lead->id);
        $html = (string) ob_get_clean();

        expect($html)->toContain('https://app.hubspot.com/contacts/243484989/record/0-1/80125512')
            ->and($html)->toContain('Open in HubSpot')
            ->and($html)->toContain('80125512')
            ->and($html)->toContain('lead');
    });

    test('a lead that never reached HubSpot says so, and offers the email search instead', function () {
        config(['services.hubspot.portal_id' => '243484989']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unsynced Lead',
            'email' => 'unsynced@northwind.io',
            'status' => 'captured',
            'submission_type' => 'Partial',
        ]);

        ob_start();
        (new LeadsAdminDashboard)->renderLeadDetail((int) $lead->id);
        $html = (string) ob_get_clean();

        /*
         * The card renders either way. An absent card reads as "this lead has nothing to do
         * with HubSpot", which is a different claim from "the sync did not happen" — the same
         * reasoning the Session Replay card above it already follows.
         */
        expect($html)->toContain('has not reached HubSpot')
            ->and($html)->toContain('Search HubSpot by email')
            ->and($html)->toContain(rawurlencode('unsynced@northwind.io'))
            ->and($html)->not->toContain('Open in HubSpot');
    });

    test('a synced lead with no portal ID configured shows the contact ID rather than nothing', function () {
        config(['services.hubspot.portal_id' => '']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Linkless Lead',
            'email' => 'linkless@northwind.io',
            'status' => 'captured',
            'submission_type' => 'Partial',
            'hubspot_contact_id' => '80125599',
        ]);

        ob_start();
        (new LeadsAdminDashboard)->renderLeadDetail((int) $lead->id);
        $html = (string) ob_get_clean();

        // Distinct from the "never synced" case above: the contact exists, and it is the
        // portal ID that is missing. Collapsing the two would send someone hunting a sync
        // failure that did not happen.
        expect($html)->toContain('no HubSpot portal ID is configured')
            ->and($html)->toContain('80125599')
            ->and($html)->not->toContain('Open in HubSpot');
    });

    test('the Slack booking alert stays silent unless it is switched on', function () {
        // Default behaviour: the legacy feed's condition is `submission_type is not Final`, so
        // a completed booking produces no second alert and no consumption row.
        config([
            'services.slack.webhook_url' => 'https://hooks.slack.com/services/test/123',
            'services.slack.notify_on_booking' => false,
        ]);

        $listener = new HandleLeadEventsForSlack(new LeadActivityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Quiet Booking',
            'email' => 'quiet@startup.com',
            'source_type' => 'organic',
            'status' => 'booked',
        ]);

        $listener->handleBookingCompleted(new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'meet-quiet',
            provider: 'calendly',
            meetUrl: 'https://meet.google.com/quiet',
            startTime: '2026-09-16T15:00:00Z',
        ));

        expect(LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Slack')
            ->count())->toBe(0);
    });

    test('HandleLeadEventsForWebhook dispatches and logs consumption for partial and final events', function () {
        config(['services.webhooks.lead_webhook_url' => 'https://api.example.com/webhooks/leads']);
        $activityLogger = new LeadActivityLogger;
        $listener = new HandleLeadEventsForWebhook($activityLogger);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Alice Smith',
            'email' => 'alice@company.com',
            'monthly_revenue' => '$100k+ Per Month',
            'source_type' => 'partnership',
            'source_id' => 'tech-partner',
            'status' => 'captured',
        ]);

        // Partial capture event
        $listener->handleCreated(new LeadCreated($lead, ['preferred_slot' => null]));

        $partialLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'OutgoingWebhook')
            ->where('event_type', 'LeadCreated')
            ->first();

        expect($partialLog)->not->toBeNull()
            ->and($partialLog->stage)->toBe('consumption');

        // Final booking completed event
        $bookingCompleted = new LeadBookingCompleted(
            lead: $lead,
            meetingId: 'meet-5678',
            provider: 'calendly',
            meetUrl: 'https://meet.google.com/xyz-123',
            startTime: '2026-09-16T16:00:00Z'
        );

        $listener->handleBookingCompleted($bookingCompleted);

        $finalLog = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'OutgoingWebhook')
            ->where('event_type', 'LeadBookingCompleted')
            ->first();

        expect($finalLog)->not->toBeNull()
            ->and($finalLog->outcome)->toBe('succeeded');
    });

    test('LeadsAdminDashboard smart search and KPI caching execute efficiently at scale', function () {
        Lead::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Enterprise',
            'email' => 'john@enterprise.io',
            'phone' => '+13055550199',
            'company' => 'Enterprise Global',
            'monthly_revenue' => '$50k to $100k Per Month',
            'status' => 'booked',
        ]);

        Lead::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Startup',
            'email' => 'sarah@startup.co',
            'phone' => '+14155550188',
            'company' => 'Startup AI',
            'monthly_revenue' => '$0 to $5k Per Month',
            'status' => 'partial',
        ]);

        $dashboard = new LeadsAdminDashboard;

        // Use reflection to access protected applyOptimizedSearch
        $reflection = new ReflectionClass($dashboard);
        $method = $reflection->getMethod('applyOptimizedSearch');
        $method->setAccessible(true);

        // 1. Test email search routing
        $emailQuery = Lead::query();
        $method->invoke($dashboard, $emailQuery, 'john@enterprise.io');
        expect($emailQuery->count())->toBe(1)
            ->and($emailQuery->first()->email)->toBe('john@enterprise.io');

        // 2. Test phone search routing
        $phoneQuery = Lead::query();
        $method->invoke($dashboard, $phoneQuery, '4155550188');
        expect($phoneQuery->count())->toBe(1)
            ->and($phoneQuery->first()->email)->toBe('sarah@startup.co');

        // 3. Test text search fallback
        $textQuery = Lead::query();
        $method->invoke($dashboard, $textQuery, 'Enterprise');
        expect($textQuery->count())->toBe(1)
            ->and($textQuery->first()->name)->toBe('John Enterprise');
    });

    test('findRecentBookingForSlot ignores dispatch-stage logs and only matches a real Scheduling consumption success for the same slot', function () {
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Priya Nair',
            'email' => 'priya@growthco.com',
            'status' => 'captured',
        ]);

        $bookedSlot = '2026-10-01T14:00:00+00:00';

        // Step 1 of the wizard: partial-capture dispatch. logDispatch() always
        // writes outcome=succeeded — that only means "the event fired," not
        // "a meeting was booked" — so this must NOT satisfy the guard.
        $activityLogger->logDispatch(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Lead',
        );

        expect($activityLogger->findRecentBookingForSlot('priya@growthco.com', $bookedSlot))->toBeNull();

        // The real booking submission a few seconds later: Scheduling's
        // consumption-stage log is the only thing that should match.
        $activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Scheduling',
            outcome: 'succeeded',
            description: 'Scheduled consultation meeting (calendly: cal_123)',
            payload: ['meeting_id' => 'cal_123', 'provider' => 'calendly', 'meet_url' => 'https://calendly.com/x', 'start_time' => $bookedSlot],
        );

        $match = $activityLogger->findRecentBookingForSlot('priya@growthco.com', $bookedSlot);
        expect($match)->not->toBeNull()
            ->and($match->payload['meeting_id'])->toBe('cal_123')
            ->and($activityLogger->findRecentBookingForSlot('nobody-else@growthco.com', $bookedSlot))->toBeNull();

        // A different slot is a fresh booking request, not a duplicate — must
        // NOT match, so BookMeetingAction falls through to a real Calendly call.
        expect($activityLogger->findRecentBookingForSlot('priya@growthco.com', '2026-10-02T09:00:00+00:00'))->toBeNull();
    });

    test('findPriorBookingForDifferentSlot finds a real prior Calendly booking to cancel, ignoring dedup placeholders and non-Calendly bookings', function () {
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Marcus Webb',
            'email' => 'marcus@scaleup.io',
            'status' => 'captured',
        ]);

        $originalSlot = '2026-10-01T14:00:00+00:00';
        $newSlot = '2026-10-03T16:00:00+00:00';

        // Original real booking.
        $activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'LeadCreated',
            actorDomain: 'Scheduling',
            outcome: 'succeeded',
            payload: ['meeting_id' => 'cal_original', 'provider' => 'calendly', 'meet_url' => 'https://calendly.com/x', 'start_time' => $originalSlot],
        );

        $match = $activityLogger->findPriorBookingForDifferentSlot('marcus@scaleup.io', $newSlot);
        expect($match)->not->toBeNull()
            ->and($match->payload['meeting_id'])->toBe('cal_original');

        // Same slot as the "new" one being booked — nothing to cancel.
        expect($activityLogger->findPriorBookingForDifferentSlot('marcus@scaleup.io', $originalSlot))->toBeNull();
    });
});
