<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Referral\Events\PayoutCompleted;
use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Events\ReferrerRegistered;
use App\Domains\Referral\Listeners\DispatchReferralWebhook;
use App\Domains\Referral\Listeners\HandleReferralEventsForSlack;
use App\Domains\Referral\Listeners\SendReferrerWelcomeEmail;
use App\Domains\Referral\Repositories\EloquentReferrerRepository;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use App\Domains\Referral\Services\AttributionEngine;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Domains\Referral\Services\StripeConnectGateway;
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
        $this->app->singleton(HandleReferralEventsForSlack::class);
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
    }
}
