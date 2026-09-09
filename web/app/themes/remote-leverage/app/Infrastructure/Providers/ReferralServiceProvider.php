<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Events\ReferrerRegistered;
use App\Domains\Referral\Listeners\DispatchReferralWebhook;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ReferrerRegistered::class, SendReferrerWelcomeEmail::class);
        Event::listen(ReferralRecorded::class, DispatchReferralWebhook::class);
    }
}
