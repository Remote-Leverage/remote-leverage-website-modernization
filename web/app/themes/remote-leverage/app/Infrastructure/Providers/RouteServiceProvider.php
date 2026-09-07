<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->routes(function () {
            $basePath = dirname(__DIR__, 3);

            Route::middleware('api')
                ->prefix('api')
                ->group($basePath.'/routes/api.php');

            Route::middleware('web')
                ->group($basePath.'/routes/web.php');
        });
    }
}
