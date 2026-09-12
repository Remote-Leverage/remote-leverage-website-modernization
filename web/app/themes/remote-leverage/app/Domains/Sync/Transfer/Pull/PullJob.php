<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Pull;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferManifest;

/**
 * The resumable state of a pull, held on the receiving side — which for a pull
 * is this environment.
 *
 * Deliberately parallel to PushJob rather than sharing a base with it. The two
 * look alike but mean different things at every field: a push's session lives
 * on the remote and its cursor walks local rows, while a pull's session is
 * local and its cursor walks the remote's. Collapsing them into one class would
 * need a flag on almost every method, and the flag would be the only thing
 * telling a reader which environment any given value refers to.
 */
final class PullJob
{
    public const PHASE_BEGIN = 'begin';

    public const PHASE_ROWS = 'rows';

    public const PHASE_MEDIA_MANIFEST = 'media-manifest';

    public const PHASE_MEDIA_FILES = 'media-files';

    public const PHASE_FINISH = 'finish';

    public const PHASE_DONE = 'done';

    public const PHASE_FAILED = 'failed';

    /**
     * @param  array<int, string>  $datasets
     * @param  array<int, array{path: string, size: int, sha256: string}>  $fileQueue
     * @param  array<string, int>  $counters
     */
    public function __construct(
        public readonly string $id,
        public readonly TransferManifest $manifest,
        public readonly string $source,
        public string $phase = self::PHASE_BEGIN,
        public string $sessionId = '',
        public array $datasets = [],
        public int $datasetIndex = 0,
        public int $cursor = 0,
        public array $fileQueue = [],
        public int $fileIndex = 0,
        public int $fileOffset = 0,
        public array $counters = ['posts' => 0, 'meta' => 0, 'files' => 0],
        public ?string $error = null,
        public readonly int $createdAt = 0,
        public int $updatedAt = 0,
    ) {}

    public function currentDataset(): ?string
    {
        return $this->datasets[$this->datasetIndex] ?? null;
    }

    /**
     * @return array{path: string, size: int, sha256: string}|null
     */
    public function currentFile(): ?array
    {
        return $this->fileQueue[$this->fileIndex] ?? null;
    }

    public function isFinished(): bool
    {
        return in_array($this->phase, [self::PHASE_DONE, self::PHASE_FAILED], true);
    }

    public function fail(string $reason): void
    {
        $this->phase = self::PHASE_FAILED;
        $this->error = $reason;
    }

    public function count(string $kind, int $by = 1): void
    {
        $this->counters[$kind] = ($this->counters[$kind] ?? 0) + $by;
    }

    /**
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase,
            'source' => $this->source,
            'session_id' => $this->sessionId,
            'dataset' => $this->currentDataset(),
            'counters' => $this->counters,
            'files_total' => count($this->fileQueue),
            'files_done' => $this->fileIndex,
            'finished' => $this->isFinished(),
            'error' => $this->error,
            'label' => $this->label(),
        ];
    }

    private function label(): string
    {
        return match ($this->phase) {
            self::PHASE_BEGIN => 'Opening a local session...',
            self::PHASE_ROWS => 'Fetching '.($this->currentDataset() ?? 'rows').' from '.$this->source.': '
                .$this->counters['posts'].' posts, '.$this->counters['meta'].' meta',
            self::PHASE_MEDIA_MANIFEST => 'Asking '.$this->source.' what media it has...',
            self::PHASE_MEDIA_FILES => 'Downloading media: '.$this->fileIndex.' of '.count($this->fileQueue),
            self::PHASE_FINISH => 'Closing the session...',
            self::PHASE_DONE => 'Done: '.$this->counters['posts'].' posts, '
                .$this->counters['meta'].' meta, '.$this->counters['files'].' files.',
            self::PHASE_FAILED => 'Failed: '.((string) $this->error),
            default => $this->phase,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'manifest' => $this->manifest->toArray(),
            'source' => $this->source,
            'phase' => $this->phase,
            'session_id' => $this->sessionId,
            'datasets' => $this->datasets,
            'dataset_index' => $this->datasetIndex,
            'cursor' => $this->cursor,
            'file_queue' => $this->fileQueue,
            'file_index' => $this->fileIndex,
            'file_offset' => $this->fileOffset,
            'counters' => $this->counters,
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
            source: (string) ($data['source'] ?? ''),
            phase: (string) ($data['phase'] ?? self::PHASE_BEGIN),
            sessionId: (string) ($data['session_id'] ?? ''),
            datasets: array_values(array_filter((array) ($data['datasets'] ?? []), 'is_string')),
            datasetIndex: (int) ($data['dataset_index'] ?? 0),
            cursor: (int) ($data['cursor'] ?? 0),
            fileQueue: array_values(array_filter((array) ($data['file_queue'] ?? []), 'is_array')),
            fileIndex: (int) ($data['file_index'] ?? 0),
            fileOffset: (int) ($data['file_offset'] ?? 0),
            counters: (array) ($data['counters'] ?? []),
            error: isset($data['error']) ? (string) $data['error'] : null,
            createdAt: (int) ($data['created_at'] ?? 0),
            updatedAt: (int) ($data['updated_at'] ?? 0),
        );
    }
}
