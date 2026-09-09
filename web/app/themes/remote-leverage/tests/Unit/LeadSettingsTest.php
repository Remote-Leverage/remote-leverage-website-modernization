<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForEmailNotification;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
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
