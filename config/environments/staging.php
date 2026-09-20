<?php

/**
 * Configuration overrides for WP_ENV === 'staging'
 */

use Roots\WPConfig\Config;

use function Env\env;

/**
 * Staging should stay as close to production as possible, with one deliberate exception: it has
 * to say what went wrong.
 *
 * Staging exists to catch what CI cannot. CI never opens an admin screen, so a fatal in a
 * dashboard widget or a settings page deploys green and is discovered by a person clicking. With
 * the fatal error handler on, that person sees "There has been a critical error on this website"
 * and nothing else -- no file, no line, no trace -- and the error log lives in CloudWatch, which
 * the people who test staging cannot read. On 2026-09-20 that turned a one-line diagnosis into
 * two wrong guesses and a production revert.
 *
 * So the handler is off and errors are displayed. The cost is that a fatal on staging is ugly
 * rather than tidy, which is the correct trade for an environment nobody outside the company
 * sees: DISALLOW_INDEXING is set below and the site is not public.
 *
 * Production keeps the handler and shows nobody anything, as it must.
 */
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', true);
Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? true);
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);

ini_set('display_errors', '1');

/*
 * Buffer the response so the error page can actually be sent.
 *
 * Acorn renders a full exception page -- type, message, file, line, stack -- and its debug flag is
 * `WP_DEBUG && WP_DEBUG_DISPLAY`, both true above. It still could not show one: by the time a
 * fatal happens in an admin screen, _wp_admin_html_begin() has already echoed the doctype, so
 * Symfony's Response::sendHeaders() hits "headers already sent" and *that* becomes the error.
 * The result is a stack trace about the error page rather than about the fault, which is what
 * 2026-09-20 spent the morning reading.
 *
 * With an output buffer open, headers_sent() is false when Acorn renders, so the real page comes
 * through. Staging only, and it changes nothing about how the application behaves -- just whether
 * it can tell you what happened.
 */
ob_start();

/*
 * Acorn's cache on the same Redis WordPress uses. Staging only, deliberately.
 *
 * The task definition sets WP_REDIS_* for the object cache drop-in and nothing for Acorn, whose
 * Cache facade reads CACHE_STORE, REDIS_HOST and REDIS_PASSWORD. Unset, Acorn uses the file
 * driver, which on ECS means every task caches on its own filesystem: the hourly job warms the
 * marketing snapshot on whichever task runs cron and a dashboard request served by any other task
 * shows what that task last computed. On 2026-09-20 the Slack card read correctly at 07:00 beside
 * a widget still showing 06:56.
 *
 * ## Why here and not in the entrypoint or a secret
 *
 * An entrypoint that derived this applied everywhere, which is how it reached production twice in
 * one morning. A GitHub secret would work but lives outside the repo, where nobody reading this
 * file would find it. In here it is staging-only by construction and visible next to the reason.
 *
 * ## Why staging only
 *
 * Acorn's cache has no graceful mode, and LiveCallAvailabilityRouter, CalendlyTokenPool and
 * CalendlyClient read through it without catching -- all on the public booking path. The file
 * driver degrades to a wrong dashboard; Redis degrades to no bookings. Staging is where that
 * trade is worth taking to find out, and production is not.
 *
 * Requires illuminate/redis, added 2026-09-20. Without it this throws a TypeError on every admin
 * screen rather than switching the store -- see .env.example.
 */
if (env('WP_REDIS_HOST') && ! env('CACHE_STORE')) {
    $rlRedisHost = (string) env('WP_REDIS_HOST');

    // phpredis takes TLS as a host prefix; Acorn's redis config has no scheme key to set.
    if ((env('WP_REDIS_SCHEME') ?: 'tcp') === 'tls') {
        $rlRedisHost = 'tls://'.preg_replace('#^tls://#', '', $rlRedisHost);
    }

    /*
     * Written to all three, because Laravel's env() reads through a repository whose adapters
     * differ by build -- putenv() alone is not reliably visible to it.
     */
    $rlPutEnv = static function (string $key, string $value): void {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    };

    $rlPutEnv('CACHE_STORE', 'redis');
    $rlPutEnv('REDIS_HOST', $rlRedisHost);
    $rlPutEnv('REDIS_PORT', (string) (env('WP_REDIS_PORT') ?: 6379));

    // Laravel's cache sits on REDIS_CACHE_DB (1); the object cache is on WP_REDIS_DATABASE (0).
    // One server, two databases, no collision.
    $rlPutEnv('REDIS_CACHE_DB', (string) (env('REDIS_CACHE_DB') ?: 1));

    if (env('WP_REDIS_PASSWORD')) {
        $rlPutEnv('REDIS_PASSWORD', (string) env('WP_REDIS_PASSWORD'));
    }

    unset($rlRedisHost, $rlPutEnv);
}

/*
 * SCRIPT_DEBUG and SAVEQUERIES are deliberately *not* copied from development. They change what
 * is served and how queries run, which is exactly the drift from production that staging exists
 * to avoid. Only the reporting of errors changes here, not the behaviour that produces them.
 */
Config::define('DISALLOW_INDEXING', true);
