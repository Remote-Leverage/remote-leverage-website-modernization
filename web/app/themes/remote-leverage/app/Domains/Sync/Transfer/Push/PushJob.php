<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Push;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferManifest;

/**
 * The resumable state of a push, held on the sending side.
 *
 * A push is a long conversation — open a session, walk the rows, negotiate the
 * media manifest, upload files, close — and running it inside one request is
 * what produced a 504: nginx gives up long before the work does, and no
 * set_time_limit() can change that, because the proxy is what timed out, not PHP.
 *
 * So the conversation is broken into steps, each small enough to finish well
 * inside a normal request, and this is the bookmark between them. The browser
 * (or the CLI) drives the loop; every step leaves the job safe to resume.
 */
final class PushJob
{
    public const PHASE_BEGIN = 'begin';

    public const PHASE_ROWS = 'rows';

    public const PHASE_MEDIA_CHECK = 'media-check';

    public const PHASE_MEDIA_FILES = 'media-files';

    public const PHASE_FINISH = 'finish';

    public const PHASE_DONE = 'done';

    public const PHASE_FAILED = 'failed';

    /**
     * @param  array<int, string>  $datasets  Ordered for import; media first.
     * @param  array<int, array{path: string, size: int, sha256: string}>  $fileQueue
     * @param  array<string, int>  $counters
     */
    public function __construct(
        public readonly string $id,
        public readonly TransferManifest $manifest,
        public readonly string $target,
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
     * Progress in a shape the admin screen can render directly.
     *
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase,
            'target' => $this->target,
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
            self::PHASE_BEGIN => 'Opening a session on '.$this->target.'...',
            self::PHASE_ROWS => 'Sending '.($this->currentDataset() ?? 'rows').': '
                .$this->counters['posts'].' posts, '.$this->counters['meta'].' meta',
            self::PHASE_MEDIA_CHECK => 'Asking '.$this->target.' which media files it needs...',
            self::PHASE_MEDIA_FILES => 'Uploading media: '.$this->fileIndex.' of '.count($this->fileQueue),
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
            'target' => $this->target,
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
            target: (string) ($data['target'] ?? ''),
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
