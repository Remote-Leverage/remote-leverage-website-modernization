<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

/**
 * Says so in wp-admin when Acorn's cache has quietly fallen back to the file driver.
 *
 * ## Why this is worth a notice
 *
 * On ECS the file driver is not slow, it is wrong. Every task keeps a private copy on its own
 * container filesystem, so the hourly job warms the marketing snapshot on whichever task runs
 * cron and a dashboard request served by any other task shows whatever that task last computed.
 * On 2026-09-20 the Slack card read correctly at 07:00 beside a widget still showing 06:56, on
 * the same site at the same moment, and the only way to tell was to notice the timestamps.
 *
 * Nothing fails, which is the problem. There is no error, no log line at request time, and every
 * figure on the page is a real figure that was true when some container computed it.
 *
 * ## What it detects
 *
 * WordPress having a Redis while Acorn does not. `WP_REDIS_HOST` is what the object cache drop-in
 * reads; `cache.default` is what Acorn resolved after docker/entrypoint.sh probed that same Redis
 * with Acorn's own client. The two disagreeing means the probe failed — wrong credentials for the
 * second client, a TLS endpoint, or a security group — and the entrypoint deliberately kept the
 * file driver rather than letting a thrown Cache::get take the admin down.
 *
 * An environment with no Redis at all is not degraded, it is simply small, and says nothing.
 */
class CacheHealthNotice
{
    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_notices', [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options') || ! $this->isDegraded()) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p>%s</p></div>',
            esc_html('Admin caching is running per-container.'),
            esc_html(sprintf(
                'WordPress is using Redis but Acorn could not reach it, so it fell back to the "%s" '.
                'driver. Each task now caches on its own filesystem.',
                (string) config('cache.default', 'file'),
            )),
            esc_html(
                'Dashboard figures may differ between page loads and disagree with the Slack card, '.
                'because whichever container serves you shows what it last computed. Nothing is lost '.
                'and no figure is invented. The container log line beginning "entrypoint: WARNING - '.
                'Redis is configured for WordPress" says which host was refused.'
            ),
        );
    }

    /**
     * Redis for WordPress, something else for Acorn.
     *
     * Read from the environment rather than from a stored flag, so the notice cannot outlive the
     * condition: the next deploy that succeeds in probing Redis simply stops rendering it.
     */
    private function isDegraded(): bool
    {
        $wordpressHasRedis = function_exists('env') ? (string) env('WP_REDIS_HOST') : '';

        return $wordpressHasRedis !== '' && config('cache.default') !== 'redis';
    }
}
