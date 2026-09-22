<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Domains\Lead\Services\AttributionCollector;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Sentry\Laravel\Integration;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\configureScope;

/**
 * Makes Sentry actually receive the things that go wrong.
 *
 * ## Why this is needed at all
 *
 * `sentry/sentry-laravel` is installed and the DSN is configured, and until 2026-09-20 that looked
 * like working error tracking. It was not. Its service provider *removes* the SDK's own
 * ErrorListener and ExceptionListener integrations — see ServiceProvider::configureIntegrations(),
 * which says so in a comment — on the assumption that the application's `ExceptionHandler` will
 * forward to Sentry instead. In a stock Laravel app you wire that in `bootstrap/app.php`. Acorn
 * has its own bootstrapper and nothing here wired it, so:
 *
 *   - explicit `captureException()` calls worked, which is why a test ping proved "Sentry works"
 *   - every unhandled exception reached nobody
 *
 * A TypeError took both environments' admin down that morning and Sentry recorded nothing.
 *
 * ## What it now catches
 *
 * 1. Unhandled exceptions, via a `reportable` callback on the handler. The callback deliberately
 *    returns nothing, so the normal log line still happens — Sentry is an addition, not a
 *    replacement for CloudWatch.
 * 2. PHP fatals that never become exceptions at all: E_ERROR, E_PARSE and friends. These bypass
 *    every exception handler by definition, and the shutdown function flushes explicitly because
 *    the process is about to end and a queued event would die with it.
 * 3. `Log::error()` and above, through a Sentry log channel. The booking wizard's own docblock
 *    notes that swallowed-but-logged failures went "to CloudWatch and nowhere else"; that was the
 *    gap that hid a dead funnel until someone happened to read the logs.
 * 4. An identity on the scope: the logged-in WordPress user (`id` + `email`) when there is one,
 *    otherwise the visitor's own attribution — nearly every request on a public marketing site.
 *    `config/sentry.php` keeps `send_default_pii` off deliberately, since that flag would start
 *    attaching every anonymous visitor's IP to their errors; setting an identity explicitly, from
 *    data this site already collects for the same visitor's lead record, gets the attribution
 *    value without that trade.
 *
 * ## Not in local
 *
 * The DSN is in the repo's `.env.example` and most developers have it set, so capturing here would
 * bury real incidents under whatever somebody is mid-refactor on. Overridable with
 * SENTRY_CAPTURE_LOCALLY=true when testing this class itself.
 */
final class SentryReporting
{
    private static bool $registered = false;

    /** Reset the once-per-process guard. For tests, which are many boots in one process. */
    public static function forgetRegistration(): void
    {
        self::$registered = false;
    }

    public function register(): void
    {
        if (self::$registered || ! app()->bound('sentry') || ! $this->enabled()) {
            return;
        }

        self::$registered = true;

        $this->tagEnvironment();
        $this->identifyUser();
        $this->captureUnhandledExceptions();
        $this->captureFatalErrors();
        $this->captureErrorLogs();
    }

