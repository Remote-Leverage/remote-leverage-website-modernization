<?php

declare(strict_types=1);

namespace App\Domains\Sync;

/**
 * A capability distinct from edit_pages/publish_pages (used by the MCP
 * content-agent user) and manage_options (used by every other wp-admin
 * screen in this theme). Only the dedicated "sync-service" application-
 * password user should ever hold this — see docs/ai-mcp-and-sync.md.
 */
final class SyncCapability
{
    public const NAME = 'rl_manage_ai_sync';

    public static function currentUserCan(): bool
    {
        return current_user_can(self::NAME);
    }
}
