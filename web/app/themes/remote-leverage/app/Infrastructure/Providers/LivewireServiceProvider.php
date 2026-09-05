<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

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
            // Livewire boot hooks
        }
    }
}
