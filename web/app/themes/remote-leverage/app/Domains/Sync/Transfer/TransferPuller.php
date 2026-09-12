<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Media\MediaFileReceiver;
use Closure;
use RuntimeException;

/**
 * Pulls a remote environment's data into this one.
 *
 * The mirror image of TransferPusher, and the safer direction: everything
 * written lands *here*, on the environment the operator is sitting in, so a
 * mistake costs a local database rather than a shared one.
 *
 * The session, the importer and the undo log are all local — this environment
 * is the target — while the rows and files come from the remote's read-only
 * export abilities. That asymmetry is why pull is not simply push with the
 * arguments swapped.
 */
class TransferPuller
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

    /**
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     * @return array<string, mixed>
     */
    public function pull(TransferManifest $manifest, string $sourceEnv, ?callable $onProgress = null): array
    {
        SyncEnvironment::assertSyncEnabled();

        if ($manifest->isPush()) {
            throw new RuntimeException('TransferPuller only runs pull manifests.');
        }

        $client = $this->clientFactory !== null
            ? ($this->clientFactory)($sourceEnv)
            : new SyncClient($sourceEnv);

        $session = $this->sessions->create($manifest);
        $undo = $this->undoLogs->for($session->id);
        $session->state = TransferSession::STATE_IMPORTING;

        try {
            foreach ($this->registry->importOrder($manifest->datasets) as $dataset) {
                $this->pullDataset($client, $session, $undo, $manifest, $dataset, $onProgress);
            }

            if ($manifest->includes(DatasetRegistry::MEDIA)) {
                $this->pullMediaFiles($client, $session, $undo, $manifest, $onProgress);
            }
        } catch (RuntimeException $e) {
            $session->fail($e->getMessage());
            $this->sessions->save($session);

            throw new RuntimeException(
                $e->getMessage()."\nRoll back locally with: wp acorn rl:sync:rollback-local {$session->id}",
                previous: $e,
            );
        }

        $session->complete();
        $this->sessions->save($session);

        return [
            'session_id' => $session->id,
            'session' => $session->toStatusArray(),
            'undo_entries' => $undo->entryCount(),
        ];
    }

    /**
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     */
    private function pullDataset(
        SyncClient $client,
        TransferSession $session,
        UndoLog $undo,
        TransferManifest $manifest,
        string $dataset,
        ?callable $onProgress,
    ): void {
        $after = 0;

        while (true) {
            $batch = $client->run('app/export-transfer-batch', [
                'manifest' => $manifest->toArray(),
                'dataset' => $dataset,
                'after' => $after,
                'limit' => self::BATCH_SIZE,
            ]);

            if (($batch['ok'] ?? false) !== true) {
                throw new RuntimeException(
                    "The source refused a {$dataset} batch: ".($batch['error'] ?? 'unknown reason')
                );
            }

            if (($batch['done'] ?? false) === true) {
                return;
            }

            $this->importer->importBatch(
                $session,
                $dataset,
                array_values(array_filter((array) ($batch['posts'] ?? []), 'is_array')),
                array_values(array_filter((array) ($batch['meta'] ?? []), 'is_array')),
                $undo,
            );

            $this->sessions->save($session);

            if ($onProgress !== null) {
                $onProgress($dataset, $session->toStatusArray());
            }

            $after = (int) ($batch['last_id'] ?? 0);

            if ($after === 0) {
                return;
            }
        }
    }

    /**
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     */
    private function pullMediaFiles(
        SyncClient $client,
        TransferSession $session,
        UndoLog $undo,
        TransferManifest $manifest,
        ?callable $onProgress,
    ): void {
        $response = $client->run('app/export-media-manifest', ['manifest' => $manifest->toArray()]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'The source refused the media manifest: '.($response['error'] ?? 'unknown reason')
            );
        }

        $remote = array_values(array_filter((array) ($response['files'] ?? []), 'is_array'));
        $receiver = new MediaFileReceiver($this->files->baseDir());
        $missing = $receiver->missing($remote);

        if ($missing === []) {
            return;
        }

        $byPath = array_column($remote, null, 'path');
        $pulled = 0;

        foreach ($missing as $path) {
            if (! isset($byPath[$path])) {
                continue;
            }

            $this->pullOneFile($client, $receiver, $byPath[$path], $undo);
            $pulled++;
            $session->recordRows('media', 'files', 1);

            if ($onProgress !== null) {
                $onProgress('media-files', ['files' => $pulled, 'of' => count($missing)]);
            }
        }

        $this->sessions->save($session);
    }

    /**
     * @param  array{path: string, size: int, sha256: string}  $file
     */
    private function pullOneFile(
        SyncClient $client,
        MediaFileReceiver $receiver,
        array $file,
        UndoLog $undo,
    ): void {
        $offset = 0;
        $size = max(0, (int) $file['size']);

        do {
            $response = $client->run('app/read-media-file', [
                'path' => $file['path'],
                'offset' => $offset,
                'length' => self::FILE_CHUNK_BYTES,
            ]);

            if (($response['ok'] ?? false) !== true) {
                throw new RuntimeException(
                    "The source could not read {$file['path']}: ".($response['error'] ?? 'unknown reason')
                );
            }

            $offset += self::FILE_CHUNK_BYTES;
            $final = $offset >= $size;

            $receiver->writeChunk(
                $file['path'],
                $offset - self::FILE_CHUNK_BYTES,
                (string) ($response['data'] ?? ''),
                $final,
                $file['sha256'],
                $undo,
            );
        } while (! $final);
    }
}
