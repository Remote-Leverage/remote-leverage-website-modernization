<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Referral\Events\PartnerRegistered;
use App\Domains\Referral\Events\ReferralRecorded;
use App\Domains\Referral\Listeners\DispatchReferralWebhook;
use App\Domains\Referral\Listeners\SendPartnerWelcomeEmail;
use App\Domains\Referral\Repositories\EloquentPartnerRepository;
use App\Domains\Referral\Repositories\PartnerRepositoryInterface;
use App\Domains\Referral\Services\AttributionEngine;
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
        $this->app->bind(PartnerRepositoryInterface::class, EloquentPartnerRepository::class);

        $this->app->singleton(StripeConnectGateway::class, fn () => new StripeConnectGateway);
        $this->app->singleton(AttributionEngine::class, fn () => new AttributionEngine);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(PartnerRegistered::class, SendPartnerWelcomeEmail::class);
        Event::listen(ReferralRecorded::class, DispatchReferralWebhook::class);
    }
}
