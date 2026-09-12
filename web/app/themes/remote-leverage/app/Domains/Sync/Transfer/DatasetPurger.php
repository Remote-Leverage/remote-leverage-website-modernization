<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncEnvironment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Empties a dataset's tables on this environment.
 *
 * The only operation permitted against leads, referrals and scheduling — they
 * are never copied between environments, but they do accumulate test data that
 * needs clearing on both sides. Anything not marked purgeable is refused, which
 * is what keeps this away from users and from content.
 *
 * Deliberately not undoable. An undo log for a purge would have to hold every
 * row it deleted, which for the tables this touches is the entire dataset —
 * that is a backup, not an undo log, and pretending otherwise would offer a
 * safety net that silently fails at scale. The confirmation is the safeguard.
 */
class DatasetPurger
{
    public function __construct(private readonly DatasetRegistry $registry) {}

    /**
     * @return array<string, int> Table name to rows deleted.
     */
    public function purge(string $datasetKey): array
    {
        SyncEnvironment::assertSyncEnabled();

        $dataset = $this->registry->get($datasetKey);

        if (! $dataset->purgeable) {
            throw new InvalidArgumentException(
                "The \"{$dataset->label}\" dataset cannot be purged by this tool."
            );
        }

        $deleted = [];

        foreach ($dataset->tables as $table) {
            // Belt and braces: the registry already excludes these, but this is
            // the one place in the feature that deletes unconditionally.
            if ($this->registry->isNeverSynced($table)) {
                continue;
            }

            if (! Schema::hasTable($table)) {
                // Migrations have not always reached every environment in this
                // project, so a missing table is reported rather than fatal.
                $deleted[$table] = -1;

                continue;
            }

            $deleted[$table] = DB::table($table)->count();
            DB::table($table)->delete();
        }

        return $deleted;
    }

    /**
     * Row counts without deleting anything, so a confirmation can say what is
     * actually at stake.
     *
     * @return array<string, int>
     */
    public function preview(string $datasetKey): array
    {
        $dataset = $this->registry->get($datasetKey);
        $counts = [];

        foreach ($dataset->tables as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : -1;
        }

        return $counts;
    }
}
