<?php

declare(strict_types=1);

use App\Ai\InsightsCapability;
use App\Domains\Lead\Provisioning\DataApiCredentialProvisioner;
use App\Infrastructure\WordPress\Admin\DataApiAdmin;

/**
 * The data API credential reads every lead and booking, customer PII included,
 * and it can only be issued from wp-admin because nothing else can reach
 * staging or production. So what is pinned here is what fails silently and
 * expensively:
 *
 *  - the user holds the one capability and nothing beyond subscriber, even
 *    after somebody has promoted the account;
 *  - credentials are individually revocable, so cutting one consumer off never
 *    makes another reconnect;
 *  - a password somebody made by hand on the same user is not this tool's to
 *    list or revoke;
 *  - the screen never renders a plaintext except the once, and is refused to
 *    anyone without manage_options.
 */
function dataApiServiceUser(): WP_User
{
    $user = get_user_by('login', DataApiCredentialProvisioner::USER_LOGIN);

    expect($user)->toBeInstanceOf(WP_User::class);

    return $user;
}

function renderDataApiScreen(): string
{
    ob_start();

    try {
        (new DataApiAdmin(new DataApiCredentialProvisioner))->render();
    } finally {
        $html = (string) ob_get_clean();
    }

    return $html;
}

function dataApiIssuedTransientKey(): string
{
    $prefix = (new ReflectionClassConstant(DataApiAdmin::class, 'ISSUED_TRANSIENT'))->getValue();

    return $prefix.get_current_user_id();
}

beforeEach(function () {
    $GLOBALS['_wp_mock_users'] = [];
    $GLOBALS['_wp_mock_application_passwords'] = [];

    // Application passwords refuse plain HTTP outside local; every test but
    // the one about that refusal wants to be able to mint.
    $GLOBALS['_wp_mock_is_ssl'] = true;
});

afterEach(function () {
    delete_transient(dataApiIssuedTransientKey());

    // Left behind, a seeded user or HTTPS would let other suites' provisioners
    // mint where they are asserting a refusal.
    unset(
        $GLOBALS['_wp_mock_users'],
        $GLOBALS['_wp_mock_application_passwords'],
        $GLOBALS['_wp_mock_is_ssl'],
        $GLOBALS['_wp_mock_capabilities'],
        $_POST['rl_data_api_action'],
    );
});

describe('the provisioner', function () {
    it('issues a credential as a dedicated subscriber holding the insights capability', function () {
        $issued = (new DataApiCredentialProvisioner)->issuePassword('warehouse-loader');

        expect($issued['user_login'])->toBe('data-api')
            ->and($issued['name'])->toBe('warehouse-loader')
            ->and($issued['password'])->toBeString()->not->toBeEmpty();

        $user = dataApiServiceUser();

        expect($user->user_email)->toBe('data-api@remoteleverage.com')
            ->and($user->display_name)->toBe('Data API')
            ->and($user->roles)->toBe(['subscriber'])
            ->and($user->has_cap(InsightsCapability::NAME))->toBeTrue()
            ->and($user->has_cap('edit_posts'))->toBeFalse();

        // Hashed on save: nothing a screen could read back holds the plaintext.
        $stored = WP_Application_Passwords::get_user_application_passwords($user->ID);

        expect($stored)->toHaveCount(1)
            ->and(json_encode($stored))->not->toContain($issued['password']);
    });

    it('holds a promoted account back to subscriber plus the one capability', function () {
        // Somebody "fixed" something by making the service account an editor
        // and handing it a capability directly. A leaked data credential must
        // not inherit either.
        wp_insert_user(['user_login' => DataApiCredentialProvisioner::USER_LOGIN, 'role' => 'editor']);
        dataApiServiceUser()->add_cap('manage_options');

        (new DataApiCredentialProvisioner)->issuePassword('warehouse-loader');

        $user = dataApiServiceUser();

        expect($user->roles)->toBe(['subscriber'])
            ->and($user->has_cap('publish_pages'))->toBeFalse()
            ->and($user->has_cap('manage_options'))->toBeFalse()
            ->and($user->has_cap(InsightsCapability::NAME))->toBeTrue()
            ->and($user->has_cap('read'))->toBeTrue();
    });

    it('leaves the first credential intact when a second one is issued', function () {
        $provisioner = new DataApiCredentialProvisioner;

        $first = $provisioner->issuePassword('warehouse-loader');
        $second = $provisioner->issuePassword('looker');

        expect($first['password'])->not->toBe($second['password'])
            ->and(array_column($provisioner->passwords(), 'name'))
            ->toEqualCanonicalizing(['warehouse-loader', 'looker']);
    });

    it('revokes one credential and leaves the other working', function () {
        $provisioner = new DataApiCredentialProvisioner;

        $provisioner->issuePassword('warehouse-loader');
        $provisioner->issuePassword('looker');

        $looker = collect($provisioner->passwords())->firstWhere('name', 'looker');

        expect($provisioner->revokePassword($looker['uuid']))->toBeTrue()
            ->and(array_column($provisioner->passwords(), 'name'))->toBe(['warehouse-loader'])
            ->and(dataApiServiceUser()->has_cap(InsightsCapability::NAME))->toBeTrue();
    });

    it('never lists or revokes a password created by hand on the same user', function () {
        $provisioner = new DataApiCredentialProvisioner;
        $provisioner->issuePassword('warehouse-loader');

        $user = dataApiServiceUser();

        // What wp-admin's own profile screen does: a password with no prefix.
        [, $byHand] = WP_Application_Passwords::create_new_application_password($user->ID, ['name' => 'debugging']);

        expect(array_column($provisioner->passwords(), 'name'))->toBe(['warehouse-loader'])
            ->and($provisioner->status()['password_count'])->toBe(1)
            ->and($provisioner->revokePassword($byHand['uuid']))->toBeFalse();

        $provisioner->revokeAll();

        expect(array_column(WP_Application_Passwords::get_user_application_passwords($user->ID), 'name'))
            ->toBe(['debugging']);
    });

    it('revokes every issued credential and the capability as a kill switch', function () {
        $provisioner = new DataApiCredentialProvisioner;
        $provisioner->issuePassword('warehouse-loader');
        $provisioner->issuePassword('looker');

        $provisioner->revokeAll();

        // The capability going is what also disarms any hand-made password,
        // which revokeAll() deliberately leaves in place.
        expect($provisioner->passwords())->toBe([])
            ->and(dataApiServiceUser()->has_cap(InsightsCapability::NAME))->toBeFalse();

        // Issuing again is the way back, and re-grants.
        $provisioner->issuePassword('warehouse-loader');

        expect(dataApiServiceUser()->has_cap(InsightsCapability::NAME))->toBeTrue();
    });

    it('refuses an unnamed credential before creating anything', function (string $name) {
        expect(fn () => (new DataApiCredentialProvisioner)->issuePassword($name))
            ->toThrow(RuntimeException::class);

        expect(get_user_by('login', DataApiCredentialProvisioner::USER_LOGIN))->toBeFalse();
    })->with(['', '   ']);

    it('refuses a name already in use, case-insensitively', function () {
        $provisioner = new DataApiCredentialProvisioner;
        $provisioner->issuePassword('looker');

        expect(fn () => $provisioner->issuePassword('Looker'))->toThrow(RuntimeException::class)
            ->and($provisioner->passwords())->toHaveCount(1);
    });

    it('refuses over plain HTTP with the reason rather than a bare failure', function () {
        $GLOBALS['_wp_mock_is_ssl'] = false;
        $provisioner = new DataApiCredentialProvisioner;

        expect(fn () => $provisioner->issuePassword('warehouse-loader'))
            ->toThrow(RuntimeException::class, 'Application passwords require HTTPS')
            ->and($provisioner->status()['available'])->toBeFalse()
            ->and($provisioner->status()['unavailable_reason'])->toContain('HTTPS');
    });
});

