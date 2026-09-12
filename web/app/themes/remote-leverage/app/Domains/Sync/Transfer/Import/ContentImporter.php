<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Import;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLog;
use Illuminate\Support\Facades\DB;

/**
 * Writes an incoming batch of posts and their meta onto the target.
 *
 * Two different ID policies, and the asymmetry is deliberate:
 *
 *  - Ordinary posts keep their source ID and overwrite whatever sits there.
 *    Mirroring is the point, and IDs are referenced by menus, patterns and
 *    options, so reassigning them would break more than it protects.
 *  - Attachments keep their source ID too, but only while that ID is free or
 *    already holds the same file. A different attachment sitting on the ID is a
 *    real collision, and clobbering it would destroy a file the source never
 *    knew about — so the incoming row is inserted under a fresh ID and the
 *    remap is recorded for AttachmentReferenceRewriter to apply to content.
 *
 * Because content is rewritten against the map as it is written, media must be
 * imported first — see DatasetRegistry::importOrder().
 */
class ContentImporter
{
    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly AttachmentReferenceRewriter $rewriter,
    ) {}

    /**
     * Apply one batch to the target, returning how many rows were written.
     *
     * @param  array<int, array<string, mixed>>  $postRows
     * @param  array<int, array<string, mixed>>  $metaRows
     * @return array{posts: int, meta: int}
     */
    public function importBatch(
        TransferSession $session,
        string $dataset,
        array $postRows,
        array $metaRows,
        ?UndoLog $undo = null,
    ): array {
        $map = AttachmentIdMap::fromArray($session->attachmentMap);
        $isMedia = $dataset === DatasetRegistry::MEDIA;
        $written = ['posts' => 0, 'meta' => 0];

        foreach ($postRows as $row) {
            $sourceId = (int) ($row['ID'] ?? 0);

            if ($sourceId <= 0) {
                continue;
            }

            $targetId = $isMedia
                ? $this->resolveAttachmentId($sourceId, $row, $map)
                : $sourceId;

            $this->writePost($row, $targetId, $map, $undo);
            $this->writeMetaFor($sourceId, $targetId, $metaRows, $map, $written, $undo);

            $written['posts']++;
        }

        $session->attachmentMap = $map->toArray();
        $session->recordRows($dataset, 'posts', $written['posts']);
        $session->recordRows($dataset, 'meta', $written['meta']);

        return $written;
    }

    /**
     * Decide which ID an incoming attachment should occupy on the target.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveAttachmentId(int $sourceId, array $row, AttachmentIdMap $map): int
    {
        if ($map->has($sourceId)) {
            return $map->resolve($sourceId);
        }

        $existing = DB::table('posts')->where('ID', $sourceId)->first();

        // Nothing there, or the same file already — keep the source ID.
        if ($existing === null || $this->isSameAttachment((array) $existing, $row)) {
            return $sourceId;
        }

        $newId = $this->nextFreeId();
        $map->add($sourceId, $newId);

        return $newId;
    }

    /**
     * Whether the row already on the target is the same attachment arriving
     * again, rather than an unrelated post occupying the ID.
     *
     * guid is the stable identity for an attachment — it is the file's URL and
     * WordPress does not change it after upload.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     */
    private function isSameAttachment(array $existing, array $incoming): bool
    {
        if (($existing['post_type'] ?? null) !== 'attachment') {
            return false;
        }

        $existingGuid = (string) ($existing['guid'] ?? '');
        $incomingGuid = (string) ($incoming['guid'] ?? '');

        if ($existingGuid !== '' && $existingGuid === $incomingGuid) {
            return true;
        }

        // Fall back to the filename when guids differ only by host, which they
        // will between environments.
        return $existingGuid !== '' && $incomingGuid !== ''
            && basename($existingGuid) === basename($incomingGuid);
    }

    private function nextFreeId(): int
    {
        return ((int) DB::table('posts')->max('ID')) + 1;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function writePost(array $row, int $targetId, AttachmentIdMap $map, ?UndoLog $undo): void
    {
        $row['ID'] = $targetId;

        if (isset($row['post_content']) && is_string($row['post_content'])) {
            $row['post_content'] = $this->rewriter->rewriteContent($row['post_content'], $map);
        }

        $this->recordPostUndo($targetId, $undo);

        DB::table('posts')->updateOrInsert(['ID' => $targetId], $this->normaliseDates($row));
    }

    /**
     * Capture the row about to be overwritten, before it is.
     *
     * Recorded ahead of the write rather than after, so a request that dies
     * between the two leaves an undo entry for a change that did not happen —
     * restoring a row to the value it already holds — rather than a change with
     * no undo entry, which is unrecoverable.
     */
    private function recordPostUndo(int $targetId, ?UndoLog $undo): void
    {
        if (! $undo instanceof UndoLog) {
            return;
        }

        $existing = DB::table('posts')->where('ID', $targetId)->first();

        if ($existing === null) {
            $undo->recordInsert('posts', ['ID' => $targetId]);
        } else {
            $undo->recordUpdate('posts', ['ID' => $targetId], (array) $existing);
        }
    }

    /**
     * Empty values for the wp_posts columns that are NOT NULL without a usable
     * default under strict mode.
     *
     * A full export sends every column, so in normal use nothing here applies.
     * It exists so a partial row — a hand-built payload, or a source on an older
     * schema missing a column this target has — fails on its own merits rather
     * than on an opaque "Field X doesn't have a default value". The date columns
     * are handled separately by normaliseDates().
     *
     * @var array<string, string|int>
     */
    private const COLUMN_DEFAULTS = [
        'post_author' => 0,
        'post_content' => '',
        'post_title' => '',
        'post_excerpt' => '',
        'post_status' => 'publish',
        'comment_status' => 'open',
        'ping_status' => 'open',
        'post_password' => '',
        'post_name' => '',
        'to_ping' => '',
        'pinged' => '',
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'post',
        'post_mime_type' => '',
        'comment_count' => 0,
    ];

    /**
     * Replace zero and missing dates with values MySQL will accept.
     *
     * wp_posts declares its four date columns NOT NULL DEFAULT
     * '0000-00-00 00:00:00', and WordPress relies on that: it strips NO_ZERO_DATE
     * from sql_mode on its own connection, and stores zero dates to mean "unset"
     * on drafts and pending posts. This import runs over the Acorn connection,
     * which does no such thing — so a row carrying a zero date, or omitting the
     * column entirely, is rejected outright with an opaque 1292.
     *
     * Rather than loosening sql_mode for the whole connection, the dates are
     * filled in here: an unset date becomes the post's own date where there is
     * one, and the import time otherwise.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normaliseDates(array $row): array
    {
        $fallback = date('Y-m-d H:i:s');

        $row['post_date'] = $this->usableDate($row['post_date'] ?? null) ?? $fallback;
        $row['post_date_gmt'] = $this->usableDate($row['post_date_gmt'] ?? null) ?? $row['post_date'];
        $row['post_modified'] = $this->usableDate($row['post_modified'] ?? null) ?? $row['post_date'];
        $row['post_modified_gmt'] = $this->usableDate($row['post_modified_gmt'] ?? null) ?? $row['post_date_gmt'];

        foreach (self::COLUMN_DEFAULTS as $column => $default) {
            $row[$column] ??= $default;
        }

        return $row;
    }

    private function usableDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_starts_with($value, '0000-00-00') ? null : $value;
    }

    /**
     * Replace the target's meta for this post with the incoming set.
     *
     * A wholesale replace rather than a merge: the source sends every meta row
     * for the posts in the batch, so anything still on the target afterwards
     * would be a row the source has since deleted.
     *
     * @param  array<int, array<string, mixed>>  $metaRows
     * @param  array{posts: int, meta: int}  $written
     */
    private function writeMetaFor(
        int $sourceId,
        int $targetId,
        array $metaRows,
        AttachmentIdMap $map,
        array &$written,
        ?UndoLog $undo = null,
    ): void {
        $mine = array_values(array_filter(
            $metaRows,
            fn (array $meta) => (int) ($meta['post_id'] ?? 0) === $sourceId,
        ));

        if ($undo instanceof UndoLog) {
            $undo->recordRowset(
                'postmeta',
                ['post_id' => $targetId],
                array_map(fn ($r) => (array) $r, DB::table('postmeta')->where('post_id', $targetId)->get()->all()),
            );
        }

        DB::table('postmeta')->where('post_id', $targetId)->delete();

        foreach ($mine as $meta) {
            $key = (string) ($meta['meta_key'] ?? '');

            DB::table('postmeta')->insert([
                'post_id' => $targetId,
                'meta_key' => $key,
                'meta_value' => $this->rewriter->rewriteMetaValue($meta['meta_value'] ?? '', $key, $map),
            ]);

            $written['meta']++;
        }
    }
}
