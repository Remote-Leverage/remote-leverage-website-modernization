<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Gateways\PostHogClient;
use App\Domains\Tracking\Listeners\HandleLeadCreatedForTracking;
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
        $this->app->singleton(HandleLeadCreatedForTracking::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(TrackingHooks::class)->register();

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
