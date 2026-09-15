<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\SyncCapability;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * REST-only (no meta.mcp.public) — invoked exclusively by `wp rl:sync:settings`.
 * Rejects any key not on the config/rl-sync.php whitelist rather than trusting
 * the caller's payload, since this writes directly to wp_options.
 */
class ImportSyncableSettingsAbility extends Ability
{
    public function label(): string
    {
        return 'Import Syncable Settings';
    }

    public function description(): string
    {
        return 'Writes the given key/value pairs into wp_options, restricted to the whitelist in '.
            'config/rl-sync.php. Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $allowed = config('rl-sync.options', []);
        $values = $input['values'] ?? [];
        $updated = [];
        $rejected = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                $rejected[] = $key;

                continue;
            }

            update_option($key, $value);
            $updated[] = $key;
        }

        $flushed = $this->flushRewriteRulesIfNeeded($updated);

        return ['updated' => $updated, 'rejected' => $rejected, 'flushed_rewrite_rules' => $flushed];
    }

    /**
     * Options that change how URLs are parsed, not merely how they are built.
     *
     * @var array<int, string>
     */
    private const REWRITE_AFFECTING = [
        'permalink_structure',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
        'category_base',
        'tag_base',
    ];

    /**
     * Regenerate the rewrite rules when a structural option has just changed.
     *
     * WordPress derives permalinks from the option on every request but matches
     * incoming URLs against rules cached in wp_options, and only rebuilds those
     * when something asks it to. Writing permalink_structure alone therefore
     * leaves a site whose pages link to /blog/<slug>/ while every one of those
     * URLs 404s, and whose old URLs still resolve — which is worse than not
     * having synced it at all, and exactly what staging did on 2026-09-15.
     *
     * Soft flush: it rewrites the cached rules, and does not try to write a
     * .htaccess this environment does not use.
     *
     * @param  array<int, string>  $updated
     */
    private function flushRewriteRulesIfNeeded(array $updated): bool
    {
        if (array_intersect($updated, self::REWRITE_AFFECTING) === []) {
            return false;
        }

        if (! function_exists('flush_rewrite_rules')) {
            return false;
        }

        flush_rewrite_rules(false);

        return true;
    }

    public function permission(): bool|WP_Error
    {
        if (! SyncCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.SyncCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'values' => [
                    'type' => 'object',
                    'description' => 'Map of wp_options key to value. Keys not in the config/rl-sync.php '.
                        'whitelist are rejected rather than written.',
                ],
            ],
            'required' => ['values'],
        ];
    }

    public function category(): ?string
    {
        return 'site';
    }

    /**
     * See ExportSyncableSettingsAbility::meta() — show_in_rest (not `public`)
     * is what unlocks the REST run endpoint on WordPress 7.1+.
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
