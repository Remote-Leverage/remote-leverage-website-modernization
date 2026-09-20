<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The cache facade, for callers that must not die with it.
 *
 * ## Why this exists
 *
 * Acorn's cache has no graceful mode. On the `file` driver that hardly matters — a read from the
 * container's own filesystem does not realistically fail. On Redis it does: a failover, a security
 * group change, a full instance, and every `Cache::get()` throws.
 *
 * Most callers can afford that, because an admin screen returning an error is a bad afternoon.
 * These cannot. `LiveCallAvailabilityRouter`, `CalendlyTokenPool` and `CalendlyClient` sit on the
 * public booking path, and a thrown cache read there means nobody can book — the cache would have
 * turned from an optimisation into a hard dependency of taking money.
 *
 * That asymmetry is the whole point of this class. It is not "cache errors are fine"; it is "on
 * this path, a cache that is down must look exactly like a cache that is empty".
 *
 * ## The contract
 *
 * Every method degrades to the empty-cache answer, which each caller already handles because it is
 * the state on a cold container:
 *
 *   - get()    -> the caller's own default
 *   - has()    -> false
 *   - put()    -> discarded
 *   - forget() -> nothing to forget
 *
 * The consequences are worth naming rather than discovering. A dead cache means the availability
 * flag reads as available, Calendly rate-limit cooldowns stop being observed, and token identities
 * are re-fetched on every call. That is more load on Calendly and a busier log, against a booking
 * funnel that stays open. It is the right trade here and the wrong one almost everywhere else, so
 * this is deliberately not a global cache decorator.
 *
 * ## Logging
 *
 * Once per request, not once per call. An outage otherwise writes a line per cache operation per
 * request, which buries the one line that says why.
 */
final class SoftCache
{
    private static bool $reported = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable $e) {
            self::report('get', $key, $e);

            return $default;
        }
    }

    public static function has(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (Throwable $e) {
            self::report('has', $key, $e);

            return false;
        }
    }

    /**
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool Whether the value was stored. False means the caller should assume it was not.
     */
    public static function put(string $key, mixed $value, mixed $ttl = null): bool
    {
        try {
            return (bool) Cache::put($key, $value, $ttl);
        } catch (Throwable $e) {
            self::report('put', $key, $e);

            return false;
        }
    }

    public static function forget(string $key): bool
    {
        try {
            return (bool) Cache::forget($key);
        } catch (Throwable $e) {
            self::report('forget', $key, $e);

            return false;
        }
    }

    /**
     * Reset the once-per-request guard. For tests, which are many requests in one process.
     */
    public static function resetReporting(): void
    {
        self::$reported = false;
    }

    private static function report(string $operation, string $key, Throwable $e): void
    {
        if (self::$reported) {
            return;
        }

        self::$reported = true;

        Log::warning('SoftCache: the cache is unavailable; continuing without it', [
            'operation' => $operation,
            'key' => $key,
            'error' => $e->getMessage(),
        ]);
    }
}
