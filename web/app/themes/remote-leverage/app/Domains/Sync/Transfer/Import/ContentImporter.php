<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Import;

use App\Domains\Sync\Datasets\Dataset;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

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
 *
 * A third policy sits alongside those two, for table-backed datasets: leads
 * arrive as whole rows of their own tables, the target's copies are emptied
 * first, and the source's primary keys are preserved exactly. No remapping and
 * no merge — see importTableBatch().
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
     * Rows read, recorded and deleted per round trip while emptying a table.
     *
     * Same reasoning as DatasetCleaner::BATCH: the receiving side has no
     * long-running process, so the empty walks in bounded steps rather than
     * relying on one large DELETE.
     */
    private const EMPTY_BATCH = 200;

    /**
     * Apply one batch of whole table rows to the target.
     *
     * This is the table-backed path — leads today — and it is a replace, not a
     * merge: the dataset's tables are emptied once, on the first batch to
     * arrive for that dataset, and rows are then inserted under the primary keys
     * they carried on the source. Preserving the keys is what keeps
     * rl_lead_activity_logs.lead_id pointing at the right lead.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int Rows written.
     */
    public function importTableBatch(
        TransferSession $session,
        string $dataset,
        string $table,
        array $rows,
        ?UndoLog $undo = null,
    ): int {
        $definition = $this->transferDataset($session, $dataset);

        if (! in_array($table, $definition->transferTables, true)) {
            throw new InvalidArgumentException(
                "Table \"{$table}\" is not part of the \"{$definition->label}\" dataset."
            );
        }

        // A no-op if the ability or the runner already did it and saved the
        // session; the marker is what makes a redelivered chunk safe.
        $this->emptyTablesFor($session, $dataset, $undo);

        $key = Dataset::TRANSFER_KEY;
        $entries = [];
        $written = 0;

        foreach ($rows as $row) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                // Dropping it silently is how a transfer reports success having
                // shipped fewer rows than it read.
                throw new RuntimeException("A {$table} row arrived without a {$key}.");
            }

            $id = $row[$key];

            // Always an insert entry, never an update: the table was emptied at
            // the top of this dataset, so nothing here existed before the
            // transfer — including on a redelivered chunk, where the row present
            // is one this same transfer wrote.
            $entries[] = UndoLog::insertEntry($table, [$key => $id]);

            // Keyed upsert rather than insert, so a chunk redelivered after a
            // dropped connection lands on the same row instead of colliding
            // with itself on the primary key.
            DB::table($table)->updateOrInsert([$key => $id], $row);

            $written++;
        }

        if ($undo instanceof UndoLog) {
            $undo->recordMany($entries);
        }

        $session->recordRows($dataset, $table, $written);

        return $written;
    }

    /**
     * Empty this dataset's tables on the target, once per session.
     *
     * Public and separate from importTableBatch so the caller can persist the
     * session between the empty and the first insert — the same ordering the
     * post path uses, and for the same reason: if the import dies and the chunk
     * is redelivered, the retry must not empty a second time and take the rows
     * this transfer already wrote with it.
     *
     * Children before parents, so rl_lead_activity_logs goes before rl_leads
     * and the foreign key never dangles.
     *
     * @return int Rows removed; zero once it has already run.
     */
    public function emptyTablesFor(TransferSession $session, string $dataset, ?UndoLog $undo = null): int
    {
        $definition = $this->transferDataset($session, $dataset);
        $marker = self::EMPTIED_MARKER_PREFIX.$dataset;

        if ($this->hasEmptied($session, $dataset)) {
            return 0;
        }

        $removed = 0;

        foreach ($definition->emptyOrder() as $table) {
            $removed += $this->emptyTable($table, $undo);
        }

        $session->markCleaned($marker);
        $session->recordRows($dataset, 'removed', $removed);

        return $removed;
    }

    /**
     * Marker namespace for "this dataset's tables have been emptied".
     *
     * Prefixed rather than the bare dataset key so it cannot be confused with
     * the post cleaner's marker, which answers a different question about a
     * different set of rows.
     */
    private const EMPTIED_MARKER_PREFIX = 'tables:';

    /**
     * Whether this session has already emptied the dataset's tables.
     *
     * A zero return from emptyTablesFor() is ambiguous on its own — it means
     * either "already done" or "the target's tables were empty anyway" — and
     * the caller has to persist the marker in the second case too.
     */
    public function hasEmptied(TransferSession $session, string $dataset): bool
    {
        return $session->hasCleaned(self::EMPTIED_MARKER_PREFIX.$dataset);
    }

    /**
     * Record and delete every row of one table.
     */
    private function emptyTable(string $table, ?UndoLog $undo): int
    {
        $key = Dataset::TRANSFER_KEY;
        $removed = 0;
        $after = null;

        while (true) {
            $query = DB::table($table)->orderBy($key)->limit(self::EMPTY_BATCH);

            if ($after !== null) {
                $query->where($key, '>', $after);
            }

            $rows = array_map(fn ($row) => (array) $row, $query->get()->all());

            if ($rows === []) {
                return $removed;
            }

            $ids = array_column($rows, $key);
            $after = end($ids);

            if ($undo instanceof UndoLog) {
                // One write for the batch rather than one per row — a whole
                // table is recorded inside a single request, and on staging the
                // log is on EFS.
                $undo->recordMany(array_map(
                    fn (array $row) => UndoLog::updateEntry($table, [$key => $row[$key]], $row),
                    $rows,
                ));
            }

            DB::table($table)->whereIn($key, $ids)->delete();

            $removed += count($ids);
        }
    }

    /**
     * The dataset definition, having checked it may actually travel this way.
     *
     * Three separate questions, all of which have to hold: the session's own
     * manifest covers the dataset, the dataset is transferable at all, and it is
     * declared table-backed. Emptying a target table is the most destructive
     * thing in this pipeline, so the reason it is allowed is re-derived here
     * rather than inherited from whoever called.
     */
    private function transferDataset(TransferSession $session, string $dataset): Dataset
    {
        if (! $session->manifest->includes($dataset)) {
            throw new InvalidArgumentException("Dataset \"{$dataset}\" is not part of this transfer.");
        }

        $definition = $this->registry->get($dataset);

        if (! $definition->transferable || ! $definition->isTableBacked()) {
            throw new InvalidArgumentException(
                "The \"{$definition->label}\" dataset does not travel as table rows."
            );
        }

        return $definition;
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
