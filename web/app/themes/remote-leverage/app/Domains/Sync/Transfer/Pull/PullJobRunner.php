<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Pull;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Media\MediaFileReceiver;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Advances a pull by exactly one step.
 *
 * Same contract as PushJobRunner — bounded work, no loops, resume-safe — for
 * the same reason: a pull from the browser would hit the identical 504 if it
 * tried to finish in one request.
 *
 * Everything written lands locally, recorded in this environment's own undo
 * log, so a pull is undone from here rather than from the remote.
 */
class PullJobRunner
{
    public const BATCH_SIZE = 25;

    public const FILE_CHUNK_BYTES = 1048576;

    /**
     * @param  Closure(string): SyncClient|null  $clientFactory
     */
    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly SessionStore $sessions,
        private readonly ContentImporter $importer,
        private readonly UndoLogFactory $undoLogs,
        private readonly MediaFileExporter $files,
        private readonly ?Closure $clientFactory = null,
    ) {}

    public function step(PullJob $job): PullJob
    {
        if ($job->isFinished()) {
            return $job;
        }

        try {
            $client = $this->client($job->source);

            match ($job->phase) {
                PullJob::PHASE_BEGIN => $this->begin($job),
                PullJob::PHASE_ROWS => $this->rows($client, $job),
                PullJob::PHASE_MEDIA_MANIFEST => $this->mediaManifest($client, $job),
                PullJob::PHASE_MEDIA_FILES => $this->mediaFile($client, $job),
                PullJob::PHASE_FINISH => $this->finish($job),
                default => $job->fail('Unknown pull phase: '.$job->phase),
            };
        } catch (Throwable $e) {
            $job->fail($e->getMessage());
        }

        return $job;
    }

    private function begin(PullJob $job): void
    {
        $session = $this->sessions->create($job->manifest);

        $job->sessionId = $session->id;
        $job->datasets = $this->registry->importOrder($job->manifest->datasets);
        $job->datasetIndex = 0;
        $job->cursor = 0;
        $job->phase = $job->datasets === [] ? PullJob::PHASE_FINISH : PullJob::PHASE_ROWS;
    }

    private function rows(SyncClient $client, PullJob $job): void
    {
        $dataset = $job->currentDataset();

        if ($dataset === null) {
            $job->phase = $this->afterRows($job);

            return;
        }

        $batch = $client->run('app/export-transfer-batch', [
            'manifest' => $job->manifest->toArray(),
            'dataset' => $dataset,
            'after' => $job->cursor,
            'limit' => self::BATCH_SIZE,
        ]);

        if (($batch['ok'] ?? false) !== true) {
            throw new RuntimeException("The source refused a {$dataset} batch: "
                .($batch['error'] ?? 'unknown reason'));
        }

        if (($batch['done'] ?? false) === true) {
            $job->datasetIndex++;
            $job->cursor = 0;

            if ($job->currentDataset() === null) {
                $job->phase = $this->afterRows($job);
            }

            return;
        }

        $session = $this->session($job);
        $posts = array_values(array_filter((array) ($batch['posts'] ?? []), 'is_array'));
        $meta = array_values(array_filter((array) ($batch['meta'] ?? []), 'is_array'));

        $this->importer->importBatch(
            $session,
            $dataset,
            $posts,
            $meta,
            $this->undoLogs->for($job->sessionId),
        );

        $this->sessions->save($session);

        $job->count('posts', count($posts));
        $job->count('meta', count($meta));
        $job->cursor = (int) ($batch['last_id'] ?? 0);

        if ($job->cursor === 0) {
            $job->datasetIndex++;

            if ($job->currentDataset() === null) {
                $job->phase = $this->afterRows($job);
            }
        }
    }

    private function afterRows(PullJob $job): string
    {
        return $job->manifest->includes(DatasetRegistry::MEDIA)
            ? PullJob::PHASE_MEDIA_MANIFEST
            : PullJob::PHASE_FINISH;
    }

    private function mediaManifest(SyncClient $client, PullJob $job): void
    {
        $response = $client->run('app/export-media-manifest', ['manifest' => $job->manifest->toArray()]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException('The source refused the media manifest: '
                .($response['error'] ?? 'unknown reason'));
        }

        $remote = array_values(array_filter((array) ($response['files'] ?? []), 'is_array'));
        $missing = (new MediaFileReceiver($this->files->baseDir()))->missing($remote);
        $byPath = array_column($remote, null, 'path');

        $job->fileQueue = array_values(array_filter(array_map(
            fn ($path) => $byPath[$path] ?? null,
            $missing,
        )));
        $job->fileIndex = 0;
        $job->fileOffset = 0;
        $job->phase = $job->fileQueue === [] ? PullJob::PHASE_FINISH : PullJob::PHASE_MEDIA_FILES;
    }

    private function mediaFile(SyncClient $client, PullJob $job): void
    {
        $file = $job->currentFile();

        if ($file === null) {
            $job->phase = PullJob::PHASE_FINISH;

            return;
        }

        $response = $client->run('app/read-media-file', [
            'path' => $file['path'],
            'offset' => $job->fileOffset,
            'length' => self::FILE_CHUNK_BYTES,
        ]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException("The source could not read {$file['path']}: "
                .($response['error'] ?? 'unknown reason'));
        }

        $nextOffset = $job->fileOffset + self::FILE_CHUNK_BYTES;
        $final = $nextOffset >= max(0, (int) $file['size']);

        (new MediaFileReceiver($this->files->baseDir()))->writeChunk(
            $file['path'],
            $job->fileOffset,
            (string) ($response['data'] ?? ''),
            $final,
            $file['sha256'],
            $this->undoLogs->for($job->sessionId),
        );

        if ($final) {
            $job->count('files');
            $job->fileIndex++;
            $job->fileOffset = 0;

            if ($job->currentFile() === null) {
                $job->phase = PullJob::PHASE_FINISH;
            }

            return;
        }

        $job->fileOffset = $nextOffset;
    }

    private function finish(PullJob $job): void
    {
        $session = $this->session($job);
        $session->complete();
        $this->sessions->save($session);

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        $job->phase = PullJob::PHASE_DONE;
    }

    private function session(PullJob $job): TransferSession
    {
        $session = $this->sessions->find($job->sessionId);

        if ($session === null) {
            throw new RuntimeException('The local transfer session has gone missing.');
        }

        return $session;
    }

    private function client(string $source): SyncClient
    {
        return $this->clientFactory !== null
            ? ($this->clientFactory)($source)
            : new SyncClient($source);
    }
}
