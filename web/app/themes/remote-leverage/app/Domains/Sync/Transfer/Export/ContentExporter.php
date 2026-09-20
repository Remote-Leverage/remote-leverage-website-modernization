<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Export;

use App\Domains\Sync\Datasets\Dataset;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\PostSelection;
use App\Domains\Sync\Transfer\TransferManifest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads the post rows a transfer covers, in resumable batches.
 *
 * Cursor-based on the primary key rather than offset-based: the exporting side
 * may be read across many HTTP requests, and an OFFSET walk silently skips or
 * repeats rows if anything is inserted between calls.
 *
 * Table names are passed unprefixed — the Acorn database connection already
 * carries the install's prefix, so DB::table('posts') resolves to wp_posts.
 *
 * Two shapes of export live here. Posts and their meta travel through
 * postIdBatch()/postRows()/postMetaRows(); a table-backed dataset (leads)
 * travels as whole rows through tableCount()/tableRowBatch(). The second is not
 * reachable for a dataset that has not declared transferTables.
 */
class ContentExporter
{
    public function __construct(private readonly DatasetRegistry $registry) {}

    /**
     * How many posts the given dataset will export.
     */
    public function count(TransferManifest $manifest, string $dataset): int
    {
        return $this->postQuery($manifest, $dataset)->count();
    }

    /**
     * The next batch of post IDs after $afterId, ascending.
     *
     * @return array<int, int>
     */
    public function postIdBatch(TransferManifest $manifest, string $dataset, int $afterId, int $limit): array
    {
        $ids = $this->postQuery($manifest, $dataset)
            ->where('ID', '>', $afterId)
            ->orderBy('ID')
            ->limit(max(1, $limit))
            ->pluck('ID')
            ->all();

        return array_map('intval', $ids);
    }

    /**
     * Full post rows for the given IDs.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function postRows(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map(
            fn ($row) => (array) $row,
            DB::table('posts')->whereIn('ID', $ids)->orderBy('ID')->get()->all(),
        );
    }

    /**
     * Postmeta rows belonging to the given posts.
     *
     * Scoped to the same ID batch as the posts it accompanies, so a post and its
     * meta always cross together and a partial transfer can never leave meta
     * attached to a post the target did not receive.
     *
     * @param  array<int, int>  $postIds
     * @return array<int, array<string, mixed>>
     */
    public function postMetaRows(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return array_map(
            fn ($row) => (array) $row,
            DB::table('postmeta')->whereIn('post_id', $postIds)->orderBy('meta_id')->get()->all(),
        );
    }

    /**
     * How many rows one transfer table holds.
     *
     * Separate from count() above, which asks the posts question. A table-backed
     * dataset has no posts at all, and answering it through PostSelection would
     * return the entire content set.
     */
    public function tableCount(string $table): int
    {
        return DB::table($this->assertTransferTable($table))->count();
    }

    /**
     * The next batch of whole rows from one transfer table, ascending by id.
     *
     * Verbatim columns, no projection and no filtering: a table-backed dataset
     * is mirrored, so anything dropped here is data the target silently never
     * receives. Cursor-based on the primary key for the same reason the post
     * walk is — the export spans many requests.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tableRowBatch(string $table, int $after, int $limit): array
    {
        return array_map(
            fn ($row) => (array) $row,
            DB::table($this->assertTransferTable($table))
                ->where(Dataset::TRANSFER_KEY, '>', $after)
                ->orderBy(Dataset::TRANSFER_KEY)
                ->limit(max(1, $limit))
                ->get()
                ->all(),
        );
    }

    /**
     * Refuse any table that is not declared as a transfer table by some dataset.
     *
     * The abilities resolve a table by index rather than by name, so a caller
     * cannot name one — but this is the only method in the export path that
     * takes a table name at all, and it is worth it being unable to read an
     * arbitrary one even if a future caller passes input straight through.
     */
    private function assertTransferTable(string $table): string
    {
        foreach ($this->registry->all() as $dataset) {
            if (in_array($table, $dataset->transferTables, true)) {
                return $table;
            }
        }

        throw new InvalidArgumentException("\"{$table}\" is not a transferable table.");
    }

    /**
     * The whitelisted wp_options values this environment would send.
     *
     * Deliberately driven by the local whitelist and re-checked against the
     * target's own in ImportSyncableSettingsAbility, so neither side has to
     * trust the other's idea of what is syncable.
     *
     * @return array<string, mixed>
     */
    public function settingsValues(): array
    {
        $values = [];

        foreach ((array) config('rl-sync.options', []) as $key) {
            $values[(string) $key] = get_option((string) $key, null);
        }

        return $values;
    }

    /**
     * The query describing every post in scope for this dataset.
     *
     * Content and media partition wp_posts between them: media is exactly the
     * attachments, content is everything else. That way selecting both transfers
     * each row once, and selecting content alone leaves attachments behind
     * deliberately rather than by accident.
     */
    private function postQuery(TransferManifest $manifest, string $dataset): Builder
    {
        return PostSelection::query($manifest, $dataset);
    }
}
