<?php

declare(strict_types=1);

use App\Infrastructure\Cache\SoftCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
 * A cache that is down has to look exactly like a cache that is empty.
 *
 * Acorn's cache has no graceful mode. On the `file` driver that hardly mattered; on Redis a
 * failover makes every read throw, and these callers sit on the public booking path — the cache
 * would become a hard dependency of taking money. Staging moved to Redis on 2026-09-20, so this
 * stopped being hypothetical.
 *
 * Facade::swap rather than a mocking library, because this suite has no Mockery: a plain object
 * that throws is a closer model of a dead Redis anyway.
 */
function breakTheCache(): void
{
    SoftCache::resetReporting();

    Cache::swap(new class
    {
        public function get($key, $default = null)
        {
            throw new RuntimeException('READONLY You can\'t write against a read only replica');
        }

        public function has($key): bool
        {
            throw new RuntimeException('Connection refused');
        }

        public function put($key, $value, $ttl = null): bool
        {
            throw new RuntimeException('Connection refused');
        }

        public function forget($key): bool
        {
            throw new RuntimeException('Connection refused');
        }
    });
}

/** @return array<int, string> The warnings logged while $work ran. */
function warningsWhile(callable $work): array
{
    $seen = [];

    Log::swap(new class($seen)
    {
        public function __construct(public array &$seen) {}

        public function warning($message, array $context = []): void
        {
            $this->seen[] = (string) $message;
        }

        public function __call($name, $arguments) {}
    });

    $work();

    return $seen;
}

afterEach(function () {
    /*
     * `Facade::swap()` does two things, and clearing the facade only undoes one of them: it
     * caches the instance on the facade *and* calls `$app->instance('cache', ...)`, which
     * replaces the container binding for the rest of the process. So without the
     * `forgetInstance()` below, every test that ran after this file got the cache that throws —
     * silently, because most of them never touch it.
     *
     * The one that did notice was BookingFlowEndToEndTest: `submitBooking()` opens with a
     * `Cache::lock()`, which died on the swapped object, so the whole booking flow failed in the
     * full suite while passing on its own. Dropping the instance lets the container fall back to
     * the singleton binding in tests/stubs.php.
     */
    Cache::clearResolvedInstances();
    app()->forgetInstance('cache');
    Log::clearResolvedInstances();
    SoftCache::resetReporting();
});

it('passes straight through when the cache is healthy', function () {
    SoftCache::put('rl_soft_probe', 'kept', 60);

    expect(SoftCache::get('rl_soft_probe'))->toBe('kept')
        ->and(SoftCache::has('rl_soft_probe'))->toBeTrue();

    SoftCache::forget('rl_soft_probe');

    expect(SoftCache::get('rl_soft_probe', 'gone'))->toBe('gone');
});

it('returns the caller default rather than propagating', function () {
    breakTheCache();

    // LiveCallAvailabilityRouter reads with a default of true, so a dead cache leaves live calls
    // available — the same answer a cold container gives.
    expect(SoftCache::get('rl_live_call_available', true))->toBeTrue()
        ->and(SoftCache::get('anything'))->toBeNull();
});

it('reports has() as false, so a cooldown goes unobserved rather than fatal', function () {
    breakTheCache();

    // CalendlyTokenPool asks this before using a token. False means "not rate limited", so the
    // call is attempted: more load on Calendly, against a booking funnel that stays open.
    expect(SoftCache::has('rl_calendly_ratelimit_abc'))->toBeFalse();
});

it('discards writes and says so', function () {
    breakTheCache();

    expect(SoftCache::put('k', 'v', 60))->toBeFalse()
        ->and(SoftCache::forget('k'))->toBeFalse();
});

/*
 * One line per request, not one per call. Eighteen call sites across the booking path would
 * otherwise bury the single line saying why.
 */
it('logs once however many calls fail', function () {
    breakTheCache();

    $warnings = warningsWhile(function () {
        SoftCache::get('a');
        SoftCache::get('b');
        SoftCache::has('c');
        SoftCache::put('d', 1, 60);
    });

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('cache is unavailable');
});
