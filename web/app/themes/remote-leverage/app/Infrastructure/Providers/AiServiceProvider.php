<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Ai\Commands\GrantInsightsCapabilityCommand;
use App\Ai\Commands\ProvisionContentAgentCommand;
use App\Ai\Provisioning\ContentAgentProvisioner;
use App\Infrastructure\WordPress\Admin\AiAccessAdmin;
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
        $this->app->singleton(AiAccessAdmin::class);
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

        // Settings → AI Access. Registered in admin only: it adds a menu page and
        // an admin_init handler, neither of which has anything to do on a front-end
        // request, and every entry point it exposes is gated on manage_options.
        if (is_admin()) {
            $this->app->make(AiAccessAdmin::class)->register();
        }
    }
}
