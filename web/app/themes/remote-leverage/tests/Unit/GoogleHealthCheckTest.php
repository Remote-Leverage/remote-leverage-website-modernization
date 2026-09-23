<?php

declare(strict_types=1);

use App\Domains\Lead\Services\LeadSettingsService;
use App\Infrastructure\Observability\Health\Checks\GoogleHealthCheck;

/*
 * GoogleHealthCheck is a thin wrapper around BigQueryClient's own idea of "configured" — see
 * its docblock for why that's deliberate. What these tests actually pin down is the *reason*
 * text, which is the one thing this class adds: BigQueryClient::misconfiguration() already
 * distinguishes "nobody signed in" from "signed in, but no project", and WarehouseOAuth's
 * connected account has to show up in the second case without WarehouseOAuth ever being asked
 * for anything in the first.
 */

beforeEach(function () {
    config([
        'marketing.warehouse.credentials' => '',
        'marketing.warehouse.client_id' => '',
        'marketing.warehouse.client_secret' => '',
        'marketing.warehouse.refresh_token' => '',
        'marketing.warehouse.project_id' => '',
    ]);

    update_option(LeadSettingsService::OPTION_KEY, []);
});

test('nothing configured reports the generic sign-in reason with no account named', function () {
    $check = new GoogleHealthCheck;

    expect($check->isConfigured())->toBeFalse()
        ->and($check->unconfiguredReason())->toBe('No Google credential is configured — sign in, or paste a service account key')
        ->and($check->unconfiguredReason())->not->toContain('(');
});

test('a service account key with no project id names the problem, not an account', function () {
    config(['marketing.warehouse.credentials' => json_encode([
        'type' => 'service_account',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n",
        'client_email' => 'warehouse-reader@rl.iam.gserviceaccount.com',
        // Deliberately no project_id — this is the "key names no project" branch.
    ])]);

    $check = new GoogleHealthCheck;

    expect($check->isConfigured())->toBeFalse()
        ->and($check->unconfiguredReason())->toContain('service account key names no project')
        // A service account's own email is not the same thing as an OAuth sign-in, and
        // WarehouseOAuth has nothing stored — this must not fabricate an account.
        ->and($check->unconfiguredReason())->not->toContain('(');
});

test('signed in via OAuth but with no project names the connected account', function () {
    config([
        'marketing.warehouse.client_id' => 'test-client-id',
        'marketing.warehouse.client_secret' => 'test-client-secret',
        'marketing.warehouse.refresh_token' => 'test-refresh-token',
    ]);

    update_option(LeadSettingsService::OPTION_KEY, [
        'bigquery_refresh_token' => 'test-refresh-token',
        'bigquery_connected_email' => 'ops@remoteleverage.com',
    ]);

    $check = new GoogleHealthCheck;

    expect($check->isConfigured())->toBeFalse()
        ->and($check->unconfiguredReason())->toBe(
            'A signed-in Google account does not name a project; set the billing project ID (ops@remoteleverage.com)'
        );
});

test('a full service account credential with a project id is configured', function () {
    config(['marketing.warehouse.credentials' => json_encode([
        'type' => 'service_account',
        'project_id' => 'rl-marketing-warehouse',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n",
        'client_email' => 'warehouse-reader@rl.iam.gserviceaccount.com',
    ])]);

    expect((new GoogleHealthCheck)->isConfigured())->toBeTrue();
});

test('a service account key with no usable private key is not configured', function () {
    // Decodable JSON, but not a real key — BigQueryClient::decodeServiceAccount() rejects this
    // specifically, and the reason should say so rather than "no credential configured".
    config(['marketing.warehouse.credentials' => json_encode([
        'type' => 'service_account',
        'project_id' => 'rl-marketing-warehouse',
    ])]);

    expect((new GoogleHealthCheck)->isConfigured())->toBeFalse();
});
