<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Referral\Actions\SyncHubSpotLifecycleAction;
use App\Domains\Referral\Commands\SyncHubSpotLifecycleCommand;
use App\Domains\Referral\Events\PayoutCompleted;
use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Events\ReferrerRegistered;
use App\Domains\Referral\Listeners\DispatchReferralWebhook;
use App\Domains\Referral\Listeners\HandleReferralEventsForSlack;
use App\Domains\Referral\Listeners\SendReferrerWelcomeEmail;
use App\Domains\Referral\Repositories\EloquentReferrerRepository;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use App\Domains\Referral\Services\AttributionEngine;
use App\Domains\Referral\Services\ReferralLeadMatcher;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Domains\Referral\Services\ReferralVisitorContext;
use App\Domains\Referral\Services\StripeConnectGateway;
use App\Domains\Referral\Support\ReferralWelcomeNotice;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ReferralServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReferrerRepositoryInterface::class, EloquentReferrerRepository::class);

        $this->app->singleton(StripeConnectGateway::class, fn () => new StripeConnectGateway);
        $this->app->singleton(AttributionEngine::class, fn () => new AttributionEngine);
        $this->app->singleton(ReferralSettingsService::class, fn () => new ReferralSettingsService);
        $this->app->singleton(ReferralLeadMatcher::class, fn () => new ReferralLeadMatcher);

        // Request-scoped: resolved once, then reused by the welcome notice and by lead capture,
        // so one page view never costs two referrer lookups or records two clicks.
        $this->app->singleton(ReferralVisitorContext::class);
        $this->app->singleton(ReferralWelcomeNotice::class);
        $this->app->singleton(HandleReferralEventsForSlack::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncHubSpotLifecycleCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ReferrerRegistered::class, SendReferrerWelcomeEmail::class);
        Event::listen(ReferralRecorded::class, DispatchReferralWebhook::class);

        /*
         * Slack. Deferred to after the response for the same reason the lead alerts are (see
         * LeadServiceProvider): with no queue configured these run synchronously, and a
         * referral is recorded inside a visitor's request. The closures must be `static` and
         * must not touch `$this` — serializable-closure would otherwise try to serialize this
         * provider and the whole container behind it, which fails silently during termination
         * and drops the notification entirely.
         */
        Event::listen(ReferrerRegistered::class, function (ReferrerRegistered $event) {
            dispatch(static fn () => app(HandleReferralEventsForSlack::class)->handleReferrerRegistered($event))->afterResponse();
        });

        Event::listen(ReferralRecorded::class, function (ReferralRecorded $event) {
            dispatch(static fn () => app(HandleReferralEventsForSlack::class)->handleReferralRecorded($event))->afterResponse();
        });

        Event::listen(PayoutCompleted::class, function (PayoutCompleted $event) {
            dispatch(static fn () => app(HandleReferralEventsForSlack::class)->handlePayoutCompleted($event))->afterResponse();
        });

        $this->scheduleHubSpotLifecycleSync();
        $this->captureReferralVisits();
    }

    /**
     * Resolve `?via=` on every front-end request: record the click, set the attribution cookie.
     *
     * On `template_redirect` rather than as Laravel middleware. `ReferralAttributionMiddleware`
     * used to hold this logic and was **registered nowhere**, so none of it ever ran — and it
     * could not have worked where it mattered even if it had been registered, because
     * RouteServiceProvider only applies middleware to `routes/web.php` and `routes/api.php`
     * while `/hire-va-4/`, the sole destination of every referral link, is rendered by
     * WordPress. The result was that no referral click was ever recorded from real traffic and
     * the attribution cookie was never set; attribution survived only for as long as `?via=`
     * stayed in the address bar.
     *
     * `template_redirect` fires for every front-end request and still before any output, which
     * is what lets the cookie be set. Admin, AJAX, REST and CLI are all excluded — a referral
     * link never lands on any of them, and resolving there would record clicks for an
     * administrator browsing the dashboard.
     */
    protected function captureReferralVisits(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('template_redirect', function () {
            if ((function_exists('is_admin') && is_admin()) || (defined('WP_CLI') && WP_CLI)) {
                return;
            }

            if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
                return;
            }

            if (defined('REST_REQUEST') && REST_REQUEST) {
                return;
            }

            $this->app->make(ReferralVisitorContext::class)->resolve();
        }, 1);
    }

    /**
     * Hourly WP-Cron: pull HubSpot lifecycle stages for leads with an open referral.
     *
     * Hourly rather than more often because the thing being measured moves over days — the
     * lead lifecycle runs about four — so a tighter interval would spend HubSpot's rate limit
     * to tell the dashboard the same thing it already said.
     *
     * This is the polling half of a deliberately two-part design. When the portal is
     * configured to send webhooks, the receiver calls
     * `SyncHubSpotLifecycleAction::applyStage()` and this schedule stays on as reconciliation
     * for anything a webhook drops — a dropped delivery is otherwise invisible, and its cost
     * is a referrer who is never paid.
     */
    protected function scheduleHubSpotLifecycleSync(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('init', function () {
            if (! wp_next_scheduled('rl_sync_hubspot_lifecycle')) {
                wp_schedule_event(time(), 'hourly', 'rl_sync_hubspot_lifecycle');
            }
        });

        add_action('rl_sync_hubspot_lifecycle', function () {
            $this->app->make(SyncHubSpotLifecycleAction::class)->execute();
        });
    }
}
