<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use WP\MCP\Core\McpAdapter;

class AiServiceProvider extends ServiceProvider
{
    /**
     * wordpress/mcp-adapter's own self-bootstrap (mcp-adapter.php) checks
     * class_exists(Plugin::class) inline in the plugin-loading loop, before
     * its own autoloader has necessarily resolved that class — so
     * Plugin::instance() (and therefore McpAdapter::instance()) never
     * actually runs on its own. This mirrors the plugin's documented
     * host-integration snippet (see docs/ai-mcp-and-sync.md), calling
     * McpAdapter::instance() ourselves once themes have loaded (still
     * before `init`, which is when it registers its own hooks).
     */
    public function boot(): void
    {
        if (class_exists(McpAdapter::class)) {
            McpAdapter::instance();
        }
    }
}
