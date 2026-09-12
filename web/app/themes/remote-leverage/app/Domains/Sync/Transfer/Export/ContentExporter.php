<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Export;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferManifest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads the post rows a transfer covers, in resumable batches.
 *
 * Cursor-based on the primary key rather than offset-based: the exporting side
 * may be read across many HTTP requests, and an OFFSET walk silently skips or
 * repeats rows if anything is inserted between calls.
 *
 * Table names are passed unprefixed — the Acorn database connection already
 * carries the install's prefix, so DB::table('posts') resolves to wp_posts.
 */
class ContentExporter
{
    /**
     * Post types never exported, whatever the manifest says.
     *
     * Revisions and auto-drafts are per-environment editing residue, and on this
     * install they are the bulk of wp_posts — carrying them would multiply the
     * transfer size for data the target has no use for.
     *
     * @var array<int, string>
     */
    private const ALWAYS_EXCLUDED_TYPES = ['revision', 'auto-draft'];

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
     * The query describing every post in scope for this dataset.
     *
     * Content and media partition wp_posts between them: media is exactly the
     * attachments, content is everything else. That way selecting both transfers
     * each row once, and selecting content alone leaves attachments behind
     * deliberately rather than by accident.
     */
    private function postQuery(TransferManifest $manifest, string $dataset): Builder
    {
        $query = DB::table('posts')->whereNotIn('post_type', self::ALWAYS_EXCLUDED_TYPES);

        if ($dataset === DatasetRegistry::MEDIA) {
            $query->where('post_type', '=', 'attachment');
        } else {
            $query->where('post_type', '!=', 'attachment');

            if ($manifest->excludedPostTypes !== []) {
                $query->whereNotIn('post_type', $manifest->excludedPostTypes);
            }
        }

        if ($manifest->excludedPostIds !== []) {
            $query->whereNotIn('ID', $manifest->excludedPostIds);
        }

        return $query;
    }
}
