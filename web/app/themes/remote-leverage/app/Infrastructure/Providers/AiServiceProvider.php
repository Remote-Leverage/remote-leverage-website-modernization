<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Ai\Commands\GrantInsightsCapabilityCommand;
use App\Ai\Commands\ProvisionContentAgentCommand;
use App\Ai\Provisioning\ContentAgentProvisioner;
use Illuminate\Support\ServiceProvider;
use WP\MCP\Core\McpAdapter;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GrantInsightsCapabilityCommand::class,
                ProvisionContentAgentCommand::class,
            ]);
        }

        $this->app->singleton(ContentAgentProvisioner::class);
    }

    /**
     * McpAdapter::instance() is idempotent, and since config/application.php
     * defines WP_MCP_AUTOLOAD the plugin now bootstraps itself normally — so
     * this is a belt-and-braces call for the case where that constant is
     * absent (a non-Bedrock context, or a config that has drifted), not the
     * primary path. Without either, no MCP server exists and every ability in
     * config/ai-wordpress.php is unreachable over MCP.
     */
    public function boot(): void
    {
        if (class_exists(McpAdapter::class)) {
            McpAdapter::instance();
        }
    }
}