    /**
     * Whether anything should be sent from this environment.
     */
    private function enabled(): bool
    {
        if ((string) config('sentry.dsn', '') === '') {
            return false;
        }

        if (filter_var(env('SENTRY_CAPTURE_LOCALLY', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $environment = function_exists('wp_get_environment_type')
            ? (string) \wp_get_environment_type()
            : 'production';

        return ! in_array($environment, ['local', 'development'], true);
    }

    /**
     * Tag every event with the environment, so staging noise is filterable from production
     * incidents. `release` already comes from APP_VERSION, which the Dockerfile writes from the
     * build's commit SHA.
     */
    private function tagEnvironment(): void
    {
        if ((string) config('sentry.environment', '') !== '') {
            return;
        }

        $environment = function_exists('wp_get_environment_type')
            ? (string) \wp_get_environment_type()
            : 'production';

        try {
            configureScope(static function (Scope $scope) use ($environment): void {
                $scope->setTag('wp_env', $environment);
            });
        } catch (Throwable) {
            // A tag is a nicety. Never let it be the reason reporting fails to install.
        }
    }

    /**
     * Attach an identity to the scope: a logged-in WordPress user first, otherwise whatever
     * attribution the visitor arrived with.
     */
    private function identifyUser(): void
    {
        if ($this->identifyLoggedInUser()) {
            return;
        }

        $this->identifyByAttribution();
    }

    /**
     * Attach the logged-in WordPress user to the scope, guest visitors excluded.
     *
     * Guarded on `get_current_user_id()` rather than just `is_user_logged_in()` — a logged-out
     * visitor's `wp_get_current_user()` still returns a `WP_User` with id `0` and empty fields,
     * and setting that as the Sentry user would tag every anonymous error as "user 0" instead of
     * leaving it correctly unidentified.
     *
     * @return bool Whether a user was actually identified, so `identifyUser()` knows not to also
     *              fall back to attribution — a staff member testing via a campaign link is
     *              identified by who they are, not by the link.
     */
    private function identifyLoggedInUser(): bool
    {
        if (! function_exists('is_user_logged_in') || ! \is_user_logged_in()) {
            return false;
        }

        $id = function_exists('get_current_user_id') ? (int) \get_current_user_id() : 0;

        if ($id < 1) {
            return false;
        }

        $email = function_exists('wp_get_current_user') ? (string) \wp_get_current_user()->user_email : '';

        try {
            configureScope(static function (Scope $scope) use ($id, $email): void {
                $scope->setUser([
                    'id' => $id,
                    'email' => $email,
                ]);
            });
        } catch (Throwable) {
            // Identifying the user is a nicety. Never let it be the reason reporting fails to install.
        }

        return true;
    }

    /**
     * Fall back to identifying the visitor by their own attribution — the case for nearly all
     * traffic on a public marketing site, which is never logged in at all.
     *
     * `AttributionCollector` is the same class `CaptureLeadAction` reads for `rl_leads`, so "who
     * is this" means the same thing here as it does on a lead row. `device_id` (the first-party
     * `rl_vid` cookie) is the only thing that can recognise a returning visitor, so it becomes the
     * `id`; `utm_source`/`utm_medium`/`utm_campaign` ride along as plain fields on that same user
     * so an issue reads as "this campaign's traffic is crashing" without cross-referencing leads.
     *
     * `device_id` is set client-side (`TrackingHooks::injectVisitorCookie()`) and so is absent on
     * a visitor's very first request — exactly the request a fresh `utm_source` is most likely to
     * be on, which is why this still identifies by UTM alone rather than requiring both.
     */
    private function identifyByAttribution(): void
    {
        try {
            $named = (new AttributionCollector)->collect()['named'] ?? [];
        } catch (Throwable) {
            return;
        }

        $user = array_filter([
            'id' => $named['device_id'] ?? null,
            'utm_source' => $named['utm_source'] ?? null,
            'utm_medium' => $named['utm_medium'] ?? null,
            'utm_campaign' => $named['utm_campaign'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        if ($user === []) {
            return;
        }

        try {
            configureScope(static function (Scope $scope) use ($user): void {
                $scope->setUser($user);
            });
        } catch (Throwable) {
            // Identifying the visitor is a nicety. Never let it be the reason reporting fails to install.
        }
    }

    private function captureUnhandledExceptions(): void
    {
        // Bound-check rather than assume: provider boot order is not ours to rely on, and
        // resolving an unbound interface throws rather than returning null.
        if (! app()->bound(ExceptionHandler::class)) {
            return;
        }

        $handler = app(ExceptionHandler::class);

        if (! method_exists($handler, 'reportable')) {
            return;
        }

        $handler->reportable(static function (Throwable $e): void {
            Integration::captureUnhandledException($e);
        });
    }

    /**
     * Fatals that never become exceptions, and so never reach any handler.
     */
    private function captureFatalErrors(): void
    {
        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

            if (! in_array($error['type'], $fatal, true)) {
                return;
            }

            try {
                captureException(new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line'],
                ));

                // The process is ending; an unsent event dies with it.
                SentrySdk::getCurrentHub()->getClient()?->flush();
            } catch (Throwable) {
                // Reporting must never be the thing that breaks the response.
            }
        });
    }

    /**
     * Route Log::error() and above to Sentry as well as to the existing channels.
     *
     * Pushed into config rather than shipped as a `config/logging.php`, which would fork Acorn's
     * whole file to add six lines and then quietly drift from it.
     */
    private function captureErrorLogs(): void
    {
        /*
         * The channel already exists -- Sentry's own provider defines it as `{driver: sentry}`
         * with no level, which Monolog reads as DEBUG. Left alone and added to the stack, that
         * would ship every debug line in the application to Sentry and exhaust the quota in an
         * afternoon. So the level is set on whatever is there rather than the channel being
         * redefined or skipped.
         */
        config([
            'logging.channels.sentry' => [
                ...(array) config('logging.channels.sentry', []),
                'driver' => 'sentry',
                'level' => (string) env('SENTRY_LOG_LEVEL', 'error'),
                'bubble' => true,
            ],
        ]);

        /*
         * Appending to the stack is the part that actually routes anything. Defining a channel
         * nothing references does nothing at all, which is what the first version of this method
         * did: it saw the channel already present and returned before wiring it up.
         */
        $stack = (array) config('logging.channels.stack.channels', []);

        if (! in_array('sentry', $stack, true)) {
            config(['logging.channels.stack.channels' => [...$stack, 'sentry']]);
        }
    }
}
