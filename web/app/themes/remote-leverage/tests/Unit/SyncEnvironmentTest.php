<?php

declare(strict_types=1);

use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\SyncNotPermittedException;

/**
 * The production gate is the whole safety story for environment sync, so it is
 * tested for what it refuses as much as for what it allows.
 */
function setWpEnv(?string $env): void
{
    if ($env === null) {
        unset($_ENV['WP_ENV'], $_SERVER['WP_ENV']);
        putenv('WP_ENV');

        return;
    }

    $_ENV['WP_ENV'] = $env;
    $_SERVER['WP_ENV'] = $env;
    putenv('WP_ENV='.$env);
}

afterEach(function () {
    setWpEnv(null);
});

it('allows sync in the development environments', function (string $env) {
    setWpEnv($env);

    expect(SyncEnvironment::current())->toBe($env)
        ->and(SyncEnvironment::isProduction())->toBeFalse()
        ->and(SyncEnvironment::syncEnabled())->toBeTrue();
})->with(['development', 'local', 'staging']);

it('refuses sync in production', function () {
    setWpEnv('production');

    expect(SyncEnvironment::isProduction())->toBeTrue()
        ->and(SyncEnvironment::syncEnabled())->toBeFalse();
});

it('fails closed for an unrecognised environment', function (string $env) {
    setWpEnv($env);

    expect(SyncEnvironment::syncEnabled())->toBeFalse();
})->with(['prod', 'stagng', 'qa', '']);

it('treats a missing WP_ENV as production', function () {
    setWpEnv(null);

    expect(SyncEnvironment::syncEnabled())->toBeFalse();
});

it('normalises case so STAGING is still staging', function () {
    setWpEnv('STAGING');

    expect(SyncEnvironment::current())->toBe('staging')
        ->and(SyncEnvironment::syncEnabled())->toBeTrue();
});

it('throws from assertSyncEnabled in production', function () {
    setWpEnv('production');

    SyncEnvironment::assertSyncEnabled();
})->throws(SyncNotPermittedException::class);

it('does not throw from assertSyncEnabled in staging', function () {
    setWpEnv('staging');
    SyncEnvironment::assertSyncEnabled();

    expect(true)->toBeTrue();
});

it('refuses to provision credentials in production before touching WordPress', function () {
    setWpEnv('production');

    (new SyncCredentialProvisioner)->provision();
})->throws(SyncNotPermittedException::class);

it('refuses to revoke credentials in production', function () {
    setWpEnv('production');

    (new SyncCredentialProvisioner)->revoke();
})->throws(SyncNotPermittedException::class);
