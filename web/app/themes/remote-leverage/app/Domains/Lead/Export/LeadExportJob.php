<?php

declare(strict_types=1);

namespace App\Domains\Lead\Export;

/**
 * One export in flight, as it survives between the browser's batches.
 *
 * Deliberately a plain mutable object rather than a readonly value: it is written to on every
 * batch, and threading a new instance through each step buys nothing when the store rewrites
 * the whole row anyway.
 *
 * `cursor` is the lowest lead id written so far, not an offset. An OFFSET-based walk re-reads
 * and re-sorts everything it has already passed, so the last batch of a large export costs many
 * times the first; worse, a lead captured mid-export shifts every subsequent offset by one and
 * silently drops a row from the file.
 *
 * `ceiling` is the highest lead id at the moment the export started, and every batch stays at or
 * below it. Without it an export is not a snapshot: leads captured while it runs join at the top
 * (the order is newest first), so a capped export would write those and drop an equal number of
 * the leads it was started to fetch, with the row count still landing exactly on target.
 */
class LeadExportJob
{
    public function __construct(
        public string $id,
        public LeadExportOptions $options,
        public string $filename,
        public string $path,
        public int $total = 0,
        public int $written = 0,
        public int $cursor = 0,
        public int $ceiling = 0,
        public string $phase = 'pending',
        public string $error = '',
        public int $createdAt = 0,
        public int $updatedAt = 0,
    ) {}

    public function isFinished(): bool
    {
        return in_array($this->phase, ['done', 'failed'], true);
    }

    /** Whole percent written, or 100 when there was nothing to write. */
    public function percent(): int
    {
        if ($this->total <= 0) {
            return 100;
        }

        return (int) min(100, floor(($this->written / $this->total) * 100));
    }

    /** What the progress bar says. */
    public function label(): string
    {
        return match ($this->phase) {
            'done' => $this->total === 0
                ? 'No leads matched — the file has headers only'
                : number_format($this->written).' leads exported',
            'failed' => 'Export failed: '.$this->error,
            default => number_format($this->written).' of '.number_format($this->total).' leads',
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'options' => $this->options->toArray(),
            'filename' => $this->filename,
            'path' => $this->path,
            'total' => $this->total,
            'written' => $this->written,
            'cursor' => $this->cursor,
            'ceiling' => $this->ceiling,
            'phase' => $this->phase,
            'error' => $this->error,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            options: LeadExportOptions::fromArray((array) ($data['options'] ?? [])),
            filename: (string) ($data['filename'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            total: (int) ($data['total'] ?? 0),
            written: (int) ($data['written'] ?? 0),
            cursor: (int) ($data['cursor'] ?? 0),
            ceiling: (int) ($data['ceiling'] ?? 0),
            phase: (string) ($data['phase'] ?? 'pending'),
            error: (string) ($data['error'] ?? ''),
            createdAt: (int) ($data['created_at'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? 0),
        );
    }
}
