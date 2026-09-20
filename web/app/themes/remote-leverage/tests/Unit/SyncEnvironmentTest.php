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

/*
 * Provisioning is the one sync surface that is NOT environment-gated, because
 * production has to be able to issue the credential a lower environment pulls
 * with. What that credential can then do is decided by the abilities — see the
 * read-only/write split asserted below — not by refusing to mint it.
 */
it('provisions credentials in production rather than refusing on the environment', function () {
    setWpEnv('production');

    // It gets past the environment and fails on application passwords instead,
    // which is the next thing a real production request would be judged on.
    expect(fn () => (new SyncCredentialProvisioner)->provision())
        ->toThrow(RuntimeException::class)
        ->and(fn () => (new SyncCredentialProvisioner)->provision())
        ->not->toThrow(SyncNotPermittedException::class);
});

it('revokes credentials in production, so the read credential has a kill switch', function () {
    setWpEnv('production');

    // No sync-service user here, so this is the early return. The assertion is
    // that it returns at all: it used to throw before reaching the lookup.
    (new SyncCredentialProvisioner)->revoke();

    expect(true)->toBeTrue();
});

describe('which transfer abilities production will answer', function () {
    /*
     * The gate that actually protects production, now that the admin screen and
     * the provisioner no longer refuse there. A write ability added later
     * inherits the refusal by extending TransferAbility; this fails if one is
     * ever put on the read-only base by mistake.
     */
    $readOnly = [
        'ExportTransferBatchAbility',
        'ExportMediaManifestAbility',
        'ReadMediaFileAbility',
    ];

    it('exposes exactly the three a pull calls, and no more', function () use ($readOnly) {
        $onReadOnlyBase = [];

        foreach (glob(__DIR__.'/../../app/Domains/Sync/Abilities/*.php') ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), 'extends ReadOnlyTransferAbility')) {
                $onReadOnlyBase[] = basename($file, '.php');
            }
        }

        expect($onReadOnlyBase)->toEqualCanonicalizing($readOnly);
    });

    it('keeps every other transfer ability on the production-refusing base', function () use ($readOnly) {
        foreach (glob(__DIR__.'/../../app/Domains/Sync/Abilities/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $readOnly, true) || $name === 'TransferAbility' || $name === 'ReadOnlyTransferAbility') {
                continue;
            }

            $source = (string) file_get_contents($file);

            if (! str_contains($source, 'extends TransferAbility')) {
                continue; // a plain Ability — the settings/page sync, gated elsewhere
            }

            expect($source)->not->toContain('extends ReadOnlyTransferAbility');
        }

        expect(true)->toBeTrue();
    });
});
