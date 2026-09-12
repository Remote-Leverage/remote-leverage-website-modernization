<?php

declare(strict_types=1);

namespace App\Domains\Sync\Datasets;

use InvalidArgumentException;

/**
 * The catalogue of syncable datasets — see docs/environment-sync.md §2.
 *
 * Three tiers, and the distinction is the safety model:
 *
 *  - transferable      may move between environments (content, media, settings)
 *  - purge-only        real customer data or credentials; may be emptied on
 *                      either side but never copied (leads, referrals,
 *                      scheduling, users)
 *  - never synced      per-environment state that must not move or be purged
 *                      by this tool (migrations, comments)
 *
 * A dataset being listed here is not permission to transfer it: transferable()
 * is the only source the manifest accepts, and TransferManifest re-checks.
 */
final class DatasetRegistry
{
    public const CONTENT = 'content';

    public const MEDIA = 'media';

    public const SETTINGS = 'settings';

    public const LEADS = 'leads';

    public const REFERRALS = 'referrals';

    public const SCHEDULING = 'scheduling';

    public const USERS = 'users';

    /**
     * Tables this tool must never read, write, or truncate.
     *
     * wp_migrations is per-environment schema state — syncing it would make a
     * target claim migrations it has not run. Comments are excluded by product
     * decision: the site is not meant to support commenting at all.
     *
     * @var array<int, string>
     */
    private const NEVER_SYNCED = ['migrations', 'comments', 'commentmeta'];

    /**
     * @return array<string, Dataset>
     */
    public function all(): array
    {
        return [
            self::CONTENT => new Dataset(
                key: self::CONTENT,
                label: 'Content',
                description: 'Posts, pages, case studies, partners and their taxonomies. '
                    .'Attachments are excluded here and travel with Media instead.',
                tables: ['posts', 'postmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships'],
                transferable: true,
                defaultSelected: true,
                purgeable: false,
            ),
            self::MEDIA => new Dataset(
                key: self::MEDIA,
                label: 'Media',
                description: 'Attachment rows and the uploaded files they point at. '
                    .'Without this, synced content references files the target does not have.',
                tables: ['posts', 'postmeta'],
                transferable: true,
                defaultSelected: true,
                purgeable: false,
            ),
            self::SETTINGS => new Dataset(
                key: self::SETTINGS,
                label: 'Settings',
                description: 'Only the option keys whitelisted in config/rl-sync.php — '
                    .'never a wholesale wp_options copy.',
                tables: ['options'],
                transferable: true,
                defaultSelected: false,
                purgeable: false,
            ),
            self::LEADS => new Dataset(
                key: self::LEADS,
                label: 'Leads',
                description: 'Real enquiry data. Never copied between environments; '
                    .'can be emptied on either side.',
                tables: ['rl_leads', 'rl_lead_activity_logs'],
                transferable: false,
                defaultSelected: false,
                purgeable: true,
            ),
            self::REFERRALS => new Dataset(
                key: self::REFERRALS,
                label: 'Referrals',
                description: 'Referrers, referrals, clicks, rewards and payouts. '
                    .'Never copied between environments; can be emptied on either side.',
                tables: ['rl_referrers', 'rl_referrals', 'rl_referral_clicks', 'rl_referral_rewards', 'rl_payouts'],
                transferable: false,
                defaultSelected: false,
                purgeable: true,
            ),
            self::SCHEDULING => new Dataset(
                key: self::SCHEDULING,
                label: 'Scheduling',
                description: 'Live call sessions. Never copied between environments; '
                    .'can be emptied on either side.',
                tables: ['rl_live_call_sessions'],
                transferable: false,
                defaultSelected: false,
                purgeable: true,
            ),
            self::USERS => new Dataset(
                key: self::USERS,
                label: 'Users',
                description: 'Never transferred and never purged. Overwriting these would '
                    .'destroy the sync-service credential this tool authenticates with, '
                    .'and your own login on the target.',
                tables: ['users', 'usermeta'],
                transferable: false,
                defaultSelected: false,
                purgeable: false,
            ),
        ];
    }

    /**
     * The order datasets must be imported in.
     *
     * Media first, always. Content is rewritten against the attachment ID map
     * as it is written, so every attachment has to have landed — and every
     * collision been resolved — before the first post referencing one is
     * imported. Importing content first would rewrite against a map that is
     * still empty and leave the references pointing at the source's IDs.
     *
     * @param  array<int, string>  $datasets
     * @return array<int, string>
     */
    public function importOrder(array $datasets): array
    {
        $order = [self::MEDIA, self::CONTENT, self::SETTINGS];

        $ordered = array_values(array_filter($order, fn (string $k) => in_array($k, $datasets, true)));

        // Anything not in the known order keeps its caller-supplied position at
        // the end, so adding a dataset without updating this cannot drop it.
        return array_merge($ordered, array_values(array_diff($datasets, $order)));
    }

    public function get(string $key): Dataset
    {
        $all = $this->all();

        if (! isset($all[$key])) {
            throw new InvalidArgumentException("Unknown sync dataset: {$key}");
        }

        return $all[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * @return array<string, Dataset>
     */
    public function transferable(): array
    {
        return array_filter($this->all(), fn (Dataset $d) => $d->transferable);
    }

    /**
     * @return array<string, Dataset>
     */
    public function purgeable(): array
    {
        return array_filter($this->all(), fn (Dataset $d) => $d->purgeable);
    }

    /**
     * @return array<int, string>
     */
    public function defaultSelection(): array
    {
        return array_keys(array_filter($this->all(), fn (Dataset $d) => $d->defaultSelected));
    }

    /**
     * Unprefixed names of tables this tool must never touch.
     *
     * @return array<int, string>
     */
    public function neverSynced(): array
    {
        return self::NEVER_SYNCED;
    }

    public function isNeverSynced(string $unprefixedTable): bool
    {
        return in_array($unprefixedTable, self::NEVER_SYNCED, true);
    }
}
