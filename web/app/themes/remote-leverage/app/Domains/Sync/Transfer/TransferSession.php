<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;

/**
 * One transfer run, on the importing side.
 *
 * A transfer spans many HTTP requests — the exporting side sends batches and
 * the target applies them one call at a time — so the progress, the attachment
 * ID map built so far, and the undo log all have to survive between requests.
 * This is that state; SessionStore persists it.
 */
final class TransferSession
{
    public const STATE_OPEN = 'open';

    public const STATE_IMPORTING = 'importing';

    public const STATE_COMPLETE = 'complete';

    public const STATE_FAILED = 'failed';

    /**
     * @param  array<string, array<string, int>>  $counters
     * @param  array<int, int>  $attachmentMap
     */
    public function __construct(
        public readonly string $id,
        public readonly TransferManifest $manifest,
        public string $state = self::STATE_OPEN,
        public array $counters = [],
        public array $attachmentMap = [],
        public ?string $error = null,
        public readonly int $createdAt = 0,
        public int $updatedAt = 0,
    ) {}

    public function recordRows(string $dataset, string $kind, int $count): void
    {
        $this->counters[$dataset][$kind] = ($this->counters[$dataset][$kind] ?? 0) + $count;
    }

    public function rowsFor(string $dataset, string $kind): int
    {
        return $this->counters[$dataset][$kind] ?? 0;
    }

    public function totalRows(): int
    {
        $total = 0;

        foreach ($this->counters as $kinds) {
            $total += array_sum($kinds);
        }

        return $total;
    }

    public function isFinished(): bool
    {
        return in_array($this->state, [self::STATE_COMPLETE, self::STATE_FAILED], true);
    }

    public function fail(string $reason): void
    {
        $this->state = self::STATE_FAILED;
        $this->error = $reason;
    }

    public function complete(): void
    {
        $this->state = self::STATE_COMPLETE;
        $this->error = null;
    }

    /**
     * A progress summary safe to return over the REST API and render in the
     * admin screen.
     *
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'direction' => $this->manifest->direction,
            'datasets' => $this->manifest->datasets,
            'counters' => $this->counters,
            'total_rows' => $this->totalRows(),
            'remapped_attachments' => count($this->attachmentMap),
            'error' => $this->error,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'manifest' => $this->manifest->toArray(),
            'state' => $this->state,
            'counters' => $this->counters,
            'attachment_map' => $this->attachmentMap,
            'error' => $this->error,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, DatasetRegistry $registry): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            manifest: TransferManifest::fromArray((array) ($data['manifest'] ?? []), $registry),
            state: (string) ($data['state'] ?? self::STATE_OPEN),
            counters: (array) ($data['counters'] ?? []),
            attachmentMap: array_map('intval', (array) ($data['attachment_map'] ?? [])),
            error: isset($data['error']) ? (string) $data['error'] : null,
            createdAt: (int) ($data['created_at'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? 0),
        );
    }
}