describe('the Settings screen', function () {
    it('shows both endpoints for the current site and never an existing password', function () {
        $issued = (new DataApiCredentialProvisioner)->issuePassword('warehouse-loader');

        $html = renderDataApiScreen();

        expect($html)
            ->toContain(home_url('/wp-json/rl-data/v1/leads'))
            ->toContain(home_url('/wp-json/rl-data/v1/bookings'))
            ->toContain('warehouse-loader')
            ->not->toContain($issued['password'])
            ->not->toContain(base64_encode($issued['user_login'].':'.$issued['password']));
    });

    it('shows a fresh credential exactly once, as a ready header and curl request', function () {
        $issued = (new DataApiCredentialProvisioner)->issuePassword('warehouse-loader');
        set_transient(dataApiIssuedTransientKey(), $issued, 300);

        $header = 'Authorization: Basic '.base64_encode($issued['user_login'].':'.$issued['password']);
        $html = renderDataApiScreen();

        expect($html)
            ->toContain($issued['password'])
            ->toContain(esc_attr($header))
            ->toContain('curl -H')
            ->toMatch('#'.preg_quote(home_url('/wp-json/rl-data/v1/leads'), '#').'\?from=\d{4}-\d{2}-\d{2}&amp;to=\d{4}-\d{2}-\d{2}#');

        expect(renderDataApiScreen())->not->toContain($issued['password']);
    });

    it('refuses to render for a user without manage_options', function () {
        $GLOBALS['_wp_mock_capabilities'] = ['read'];

        expect(fn () => renderDataApiScreen())->toThrow(RuntimeException::class, 'wp_die');
    });

    it('ignores a POST from a user without manage_options', function () {
        $provisioner = new DataApiCredentialProvisioner;
        $provisioner->issuePassword('warehouse-loader');

        // add_options_page's capability argument does not protect admin_init,
        // so the handler has to refuse on its own — before the nonce check,
        // which would otherwise be the first thing to run.
        $GLOBALS['_wp_mock_capabilities'] = ['read'];
        $_POST['rl_data_api_action'] = 'revoke_all';

        (new DataApiAdmin($provisioner))->handleActions();

        expect($provisioner->passwords())->toHaveCount(1)
            ->and(dataApiServiceUser()->has_cap(InsightsCapability::NAME))->toBeTrue();
    });

    it('registers under Settings, gated on manage_options, with its own slug', function () {
        // add_action is a no-op stub, so the menu registration is pinned from
        // source the way AiAccessScreenTest pins its own.
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Infrastructure/WordPress/Admin/DataApiAdmin.php');

        expect(DataApiAdmin::SLUG)->toBe('rl-data-api')
            ->and($source)->toContain('add_options_page(')
            ->and($source)->toContain("capability: 'manage_options'");
    });
});
