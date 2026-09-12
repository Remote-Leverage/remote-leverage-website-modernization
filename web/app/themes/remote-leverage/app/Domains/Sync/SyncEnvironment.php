<?php

declare(strict_types=1);

namespace App\Domains\Sync;

/**
 * Which environment this code is running in, for the sync feature's gating.
 *
 * Every sync surface — the admin screen, each ability, the client's choice of
 * target — asks this class rather than reading WP_ENV directly, so the
 * production rule is defined once and cannot drift between call sites.
 *
 * Defaults to production when WP_ENV is absent or unrecognised: an unknown
 * environment is treated as the one where sync must never run.
 */
final class SyncEnvironment
{
    public const PRODUCTION = 'production';

    /**
     * Environments the sync feature may run in at all.
     */
    private const ALLOWED = ['development', 'local', 'staging'];

    public static function current(): string
    {
        $env = function_exists('env') ? env('WP_ENV') : null;

        if (! is_string($env) || $env === '') {
            $env = defined('WP_ENV') ? (string) WP_ENV : self::PRODUCTION;
        }

        return strtolower($env);
    }

    public static function isProduction(): bool
    {
        return self::current() === self::PRODUCTION;
    }

    /**
     * Whether any part of the sync feature may operate here.
     *
     * Note this is not simply "not production" — an unrecognised WP_ENV is
     * refused too, so a typo in the container's env can only ever fail closed.
     */
    public static function syncEnabled(): bool
    {
        return in_array(self::current(), self::ALLOWED, true);
    }

    /**
     * Guard for the entry point of anything destructive or credential-bearing.
     *
     * @throws SyncNotPermittedException
     */
    public static function assertSyncEnabled(): void
    {
        if (! self::syncEnabled()) {
            throw new SyncNotPermittedException(
                'Environment sync is not available in the "'.self::current().'" environment.'
            );
        }
    }
}
