<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Livewire\Blog\GuideIndexFilter;
use App\Application\Livewire\Booking\MultistepBookingWizard;
use App\Application\Livewire\Partner\PartnerDirectoryGrid;
use App\Application\Livewire\Partner\PartnerPortalDashboard;
use App\Application\Livewire\Partner\PartnerRegistrationForm;
use App\Application\Livewire\Scheduling\InstantLiveCallButton;
use App\Application\Livewire\Utilities\EmailSignatureGenerator;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireServiceProvider extends ServiceProvider
{
    /**
     * Register Livewire component discovery and namespaces.
     */
    public function register(): void
    {
        config([
            'livewire.class_namespace' => 'App\\Application\\Livewire',
            'livewire.view_path' => resource_path('views/livewire'),
        ]);
    }

    /**
     * Bootstrap Livewire components if Livewire is installed.
     */
    public function boot(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('booking.multistep-booking-wizard', MultistepBookingWizard::class);
            Livewire::component('multistep-booking-wizard', MultistepBookingWizard::class);

            Livewire::component('scheduling.instant-live-call-button', InstantLiveCallButton::class);
            Livewire::component('instant-live-call-button', InstantLiveCallButton::class);

            Livewire::component('partner.partner-portal-dashboard', PartnerPortalDashboard::class);
            Livewire::component('partner-portal-dashboard', PartnerPortalDashboard::class);

            Livewire::component('partner.partner-registration-form', PartnerRegistrationForm::class);
            Livewire::component('partner-registration-form', PartnerRegistrationForm::class);

            Livewire::component('partner.partner-directory-grid', PartnerDirectoryGrid::class);
            Livewire::component('partner-directory-grid', PartnerDirectoryGrid::class);

            Livewire::component('utilities.email-signature-generator', EmailSignatureGenerator::class);
            Livewire::component('email-signature-generator', EmailSignatureGenerator::class);

            Livewire::component('blog.guide-index-filter', GuideIndexFilter::class);
            Livewire::component('guide-index-filter', GuideIndexFilter::class);
        }
    }
}
