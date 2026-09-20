<?php

declare(strict_types=1);

use App\Infrastructure\WordPress\Admin\CacheHealthNotice;
use Illuminate\Contracts\Redis\Factory;

/*
 * The notice exists because the failure it reports is silent. Nothing errors, nothing logs at
 * request time, and every figure on the page is real — just computed by a different container
 * than the one answering. So the detection has to be exactly right in both directions: a false
 * positive trains people to ignore it, and a false negative is the state it was written for.
 */
function renderCacheNotice(): string
{
    ob_start();
    (new CacheHealthNotice)->render();

    return (string) ob_get_clean();
}

beforeEach(function () {
    $GLOBALS['_app_config'] = [];
    unset($_ENV['WP_REDIS_HOST'], $_SERVER['WP_REDIS_HOST']);
    putenv('WP_REDIS_HOST');
});

afterEach(function () {
    unset($_ENV['WP_REDIS_HOST'], $_SERVER['WP_REDIS_HOST']);
    putenv('WP_REDIS_HOST');
});

function withRedisHost(string $host): void
{
    $_ENV['WP_REDIS_HOST'] = $host;
    $_SERVER['WP_REDIS_HOST'] = $host;
    putenv('WP_REDIS_HOST='.$host);
}

it('warns when WordPress has a Redis and Acorn does not', function () {
    withRedisHost('cache.internal');
    config(['cache.default' => 'file']);

    expect(renderCacheNotice())
        ->toContain('Admin caching is per-container')
        ->toContain('notice-warning')
        // esc_html turns the quotes into entities; asserting the raw string would pass only if
        // the driver name were being printed unescaped.
        ->toContain('&quot;file&quot; driver')
        // It has to describe the current state, not a past one. "Acorn could not reach it" sent
        // somebody after a credential that was never the problem, and "the package is not
        // installed" stopped being true the moment it was.
        ->toContain('CACHE_STORE=redis fixes it and now works')
        ->and(renderCacheNotice())->not->toContain('could not reach')
        ->and(renderCacheNotice())->not->toContain('is not installed');
});

it('says nothing once Acorn is on the same Redis', function () {
    withRedisHost('cache.internal');
    config(['cache.default' => 'redis']);

    expect(renderCacheNotice())->toBe('');
});

it('says nothing where there is no Redis at all', function () {
    // Local development. Not degraded, just small — a warning here is pure noise.
    config(['cache.default' => 'file']);

    expect(renderCacheNotice())->toBe('');
});

it('reads the environment rather than a stored flag, so it cannot outlive the fault', function () {
    withRedisHost('cache.internal');
    config(['cache.default' => 'file']);
    expect(renderCacheNotice())->not->toBe('');

    // The next deploy whose probe succeeds simply stops rendering it. No dismissal, no cleanup.
    config(['cache.default' => 'redis']);
    expect(renderCacheNotice())->toBe('');
});

/*
 * The binding this whole outage came down to is deliberately not asserted here.
 *
 * Acorn registers no Redis provider. With illuminate/redis absent, nothing held `redis` in the
 * container, Laravel resolved the literal string as a class name -- PHP class names being
 * case-insensitive -- and handed phpredis' own `Redis` to a store demanding a Factory. A
 * TypeError on every admin screen using the Cache facade, on a deploy that went green.
 *
 * Proving that needs a booted application: a real container to resolve `redis` from and a real
 * CacheManager to build the store with. This suite runs on tests/stubs.php with no container at
 * all, and standing one up here would test the scaffolding rather than the fix.
 *
 * Verified against the running application instead:
 *
 *   wp eval 'echo get_class(app("redis"));'          -> Illuminate\Redis\RedisManager
 *   CACHE_STORE=redis wp eval '... getStore() ...'    -> Illuminate\Cache\RedisStore
 *
 * The regression that matters is caught anyway, one level up: an admin screen has to render on
 * staging before a release is tagged. Every deploy on 2026-09-20 was green while the admin was
 * down, which is the actual lesson.
 */
