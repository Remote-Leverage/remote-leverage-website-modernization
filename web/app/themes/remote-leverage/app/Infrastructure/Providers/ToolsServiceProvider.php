<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Tools\Http\JobWidgetRestRoutes;
use App\Domains\Tools\Services\OpenAiProxy;
use App\Domains\Tools\Services\OpenAiProxyGuard;
use Illuminate\Support\ServiceProvider;

/**
 * The Elementor-era browser tools: the vastore5 job description generator and the seven other
 * snapshot pages that share its OpenAI proxy. See config/job-widget.php.
 */
class ToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAiProxyGuard::class, fn () => new OpenAiProxyGuard);
        $this->app->singleton(OpenAiProxy::class, fn ($app) => new OpenAiProxy(
            $app->make(OpenAiProxyGuard::class),
        ));
        $this->app->singleton(JobWidgetRestRoutes::class, fn ($app) => new JobWidgetRestRoutes(
            $app->make(OpenAiProxyGuard::class),
            $app->make(OpenAiProxy::class),
        ));
    }

    /**
     * The routes gate themselves on `rest_api_init`, so this is unconditional — an environment
     * with no OPENAI_API_KEY registers no route and 404s, which is the behaviour v2 has today.
     */
    public function boot(): void
    {
        $this->app->make(JobWidgetRestRoutes::class)->register();
    }
}
