<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Import;

use App\Domains\Sync\Transfer\PostSelection;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLog;
use Illuminate\Support\Facades\DB;

/**
 * Empties a dataset on the target before the incoming rows are written, so the
 * target ends up a mirror of the source rather than a merge of the two.
 *
 * This is the "clean before import" toggle from docs/environment-sync.md §2.
 * Without it, import is an upsert keyed on the source's IDs, and anything the
 * source does not have keeps whatever the target had at that ID — which on two
 * independently-grown environments means unrelated posts colliding on the same
 * number and pages surviving under slugs the source has since reused.
 *
 * Three things bound what this deletes, and each is load-bearing:
 *
 *  - **Only what the export replaces.** The set comes from PostSelection, the
 *    same definition the exporting side sends from. Deleting a wider set would
 *    destroy rows with nothing arriving to restore them.
 *  - **Only wp_posts and wp_postmeta.** The content dataset advertises the term
 *    tables, but nothing in the transfer actually carries terms — so cleaning
 *    them would drop every category and tag permanently. Taxonomy is left
 *    exactly as the target had it.
 *  - **Rows, not files.** Uploaded files stay. The media transfer already asks
 *    the target which files it is missing and sends only those, so deleting
 *    them here would force a re-upload of everything for no gain, and the
 *    attachment rows that point at them are rebuilt from the source regardless.
 *
 * Every deleted row is recorded in the session's undo log first, so a clean is
 * reversible by the same rollback that reverses the import it precedes. That is
 * the opposite of DatasetPurger, which refuses to pretend a whole-table
 * truncation can be undone — the difference is that this is bounded by one
 * dataset that is about to be replaced, not by an open-ended table.
 */
class DatasetCleaner
{
    /**
     * Rows read, recorded and deleted per round trip.
     *
     * The receiving side has no long-running process, so this walks in batches
     * for the same reason the import does: a clean of a large target has to fit
     * inside a normal PHP request rather than relying on one big DELETE.
     */
    private const BATCH = 200;

    /**
     * Delete this dataset's rows on the target.
     *
     * @return int Number of posts removed.
     */
    public function clean(TransferSession $session, string $dataset, ?UndoLog $undo = null): int
    {
        $removed = 0;
        $afterId = 0;

        while (true) {
            $rows = PostSelection::query($session->manifest, $dataset)
                ->where('ID', '>', $afterId)
                ->orderBy('ID')
                ->limit(self::BATCH)
                ->get()
                ->all();

            if ($rows === []) {
                return $removed;
            }

            $ids = array_map(fn ($row) => (int) $row->ID, $rows);

            // Walk forward on the primary key rather than re-reading from the
            // start. The rows just handled are gone by the next iteration, so a
            // cursor is not strictly required — but it also cannot loop forever
            // if a delete is ever refused, which re-reading from zero would.
            $afterId = (int) end($ids);

            $this->record($rows, $ids, $undo);

            DB::table('postmeta')->whereIn('post_id', $ids)->delete();
            DB::table('posts')->whereIn('ID', $ids)->delete();

            $removed += count($ids);
        }
    }

    /**
     * Capture the rows about to be deleted, before they are.
     *
     * Written ahead of the delete for the same reason the importer records its
     * overwrites ahead of the write: a request dying between the two leaves an
     * undo entry for a deletion that did not happen, which restores a row to the
     * value it already holds, rather than a deletion with no undo entry, which
     * is unrecoverable.
     *
     * A post's meta is recorded as a rowset rather than row by row because the
     * meta_ids are not preserved — a restore re-inserts the set, and rowset is
     * the only op that can express that.
     *
     * @param  array<int, object>  $rows
     * @param  array<int, int>  $ids
     */
    private function record(array $rows, array $ids, ?UndoLog $undo): void
    {
        if (! $undo instanceof UndoLog) {
            return;
        }

        $metaByPost = [];

        foreach (DB::table('postmeta')->whereIn('post_id', $ids)->get() as $meta) {
            $metaByPost[(int) $meta->post_id][] = (array) $meta;
        }

        $entries = [];

        foreach ($rows as $row) {
            $id = (int) $row->ID;

            $entries[] = UndoLog::updateEntry('posts', ['ID' => $id], (array) $row);
            $entries[] = UndoLog::rowsetEntry('postmeta', ['post_id' => $id], $metaByPost[$id] ?? []);
        }

        // One write per batch, not one per entry. A clean records a whole
        // dataset inside a single request, and on staging the log is on EFS —
        // recording 512 attachments as 1,024 individually-locked appends is
        // what timed the first media chunk out at 30s.
        $undo->recordMany($entries);
    }
}
