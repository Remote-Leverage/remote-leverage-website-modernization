<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForEmailNotification;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Infrastructure\Slack\SlackCredentials;
use Illuminate\Support\Str;

describe('Lead Settings (WR-102)', function () {
    beforeEach(function () {
        $GLOBALS['_wp_mock_options'] = [];
        $GLOBALS['_wp_mock_mail_sent'] = [];
        LeadActivityLog::truncate();
        Lead::truncate();
    });

    test('LeadSettingsService::get returns defaults when unconfigured', function () {
        $settings = (new LeadSettingsService)->get();

        expect($settings['retention_days'])->toBe(30)
            ->and($settings['notification_emails'])->toBe([])
            ->and($settings['optional_fields'])->toBe(['company' => true, 'notes' => true, 'phone_country' => true]);
    });

    test('LeadSettingsService::save rejects retention days below the 30-day floor', function () {
        $result = (new LeadSettingsService)->save([
            'retention_days' => '10',
            'notification_emails' => 'ops@remoteleverage.com',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'][0])->toContain('30-day minimum floor');
    });

    test('LeadSettingsService::save rejects invalid notification email addresses', function () {
        $result = (new LeadSettingsService)->save([
            'retention_days' => '45',
            'notification_emails' => 'not-an-email, ops@remoteleverage.com',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'][0])->toContain('not-an-email');
    });

    test('LeadSettingsService::save persists sanitized settings and syncs legacy retention option', function () {
        $result = (new LeadSettingsService)->save([
            'retention_days' => '45',
            'notification_emails' => ' sales@remoteleverage.com , ops@remoteleverage.com ',
            'optional_fields' => ['company' => '1'],
            'hubspot_access_token' => 'token-123',
            'hubspot_portal_id' => 'portal-456',
            'slack_webhook_url' => 'https://hooks.slack.com/services/test/xyz',
            'lead_webhook_url' => 'https://api.example.com/webhooks/leads',
        ]);

        expect($result['success'])->toBeTrue();

        $settings = (new LeadSettingsService)->get();
        expect($settings['retention_days'])->toBe(45)
            ->and($settings['notification_emails'])->toBe(['sales@remoteleverage.com', 'ops@remoteleverage.com'])
            ->and($settings['optional_fields']['company'])->toBeTrue()
            ->and($settings['optional_fields']['notes'])->toBeFalse()
            ->and($settings['hubspot_access_token'])->toBe('token-123');

        expect(get_option('rl_lead_retention_days'))->toBe(45);
    });

    test('HubSpotGateway prefers the settings-configured access token over the env value', function () {
        (new LeadSettingsService)->save([
            'retention_days' => '30',
            'hubspot_access_token' => 'override-token',
            'hubspot_portal_id' => 'override-portal',
        ]);

        $gateway = new HubSpotGateway(new LeadSettingsService);

        $reflection = new ReflectionClass($gateway);
        $tokenProperty = $reflection->getProperty('accessToken');
        $tokenProperty->setAccessible(true);

        expect($tokenProperty->getValue($gateway))->toBe('override-token');
    });

    test('HandleLeadEventsForEmailNotification emails configured recipients and logs consumption', function () {
        $settingsService = new LeadSettingsService;
        $settingsService->save([
            'retention_days' => '30',
            'notification_emails' => 'sales@remoteleverage.com',
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Notify Me',
            'email' => 'notify@lead.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);

        $listener = new HandleLeadEventsForEmailNotification(new LeadActivityLogger, $settingsService);
        $listener->handleCreated(new LeadCreated($lead, []));

        expect($GLOBALS['_wp_mock_mail_sent'])->toHaveCount(1)
            ->and($GLOBALS['_wp_mock_mail_sent'][0]['to'])->toBe(['sales@remoteleverage.com']);

        $log = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'EmailNotification')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->outcome)->toBe('succeeded');
    });

    test('HandleLeadEventsForEmailNotification is a no-op when no recipients are configured', function () {
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'No Notify',
            'email' => 'nonotify@lead.com',
            'source_type' => 'organic',
            'status' => 'captured',
        ]);

        $listener = new HandleLeadEventsForEmailNotification(new LeadActivityLogger, new LeadSettingsService);
        $listener->handleCreated(new LeadCreated($lead, []));

        expect($GLOBALS['_wp_mock_mail_sent'])->toBe([]);
    });
});

/*
 * The Slack signing secret as an admin setting.
 *
 * It is here rather than only in the environment because ECS maps Secrets Manager keys to
 * environment variables one at a time in the task definition, so a newly added credential cannot
 * reach staging any other way today — and the Lead settings blob is in the environment-sync
 * whitelist precisely so a value set locally can be pushed there without a deploy.
 */
describe('Slack credentials as settings', function () {
    beforeEach(function () {
        $GLOBALS['_wp_mock_options'] = [];
        config([
            'services.slack.signing_secret' => '',
            'services.slack.bot_token' => '',
            'services.slack.channel' => '',
        ]);
    });

    test('the signing secret round-trips through the settings blob', function () {
        (new LeadSettingsService)->save([
            'retention_days' => '30',
            'notification_emails' => '',
            'slack_signing_secret' => '  5967cd4ef0bdc5666653580e8dd8d8f4  ',
        ]);

        expect((new LeadSettingsService)->get()['slack_signing_secret'])
            ->toBe('5967cd4ef0bdc5666653580e8dd8d8f4');
    });

    test('the environment wins over the setting', function () {
        (new LeadSettingsService)->save([
            'retention_days' => '30',
            'notification_emails' => '',
            'slack_signing_secret' => 'from_the_database',
        ]);

        config(['services.slack.signing_secret' => 'from_the_environment']);

        expect(SlackCredentials::signingSecret())->toBe('from_the_environment');
    });

    test('the setting is used when the environment is blank', function () {
        // The staging case: no task-definition change, the value arrives by environment sync.
        (new LeadSettingsService)->save([
            'retention_days' => '30',
            'notification_emails' => '',
            'slack_signing_secret' => 'from_the_database',
        ]);

        expect(SlackCredentials::signingSecret())->toBe('from_the_database');
    });

    test('saving the settings screen does not wipe a credential it never rendered', function () {
        /*
         * `save()` replaces the whole blob, so a key the form does not post used to be written
         * back as an empty string. The Slack bot token and channel have never been on that form
         * — they arrive by environment sync — so saving the screen for an unrelated reason
         * silently unwired Slack, after which alerts fell back to the incoming webhook with
         * nothing anywhere to say why.
         */
        $service = new LeadSettingsService;

        $service->save([
            'retention_days' => '30',
            'notification_emails' => '',
            'slack_bot_token' => 'xoxb-synced-from-local',
            'slack_channel' => 'C086BBKUXL5',
            'slack_signing_secret' => 'synced-secret',
        ]);

        // A later save from the settings screen, which posts neither of those three.
        $service->save([
            'retention_days' => '45',
            'notification_emails' => 'ops@remoteleverage.com',
            'slack_webhook_url' => 'https://hooks.slack.com/services/abc',
        ]);

        $settings = $service->get();

        expect($settings['slack_bot_token'])->toBe('xoxb-synced-from-local')
            ->and($settings['slack_channel'])->toBe('C086BBKUXL5')
            ->and($settings['slack_signing_secret'])->toBe('synced-secret')
            ->and($settings['retention_days'])->toBe(45);
    });

    test('a submitted empty value still clears a credential', function () {
        // Absent means "not on this form". Present-but-empty is how a credential is removed,
        // and conflating the two would make one impossible to delete from the screen.
        $service = new LeadSettingsService;

        $service->save([
            'retention_days' => '30',
            'notification_emails' => '',
            'slack_signing_secret' => 'synced-secret',
        ]);

        $service->save([
            'retention_days' => '30',
            'notification_emails' => '',
            'slack_signing_secret' => '',
        ]);

        expect($service->get()['slack_signing_secret'])->toBe('');
    });
});

describe('the warehouse refresh token', function () {
    /*
     * The token is written by the OAuth callback and, until now, by nothing else — so it was
     * unreachable: there was no way to read it out of the environment that consented, and no way
     * to write it into one that had not. Google issues a refresh token only on first consent, so
     * once the person who signed in moves on, an environment without the value can never obtain
     * one. Making the field readable and writable on the settings screen is what makes it
     * portable, and these two tests are the halves of that.
     */
    test('a save that omits it leaves the value the OAuth callback wrote', function () {
        $service = new LeadSettingsService;

        $service->save(['retention_days' => '30', 'notification_emails' => '', 'bigquery_refresh_token' => '1//consented']);
        $service->save(['retention_days' => '45', 'notification_emails' => '']);

        expect($service->get()['bigquery_refresh_token'])->toBe('1//consented');
    });

    test('a save that carries it adopts a token issued on another environment', function () {
        $service = new LeadSettingsService;

        $service->save(['retention_days' => '30', 'notification_emails' => '', 'bigquery_refresh_token' => '1//from-staging']);

        expect($service->get()['bigquery_refresh_token'])->toBe('1//from-staging');
    });
});
