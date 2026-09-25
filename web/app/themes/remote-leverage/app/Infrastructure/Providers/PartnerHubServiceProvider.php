<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\PartnerHub\Events\PartnershipProspectSubmitted;
use App\Domains\PartnerHub\Listeners\HandlePartnershipProspectForSlack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * The PartnerHub domain's events.
 *
 * The directory itself (the `rl_partner` post type, its admin columns and the Prospects screen)
 * is wired in DomainServiceProvider alongside every other admin screen; this holds only what
 * listens to the domain, matching ReferralServiceProvider.
 */
class PartnerHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HandlePartnershipProspectForSlack::class);
    }

    public function boot(): void
    {
        /*
         * Slack, after the response — the visitor is waiting on the calendar, and a Slack round
         * trip is not what they should wait for. Static closure with no `$this`, for the reason
         * ReferralServiceProvider gives: serializable-closure would otherwise try to serialize
         * this provider and the container behind it, fail during termination, and drop the post.
         */
        Event::listen(PartnershipProspectSubmitted::class, function (PartnershipProspectSubmitted $event) {
            dispatch(static fn () => app(HandlePartnershipProspectForSlack::class)->handle($event))->afterResponse();
        });
    }
}
