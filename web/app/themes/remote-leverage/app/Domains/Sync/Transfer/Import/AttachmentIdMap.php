<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Import;

/**
 * Old attachment ID → new attachment ID, for the attachments that could not
 * keep their source ID on the target.
 *
 * The map is deliberately sparse. Remapping every attachment would mean
 * rewriting every reference in every post, and every rewrite is a chance to
 * corrupt serialized data — so the importer preserves the source ID whenever
 * the target has nothing at it, or already holds the same file. Only a genuine
 * collision (the target has a *different* attachment at that ID) produces an
 * entry here.
 *
 * An empty map therefore means no content rewriting is needed at all, which is
 * the normal case for a target that was cleaned before import.
 */
final class AttachmentIdMap
{
    /**
     * @var array<int, int>
     */
    private array $map = [];

    /**
     * @param  array<int, int>  $map
     */
    public static function fromArray(array $map): self
    {
        $instance = new self;

        foreach ($map as $old => $new) {
            $instance->add((int) $old, (int) $new);
        }

        return $instance;
    }

    public function add(int $oldId, int $newId): void
    {
        if ($oldId > 0 && $newId > 0 && $oldId !== $newId) {
            $this->map[$oldId] = $newId;
        }
    }

    public function has(int $oldId): bool
    {
        return isset($this->map[$oldId]);
    }

    /**
     * The ID this attachment has on the target — the source ID unchanged when
     * it was preserved, which is the common case.
     */
    public function resolve(int $oldId): int
    {
        return $this->map[$oldId] ?? $oldId;
    }

    public function isEmpty(): bool
    {
        return $this->map === [];
    }

    public function count(): int
    {
        return count($this->map);
    }

    /**
     * @return array<int, int>
     */
    public function toArray(): array
    {
        return $this->map;
    }
}
