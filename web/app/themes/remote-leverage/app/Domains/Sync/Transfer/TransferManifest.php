<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;

/**
 * What a single transfer run will do: direction, which datasets, what to skip,
 * and which of them to empty on the target first.
 *
 * Built once from untrusted input (an admin form, or an ability's REST payload)
 * and validated at construction, so every later stage can treat it as already
 * safe. In particular this is the only place that decides a dataset may move
 * between environments — the purge-only tier (leads, referrals, scheduling,
 * users) is refused here rather than being filtered out silently, so asking for
 * it is an error you see rather than a no-op you don't.
 */
final class TransferManifest
{
    public const PUSH = 'push';

    public const PULL = 'pull';

    /**
     * @param  array<int, string>  $datasets
     * @param  array<int, string>  $excludedPostTypes
     * @param  array<int, int>  $excludedPostIds
     * @param  array<int, string>  $excludedTaxonomies
     * @param  array<int, string>  $cleanBeforeImport
     */
    private function __construct(
        public readonly string $direction,
        public readonly array $datasets,
        public readonly array $excludedPostTypes,
        public readonly array $excludedPostIds,
        public readonly array $excludedTaxonomies,
        public readonly array $cleanBeforeImport,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws InvalidManifestException
     */
    public static function fromArray(array $input, DatasetRegistry $registry): self
    {
        $direction = is_string($input['direction'] ?? null) ? $input['direction'] : '';

        if (! in_array($direction, [self::PUSH, self::PULL], true)) {
            throw new InvalidManifestException(
                'Transfer direction must be "'.self::PUSH.'" or "'.self::PULL.'".'
            );
        }

        $datasets = self::stringList($input['datasets'] ?? []);

        if ($datasets === []) {
            throw new InvalidManifestException('Select at least one dataset to transfer.');
        }

        foreach ($datasets as $key) {
            if (! $registry->has($key)) {
                throw new InvalidManifestException("Unknown sync dataset: {$key}");
            }

            if (! $registry->get($key)->transferable) {
                throw new InvalidManifestException(
                    "The \"{$registry->get($key)->label}\" dataset is never transferred between "
                    .'environments. It can only be emptied, from the Maintenance section.'
                );
            }
        }

        $clean = self::stringList($input['clean_before_import'] ?? []);

        foreach ($clean as $key) {
            if (! in_array($key, $datasets, true)) {
                throw new InvalidManifestException(
                    "Cannot clean \"{$key}\" before import: it is not one of the selected datasets."
                );
            }
        }

        return new self(
            direction: $direction,
            datasets: $datasets,
            excludedPostTypes: self::stringList($input['excluded_post_types'] ?? []),
            excludedPostIds: self::intList($input['excluded_post_ids'] ?? []),
            excludedTaxonomies: self::stringList($input['excluded_taxonomies'] ?? []),
            cleanBeforeImport: $clean,
        );
    }

    public function includes(string $dataset): bool
    {
        return in_array($dataset, $this->datasets, true);
    }

    public function shouldClean(string $dataset): bool
    {
        return in_array($dataset, $this->cleanBeforeImport, true);
    }

    public function isPush(): bool
    {
        return $this->direction === self::PUSH;
    }

    /**
     * True when content is selected without media, which leaves the target with
     * posts referencing attachments it does not have. Not an error — you may
     * genuinely want content-only — but the UI should say so before running.
     */
    public function hasDanglingMediaRisk(): bool
    {
        return $this->includes(DatasetRegistry::CONTENT) && ! $this->includes(DatasetRegistry::MEDIA);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction,
            'datasets' => $this->datasets,
            'excluded_post_types' => $this->excludedPostTypes,
            'excluded_post_ids' => $this->excludedPostIds,
            'excluded_taxonomies' => $this->excludedTaxonomies,
            'clean_before_import' => $this->cleanBeforeImport,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = array_map(
            fn ($item) => is_string($item) ? trim($item) : '',
            array_values($value),
        );

        return array_values(array_unique(array_filter($strings, fn (string $s) => $s !== '')));
    }

    /**
     * @return array<int, int>
     */
    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ints = array_map(
            fn ($item) => is_numeric($item) ? (int) $item : 0,
            array_values($value),
        );

        return array_values(array_unique(array_filter($ints, fn (int $i) => $i > 0)));
    }
}
