<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Gateways\PostHogClient;
use App\Domains\Tracking\Listeners\HandleLeadCreatedForTracking;
use App\Infrastructure\WordPress\Hooks\MarketingPixelHooks;
use App\Infrastructure\WordPress\Hooks\SiteKitHooks;
use App\Infrastructure\WordPress\Hooks\TrackingHooks;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CustomerIOClient::class, fn () => new CustomerIOClient);
        $this->app->singleton(PostHogClient::class, fn () => new PostHogClient);
        $this->app->singleton(TrackingHooks::class, fn () => new TrackingHooks);
        $this->app->singleton(SiteKitHooks::class, fn () => new SiteKitHooks);
        $this->app->singleton(MarketingPixelHooks::class, fn () => new MarketingPixelHooks);
        $this->app->singleton(HandleLeadCreatedForTracking::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(TrackingHooks::class)->register();

        /*
         * Registered after TrackingHooks so the GTM containers sit behind the visitor cookie and
         * the PostHog snippet in the head. See SiteKitHooks::register() for why the order
         * matters, and config/site-kit.php for why the containers are emitted here at all
         * rather than by Site Kit.
         */
        $this->app->make(SiteKitHooks::class)->register();

        /*
         * Last of the three, so a GTM tag that ever starts doing one of these jobs defines
         * `fbq` (or `uetq`) first and this becomes the visible duplicate rather than the hidden
         * one. See MarketingPixelHooks for the ordering rationale and config/pixels.php for why
         * these are not simply tags in the container.
         */
        $this->app->make(MarketingPixelHooks::class)->register();

        // Deferred: Customer.io + PostHog are both live API calls that nothing
        // in the request depends on — see LeadServiceProvider::boot() for why
        // these were blocking the booking wizard's response. The inner
        // closure is `static` and uses the global app() helper (not
        // $this->app) so it never captures $this — see that same method's
        // comment for why: a non-static closure here previously crashed with
        // a fatal out-of-memory error while serializable-closure tried to
        // serialize the whole ServiceProvider/container graph, silently
        // dropping the deferred work every time.
        Event::listen(LeadCreated::class, function (LeadCreated $event) {
            dispatch(static fn () => app(HandleLeadCreatedForTracking::class)->handle($event))->afterResponse();
        });
    }
}
