<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Append-only record of what a transfer overwrote, so it can be put back.
 *
 * The design doc called for a full backup of every affected table before the
 * first row lands. That cannot work here: the receiving side has no CLI and no
 * long-running process, and dumping wp_posts inside one PHP request is exactly
 * the thing that times out. An undo log is bounded by what actually changed
 * instead of by table size, and it is written incrementally, so it fits the
 * one-request-per-chunk shape the transfer already has.
 *
 * JSONL rather than a table: one append per row, no schema to migrate onto the
 * target, and a partially written final line can be discarded on read without
 * invalidating everything before it.
 */
class UndoLog
{
    /**
     * Row existed and was overwritten — undo by restoring it.
     */
    public const OP_UPDATE = 'update';

    /**
     * Row did not exist and was created — undo by deleting it.
     */
    public const OP_INSERT = 'insert';

    /**
     * A whole set of rows matching a scope was replaced — undo by deleting
     * whatever matches the scope now and restoring the recorded rows.
     *
     * Postmeta needs this: the importer replaces a post's meta wholesale, and
     * the replacements get fresh auto-increment meta_ids that are not known
     * until after the insert, so neither per-row op can express the undo.
     */
    public const OP_ROWSET = 'rowset';

    /**
     * A file the transfer created — undo by deleting it.
     */
    public const OP_FILE_ADD = 'file_add';

    /**
     * A file the transfer overwrote — undo by restoring the copy taken first.
     */
    public const OP_FILE_REPLACE = 'file_replace';

    public function __construct(private readonly string $path) {}

    /**
     * @param  array<string, mixed>  $primaryKey
     * @param  array<string, mixed>  $before
     */
    public function recordUpdate(string $table, array $primaryKey, array $before): void
    {
        $this->append([
            'op' => self::OP_UPDATE,
            'table' => $table,
            'pk' => $primaryKey,
            'before' => $before,
        ]);
    }

    /**
     * @param  array<string, mixed>  $primaryKey
     */
    public function recordInsert(string $table, array $primaryKey): void
    {
        $this->append([
            'op' => self::OP_INSERT,
            'table' => $table,
            'pk' => $primaryKey,
        ]);
    }

    /**
     * @param  array<string, mixed>  $scope  Column/value pairs identifying the set.
     * @param  array<int, array<string, mixed>>  $before
     */
    public function recordRowset(string $table, array $scope, array $before): void
    {
        $this->append([
            'op' => self::OP_ROWSET,
            'table' => $table,
            'pk' => $scope,
            'rows' => $before,
        ]);
    }

    public function recordFileAdd(string $absolutePath): void
    {
        $this->append(['op' => self::OP_FILE_ADD, 'path' => $absolutePath]);
    }

    public function recordFileReplace(string $absolutePath, string $backupPath): void
    {
        $this->append([
            'op' => self::OP_FILE_REPLACE,
            'path' => $absolutePath,
            'backup' => $backupPath,
        ]);
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function entryCount(): int
    {
        return count($this->entries());
    }

    /**
     * Undo everything recorded, newest first.
     *
     * Reverse order matters: a row inserted and then updated within one transfer
     * must have the update undone before the insert, or the restore writes a row
     * the delete then removes.
     *
     * @return int Number of entries applied.
     */
    public function rollback(): int
    {
        $applied = 0;

        foreach (array_reverse($this->entries()) as $entry) {
            $op = $entry['op'] ?? '';

            if ($op === self::OP_FILE_ADD || $op === self::OP_FILE_REPLACE) {
                $applied += $this->rollbackFile($entry, $op) ? 1 : 0;

                continue;
            }

            $table = (string) ($entry['table'] ?? '');
            $pk = (array) ($entry['pk'] ?? []);

            if ($table === '' || $pk === []) {
                continue;
            }

            if ($op === self::OP_INSERT) {
                DB::table($table)->where($pk)->delete();
            } elseif ($op === self::OP_ROWSET) {
                DB::table($table)->where($pk)->delete();

                foreach ((array) ($entry['rows'] ?? []) as $row) {
                    DB::table($table)->insert((array) $row);
                }
            } else {
                DB::table($table)->updateOrInsert($pk, (array) ($entry['before'] ?? []));
            }

            $applied++;
        }

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function rollbackFile(array $entry, string $op): bool
    {
        $path = (string) ($entry['path'] ?? '');

        if ($path === '') {
            return false;
        }

        if ($op === self::OP_FILE_ADD) {
            return ! is_file($path) || @unlink($path);
        }

        $backup = (string) ($entry['backup'] ?? '');

        // Copy rather than rename, so a rollback replayed after a partial run
        // still finds the backup where it expects it.
        return $backup !== '' && is_file($backup) && @copy($backup, $path);
    }

    public function discard(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entries(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $entries = [];

        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);

            // A line truncated by a request dying mid-write is skipped rather
            // than aborting the rollback — every complete entry before it is
            // still valid and still worth restoring.
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function append(array $entry): void
    {
        $this->ensureDirectory();

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            // A row that cannot be encoded cannot be restored, and silently
            // continuing would leave a gap in a log whose whole value is being
            // complete. Fail the transfer instead.
            throw new RuntimeException('Could not record an undo entry for '.($entry['table'] ?? 'unknown').'.');
        }

        if (file_put_contents($this->path, $line."\n", FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Could not write to the undo log at '.$this->path.'.');
        }
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Could not create the undo log directory at '.$dir.'.');
        }
    }
}
