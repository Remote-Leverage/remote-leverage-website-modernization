<?php

declare(strict_types=1);

namespace App\Domains\Sync\Datasets;

/**
 * One selectable group of tables in an environment sync.
 *
 * Tables are held unprefixed ("posts", "rl_leads") and resolved against the
 * live $wpdb prefix at use, so a dataset definition stays valid on an install
 * with a non-default prefix.
 *
 * $tables and $transferTables are different questions and must not be confused:
 *
 *  - $tables is what the *purger* may empty. Every dataset populates it,
 *    including content, media and settings — so "has tables" says nothing about
 *    how a dataset travels.
 *  - $transferTables is the explicit marker that this dataset moves between
 *    environments as whole rows of its own tables, rather than through the
 *    posts/meta pipeline. Only leads is shaped that way today.
 *
 * Order inside $transferTables is load order, and it is load-bearing: rows are
 * imported in this order and the target's tables are emptied in the reverse of
 * it, so a child table with a foreign key onto a parent must come after it.
 */
final class Dataset
{
    /**
     * The primary key every transfer table is walked and keyed on.
     *
     * A single name rather than per-table configuration: the custom tables in
     * this install all declare `id`, and both sides have to agree on the cursor
     * column for a resumable walk to mean anything. A future table without one
     * should be refused here rather than exported under a guess.
     */
    public const TRANSFER_KEY = 'id';

    /**
     * @param  array<int, string>  $tables  Unprefixed table names.
     * @param  array<int, string>  $transferTables  Unprefixed, in load order.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly array $tables,
        public readonly bool $transferable,
        public readonly bool $defaultSelected,
        public readonly bool $purgeable,
        public readonly array $transferTables = [],
    ) {}

    /**
     * Rows per request for a dataset moving through the posts/meta pipeline.
     *
     * Small because a "row" here is a post that drags its `post_content` and
     * every meta row attached to it, and one batch has to fit comfortably inside
     * a single request on both sides.
     */
    public const POST_BATCH_SIZE = 25;

    /**
     * Rows per request for a table-backed dataset.
     *
     * Four times the posts figure, and the ceiling ExportTransferBatchAbility
     * clamps to — raising it further means redeploying both ends.
     *
     * A lead row is a handful of narrow columns, so 25 of them spent a full
     * HTTPS round trip to move a few kilobytes: the first production leads pull
     * was 19,737 rows in 790 sequential requests and 15m39s, essentially all of
     * it latency. The batch size is per-dataset rather than one constant
     * precisely because the two shapes have nothing in common.
     */
    public const TABLE_BATCH_SIZE = 100;

    /**
     * Whether this dataset travels as whole table rows rather than as posts.
     */
    public function isTableBacked(): bool
    {
        return $this->transferTables !== [];
    }

    /**
     * How many rows to move per request for this dataset.
     */
    public function batchSize(): int
    {
        return $this->isTableBacked() ? self::TABLE_BATCH_SIZE : self::POST_BATCH_SIZE;
    }

    /**
     * The tables to empty on the target, children before parents.
     *
     * @return array<int, string>
     */
    public function emptyOrder(): array
    {
        return array_reverse($this->transferTables);
    }

    /**
     * Table names with the install's prefix applied.
     *
     * @return array<int, string>
     */
    public function prefixedTables(string $prefix): array
    {
        return array_map(fn (string $table) => $prefix.$table, $this->tables);
    }
}
