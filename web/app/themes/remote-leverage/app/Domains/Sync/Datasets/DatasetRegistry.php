<?php

declare(strict_types=1);

namespace App\Domains\Sync\Datasets;

use InvalidArgumentException;

/**
 * The catalogue of syncable datasets — see docs/environment-sync.md §2.
 *
 * Three tiers, and the distinction is the safety model:
 *
 *  - transferable      may move between environments (content, media, settings,
 *                      leads)
 *  - purge-only        real customer data or credentials; may be emptied on
 *                      either side but never copied (referrals, scheduling,
 *                      partnership prospects, users)
 *  - never synced      per-environment state that must not move or be purged
 *                      by this tool (migrations, comments)
 *
 * Leads is transferable *and* purgeable, and it is the only dataset that
 * travels as whole table rows rather than through the posts pipeline — see
 * Dataset::$transferTables. It carries real PII verbatim, deliberately: the
 * marketing cost alert compares BigQuery spend against this site's own lead
 * counts, and a non-production environment with warehouse data but no leads
 * makes every cross-check and every cost-per-lead figure meaningless.
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

    public const PARTNERSHIPS = 'partnerships';

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
                description: 'Real enquiry data with its identity profiles, copied verbatim — '
                    .'PII included — so a non-production environment can be cross-checked against '
                    .'the marketing warehouse. The target\'s lead tables are replaced, not merged.',
                /*
                 * Purge order, children before parents. `rl_lead_activity_logs.lead_id` is a real
                 * cascading foreign key, so deleting leads would take the logs anyway; the others
                 * are indexes rather than constraints, which means nothing stops a wrong order
                 * leaving orphans instead of erroring.
                 */
                tables: [
                    'rl_lead_activity_logs',
                    'rl_leads',
                    'rl_lead_identifiers',
                    'rl_lead_profiles',
                ],
                transferable: true,
                defaultSelected: false,
                purgeable: true,

                /*
                 * Load order, parents before children, and the identity tables travel with the
                 * leads rather than being left behind.
                 *
                 * `rl_leads.profile_id` points at `rl_lead_profiles.id`. Copying leads without
                 * their profiles gives the target ids pointing at rows it does not have — the
                 * same shape as the existing "content without media" hazard, and silent, because
                 * that column carries an index and not a constraint. `rl_lead_identifiers` is the
                 * email, phone and device fingerprints those profiles are matched on, so a
                 * profile without them cannot recognise a returning visitor.
                 *
                 * `emptyOrder()` reverses this, which is why the two lists differ in order rather
                 * than one being derived from the other.
                 */
                transferTables: [
                    'rl_lead_profiles',
                    'rl_lead_identifiers',
                    'rl_leads',
                    'rl_lead_activity_logs',
                ],
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
            /*
             * Purge-only, the same tier as referrers. These are named contacts at real
             * companies with a message they wrote to us, and nothing needs them outside the
             * environment they were submitted on — unlike leads, no warehouse cross-check reads
             * them. A staging copy would be PII with no job to do.
             */
            self::PARTNERSHIPS => new Dataset(
                key: self::PARTNERSHIPS,
                label: 'Partnership prospects',
                description: 'Companies that asked to become a partner on /become-a-partner/. '
                    .'Never copied between environments; can be emptied on either side.',
                tables: ['rl_partnership_prospects'],
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
     * Leads is last and independent: it shares no keys with wp_posts, so its
     * position only has to be stable, not early.
     *
     * @param  array<int, string>  $datasets
     * @return array<int, string>
     */
    public function importOrder(array $datasets): array
    {
        $order = [self::MEDIA, self::CONTENT, self::SETTINGS, self::LEADS];

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
