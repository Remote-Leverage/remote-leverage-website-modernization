<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

class LivewireServiceProvider extends ServiceProvider
{
    /**
     * Register Livewire component discovery and namespaces.
     */
    public function register(): void
    {
        // Reserved for Livewire 4 component mappings and directives
    }

    /**
     * Bootstrap Livewire components if Livewire is installed.
     */
    public function boot(): void
    {
        if (class_exists('Livewire\\Livewire')) {
            // Register components namespace: App\Application\Livewire
        }
    }
}
