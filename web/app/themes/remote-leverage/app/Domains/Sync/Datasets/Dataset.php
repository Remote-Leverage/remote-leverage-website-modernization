<?php

declare(strict_types=1);

namespace App\Domains\Sync\Datasets;

/**
 * One selectable group of tables in an environment sync.
 *
 * Tables are held unprefixed ("posts", "rl_leads") and resolved against the
 * live $wpdb prefix at use, so a dataset definition stays valid on an install
 * with a non-default prefix.
 */
final class Dataset
{
    /**
     * @param  array<int, string>  $tables  Unprefixed table names.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly array $tables,
        public readonly bool $transferable,
        public readonly bool $defaultSelected,
        public readonly bool $purgeable,
    ) {}

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
