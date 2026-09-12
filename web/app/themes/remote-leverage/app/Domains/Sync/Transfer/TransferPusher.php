<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Datasets\DatasetRegistry as Datasets;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use Closure;
use RuntimeException;

/**
 * Drives a push from this environment to a remote one.
 *
 * Runs on the sending side — the only side that holds the remote's credentials
 * — and does nothing locally except read: every write happens on the target, in
 * response to the ability calls made here.
 *
 * Batches are deliberately small. The receiving end applies each one inside a
 * single PHP request with no long-running process available, so the batch size
 * is bounded by what that request can finish, not by what this side could send.
 */
class TransferPusher
{
    /**
     * Posts per chunk. Each carries its postmeta, so the real payload is a
     * multiple of this — conservative on purpose.
     */
    public const BATCH_SIZE = 25;

    /**
     * Bytes per media chunk. Base64 inflates by a third, so this is ~1.3MB on
     * the wire — comfortably inside a default PHP post_max_size.
     */
    public const FILE_CHUNK_BYTES = 1048576;

    /**
     * @param  Closure(string): SyncClient|null  $clientFactory  Override for tests;
     *                                                           production builds a real SyncClient.
     */
    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly ContentExporter $exporter,
        private readonly MediaFileExporter $files,
        private readonly ?Closure $clientFactory = null,
    ) {}

    /**
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     * @return array<string, mixed>
     */
    public function push(TransferManifest $manifest, string $targetEnv, ?callable $onProgress = null): array
    {
        SyncEnvironment::assertSyncEnabled();
        $this->assertTargetIsNotProduction($targetEnv);

        if (! $manifest->isPush()) {
            throw new RuntimeException('TransferPusher only runs push manifests.');
        }

        $client = $this->clientFactory !== null
            ? ($this->clientFactory)($targetEnv)
            : new SyncClient($targetEnv);

        $begin = $client->run('app/begin-transfer', ['manifest' => $manifest->toArray()]);

        if (($begin['ok'] ?? false) !== true) {
            throw new RuntimeException('The target refused the transfer: '.($begin['error'] ?? 'unknown reason'));
        }

        $sessionId = (string) ($begin['session']['id'] ?? '');
        $sent = ['posts' => 0, 'meta' => 0, 'files' => 0];

        try {
            foreach ($this->registry->importOrder($manifest->datasets) as $dataset) {
                $sent = $this->pushDataset($client, $sessionId, $manifest, $dataset, $sent, $onProgress);
            }

            if ($manifest->includes(Datasets::MEDIA)) {
                $sent['files'] = $this->pushMediaFiles($client, $sessionId, $manifest, $onProgress);
            }
        } catch (RuntimeException $e) {
            // Leave the session open and the undo log intact: the target can be
            // rolled back, which it could not be if this tidied up on the way out.
            throw new RuntimeException(
                $e->getMessage()."\nThe target still holds session {$sessionId}; roll it back to undo what landed.",
                previous: $e,
            );
        }

        $finish = $client->run('app/finish-transfer', ['session_id' => $sessionId, 'complete' => true]);

        return [
            'session_id' => $sessionId,
            'sent' => $sent,
            'session' => $finish['session'] ?? [],
            'undo_entries' => $finish['undo_entries'] ?? 0,
        ];
    }

    /**
     * @param  array{posts: int, meta: int}  $sent
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     * @return array{posts: int, meta: int}
     */
    private function pushDataset(
        SyncClient $client,
        string $sessionId,
        TransferManifest $manifest,
        string $dataset,
        array $sent,
        ?callable $onProgress,
    ): array {
        $after = 0;

        while (true) {
            $ids = $this->exporter->postIdBatch($manifest, $dataset, $after, self::BATCH_SIZE);

            if ($ids === []) {
                return $sent;
            }

            $posts = $this->exporter->postRows($ids);
            $meta = $this->exporter->postMetaRows($ids);

            $result = $client->run('app/receive-transfer-chunk', [
                'session_id' => $sessionId,
                'dataset' => $dataset,
                'posts' => $posts,
                'meta' => $meta,
            ]);

            if (($result['ok'] ?? false) !== true) {
                throw new RuntimeException(
                    "The target rejected a {$dataset} batch: ".($result['error'] ?? 'unknown reason')
                );
            }

            $sent['posts'] += count($posts);
            $sent['meta'] += count($meta);

            if ($onProgress !== null) {
                $onProgress($dataset, $sent);
            }

            $after = (int) end($ids);
        }
    }

    /**
     * Send the files behind the attachments, after their rows have landed.
     *
     * The target is asked what it is missing first. On a re-sync that is
     * usually almost nothing, which is the difference between a transfer that
     * moves a few files and one that re-uploads every image every time.
     *
     * @param  callable(string, array<string, mixed>): void|null  $onProgress
     */
    private function pushMediaFiles(
        SyncClient $client,
        string $sessionId,
        TransferManifest $manifest,
        ?callable $onProgress,
    ): int {
        $ids = [];
        $after = 0;

        while (($batch = $this->exporter->postIdBatch($manifest, Datasets::MEDIA, $after, self::BATCH_SIZE)) !== []) {
            $ids = array_merge($ids, $batch);
            $after = (int) end($batch);
        }

        $fileManifest = $this->files->manifest($this->files->pathsFor($ids));

        if ($fileManifest === []) {
            return 0;
        }

        $check = $client->run('app/check-media-files', ['files' => $fileManifest]);
        $missing = (array) ($check['missing'] ?? []);

        if ($missing === []) {
            return 0;
        }

        $byPath = array_column($fileManifest, null, 'path');
        $sentFiles = 0;

        foreach ($missing as $path) {
            if (! isset($byPath[$path])) {
                continue;
            }

            $this->pushOneFile($client, $sessionId, $byPath[$path]);
            $sentFiles++;

            if ($onProgress !== null) {
                $onProgress('media-files', ['files' => $sentFiles, 'of' => count($missing)]);
            }
        }

        return $sentFiles;
    }

    /**
     * @param  array{path: string, size: int, sha256: string}  $file
     */
    private function pushOneFile(SyncClient $client, string $sessionId, array $file): void
    {
        $offset = 0;
        $size = max(0, (int) $file['size']);

        do {
            $data = $this->files->readChunk($file['path'], $offset, self::FILE_CHUNK_BYTES);

            if ($data === null) {
                throw new RuntimeException("Could not read media file {$file['path']}.");
            }

            $offset += self::FILE_CHUNK_BYTES;
            $final = $offset >= $size;

            $result = $client->run('app/receive-media-file', [
                'session_id' => $sessionId,
                'path' => $file['path'],
                'offset' => $offset - self::FILE_CHUNK_BYTES,
                'data' => $data,
                'final' => $final,
                'sha256' => $file['sha256'],
            ]);

            if (($result['ok'] ?? false) !== true) {
                throw new RuntimeException(
                    "The target rejected media file {$file['path']}: ".($result['error'] ?? 'unknown reason')
                );
            }
        } while (! $final);
    }

    /**
     * Gate 3 of the four in docs/environment-sync.md §6.
     *
     * The target enforces its own refusal, but that only helps once the request
     * arrives. Checking the configured URL here means a mistyped target never
     * gets sent a single row of anything.
     */
    private function assertTargetIsNotProduction(string $targetEnv): void
    {
        if ($targetEnv === SyncEnvironment::PRODUCTION) {
            throw new RuntimeException('Refusing to push to production.');
        }

        $targetUrl = (string) config("rl-sync.environments.{$targetEnv}.url");
        $productionUrl = (string) config('rl-sync.environments.production.url');

        if ($targetUrl !== '' && $productionUrl !== '' && $this->sameHost($targetUrl, $productionUrl)) {
            throw new RuntimeException(
                "Refusing to push: \"{$targetEnv}\" is configured with the same host as production."
            );
        }
    }

    private function sameHost(string $a, string $b): bool
    {
        return strcasecmp((string) parse_url($a, PHP_URL_HOST), (string) parse_url($b, PHP_URL_HOST)) === 0;
    }
}
