<?php

declare(strict_types=1);

use App\Infrastructure\WordPress\Admin\CacheHealthNotice;

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
        // It has to name the actual cause. "Acorn could not reach it" sent somebody looking for a
        // credential that was never the problem; the package is.
        ->toContain('illuminate/redis package is not installed')
        ->and(renderCacheNotice())->not->toContain('could not reach');
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
