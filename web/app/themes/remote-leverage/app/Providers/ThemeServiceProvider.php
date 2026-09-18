<?php

namespace App\Providers;

use App\Infrastructure\Providers\DomainServiceProvider;
use Roots\Acorn\Sage\SageServiceProvider;

class ThemeServiceProvider extends SageServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->app->register(DomainServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        $this->ensureApplicationTerminates();
    }

    /**
     * Run the container's terminating callbacks on requests Acorn does not route itself.
     *
     * Every deferred integration in this theme — Customer.io, Slack, the lead webhook, the
     * notification mail, PostHog — is queued with `dispatch(...)->afterResponse()` so a
     * third-party API can never sit on the response path. That registers a *terminating
     * callback*, and a terminating callback only runs if something calls `$app->terminate()`.
     *
     * Acorn calls it in exactly two places (`Bootable::registerRequestHandler`): the route it
     * handles itself, and the `wordpress` route when `handlesWordPressRequests()` is true. This
     * theme renders through WordPress' own template hierarchy, so an ordinary page view matches
     * neither and Acorn returns *before* registering its shutdown hook. The same early return
     * covers `/wp-admin`, `/wp-login.php`, `/wp-json` and anything ending in `.php`. On all of
     * those the container never terminates and every deferred job is discarded — no exception,
     * no log line, nothing in the response.
     *
     * Measured on 2026-09-18 with a probe on both paths: a front-end page render logged the
     * dispatch and never the closure; the Livewire update endpoint, which Acorn does route,
     * logged both. That is why the booking funnel's `form_loaded` has never reached anything.
     *
     * The guard is not optional. `Application::terminate()` walks `terminatingCallbacks`
     * without clearing them, so a second call re-runs all of them — a duplicate Slack alert, a
     * duplicate lead webhook, a duplicate analytics event. On the paths Acorn does handle it
     * terminates and then `exit()`s, which still unwinds into WordPress' `shutdown` hook, so
     * this callback would otherwise be that second call. The sentinel is registered here,
     * before any request-scoped callback, so it is the first one Acorn's terminate runs.
     */
    protected function ensureApplicationTerminates(): void
    {
        if ($this->app->runningInConsole() || ! function_exists('add_action')) {
            return;
        }

        $state = new \stdClass;
        $state->terminated = false;

        $this->app->terminating(static function () use ($state) {
            $state->terminated = true;
        });

        add_action('shutdown', function () use ($state) {
            if ($state->terminated) {
                return;
            }

            // Close the connection first, exactly as Acorn's own handler does. Without this the
            // visitor's browser waits on whatever the deferred work is calling, which is the
            // cost the `afterResponse()` was there to avoid in the first place.
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }

            $this->app->terminate();
        }, 999);
    }
}
