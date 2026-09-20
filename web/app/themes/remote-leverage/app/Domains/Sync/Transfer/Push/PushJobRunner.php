<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Push;

use App\Domains\Sync\Datasets\Dataset;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Advances a push by exactly one step.
 *
 * Every branch here does a bounded amount of work — one batch of rows, one
 * chunk of one file, one negotiation — and then returns. Nothing loops. That is
 * the whole point: the caller decides how many steps to run and how long to
 * spend, so a browser can take one step per request and never approach the
 * proxy timeout, while the CLI can take them back to back.
 */
class PushJobRunner
{
    public const BATCH_SIZE = 25;

    public const FILE_CHUNK_BYTES = 1048576;

    /**
     * @param  Closure(string): SyncClient|null  $clientFactory
     */
    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly ContentExporter $exporter,
        private readonly MediaFileExporter $files,
        private readonly ?Closure $clientFactory = null,
    ) {}

    /**
     * Perform one step and return the job, advanced.
     *
     * A failure is recorded on the job rather than thrown: the caller is a
     * polling loop that needs to render the error, and the remote session must
     * stay open so it can still be rolled back.
     */
    public function step(PushJob $job): PushJob
    {
        if ($job->isFinished()) {
            return $job;
        }

        try {
            $client = $this->client($job->target);

            match ($job->phase) {
                PushJob::PHASE_BEGIN => $this->begin($client, $job),
                PushJob::PHASE_ROWS => $this->rows($client, $job),
                PushJob::PHASE_SETTINGS => $this->settings($client, $job),
                PushJob::PHASE_MEDIA_CHECK => $this->mediaCheck($client, $job),
                PushJob::PHASE_MEDIA_FILES => $this->mediaFile($client, $job),
                PushJob::PHASE_FINISH => $this->finish($client, $job),
                default => $job->fail('Unknown push phase: '.$job->phase),
            };
        } catch (Throwable $e) {
            $job->fail($e->getMessage());
        }

        return $job;
    }

    private function begin(SyncClient $client, PushJob $job): void
    {
        $response = $client->run('app/begin-transfer', ['manifest' => $job->manifest->toArray()]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException('The target refused the transfer: '
                .($response['error'] ?? 'unknown reason'));
        }

        $job->sessionId = (string) ($response['session']['id'] ?? '');
        $job->datasets = $this->registry->importOrder($job->manifest->datasets);

        // Counted once, here, so the progress bar has a denominator from the
        // first step rather than appearing to stall at an unknown total.
        $posts = 0;

        foreach ($job->datasets as $dataset) {
            $posts += $this->rowTotal($job, $dataset);
        }

        $job->totals = ['posts' => $posts, 'files' => 0];
        $job->datasetIndex = 0;
        $job->tableIndex = 0;
        $job->cursor = 0;
        $job->phase = $job->datasets === [] ? PushJob::PHASE_FINISH : PushJob::PHASE_ROWS;
    }

    /**
     * How many rows this dataset will send, for the progress denominator.
     *
     * A table-backed dataset owns no posts, so asking the post exporter would
     * count the whole content set instead.
     */
    private function rowTotal(PushJob $job, string $dataset): int
    {
        $definition = $this->registry->get($dataset);

        if (! $definition->isTableBacked()) {
            return $this->exporter->count($job->manifest, $dataset);
        }

        $total = 0;

        foreach ($definition->transferTables as $table) {
            $total += $this->exporter->tableCount($table);
        }

        return $total;
    }

    private function rows(SyncClient $client, PushJob $job): void
    {
        $dataset = $job->currentDataset();

        if ($dataset === null) {
            $job->phase = $this->afterRows($job);

            return;
        }

        if ($this->registry->get($dataset)->isTableBacked()) {
            $this->tableRows($client, $job, $dataset);

            return;
        }

        $ids = $this->exporter->postIdBatch($job->manifest, $dataset, $job->cursor, self::BATCH_SIZE);

        if ($ids === []) {
            $this->nextDataset($job);

            return;
        }

        $posts = $this->exporter->postRows($ids);
        $meta = $this->exporter->postMetaRows($ids);

        $response = $client->run('app/receive-transfer-chunk', [
            'session_id' => $job->sessionId,
            'dataset' => $dataset,
            'posts' => $posts,
            'meta' => $meta,
        ]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException("The target rejected a {$dataset} batch: "
                .($response['error'] ?? 'unknown reason'));
        }

        $job->count('posts', count($posts));
        $job->count('meta', count($meta));
        $job->counters['removed'] = $this->removedOnTarget($response);
        $job->cursor = (int) end($ids);
    }

    /**
     * Send one batch of whole rows from the current transfer table.
     *
     * A dataset with several tables needs a cursor per table, which is what
     * tableIndex is: cursor walks ids inside one table, tableIndex walks the
     * tables, datasetIndex walks the datasets. All three are persisted, so a
     * resumed job picks up inside the table it was in rather than starting the
     * dataset again.
     *
     * The opening batch of each table is sent even when the table is empty. It
     * is what tells the target to empty its own copy, and skipping it is how a
     * source with no activity logs would leave the target's in place and call
     * the dataset mirrored.
     */
    private function tableRows(SyncClient $client, PushJob $job, string $dataset): void
    {
        $tables = $this->registry->get($dataset)->transferTables;
        $table = $tables[$job->tableIndex] ?? null;

        if ($table === null) {
            $this->nextDataset($job);

            return;
        }

        $rows = $this->exporter->tableRowBatch($table, $job->cursor, self::BATCH_SIZE);

        if ($rows === [] && $job->cursor > 0) {
            $this->nextTable($job, $tables);

            return;
        }

        $response = $client->run('app/receive-transfer-chunk', [
            'session_id' => $job->sessionId,
            'dataset' => $dataset,
            'table' => $table,
            'rows' => $rows,
        ]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException("The target rejected a {$table} batch: "
                .($response['error'] ?? 'unknown reason'));
        }

        $job->count('posts', count($rows));
        $job->counters['removed'] = $this->removedOnTarget($response);

        if ($rows === []) {
            $this->nextTable($job, $tables);

            return;
        }

        $job->cursor = (int) $rows[count($rows) - 1][Dataset::TRANSFER_KEY];
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function nextTable(PushJob $job, array $tables): void
    {
        $job->tableIndex++;
        $job->cursor = 0;

        if ($job->tableIndex >= count($tables)) {
            $this->nextDataset($job);
        }
    }

    private function nextDataset(PushJob $job): void
    {
        $job->datasetIndex++;
        $job->tableIndex = 0;
        $job->cursor = 0;

        if ($job->currentDataset() === null) {
            $job->phase = $this->afterRows($job);
        }
    }

    /**
     * How many rows the target has deleted so far for this transfer.
     *
     * Assigned rather than accumulated: the target reports its own running
     * total on every chunk, so adding them up would multiply one clean by the
     * number of batches that followed it.
     *
     * @param  array<string, mixed>  $response
     */
    private function removedOnTarget(array $response): int
    {
        $counters = (array) (($response['session'] ?? [])['counters'] ?? []);
        $removed = 0;

        foreach ($counters as $kinds) {
            $removed += (int) (((array) $kinds)['removed'] ?? 0);
        }

        return $removed;
    }

    private function afterRows(PushJob $job): string
    {
        return $job->manifest->includes(DatasetRegistry::SETTINGS)
            ? PushJob::PHASE_SETTINGS
            : $this->afterSettings($job);
    }

    private function afterSettings(PushJob $job): string
    {
        return $job->manifest->includes(DatasetRegistry::MEDIA)
            ? PushJob::PHASE_MEDIA_CHECK
            : PushJob::PHASE_FINISH;
    }

    /**
     * Send the whitelisted wp_options in one bounded call.
     *
     * Settings reuse the settings ability rather than the row pipeline, because
     * they are option rows, there are a handful of them, and that ability
     * already re-checks every key against the target's own whitelist.
     *
     * A key the target refuses is a hard failure rather than a warning. It means
     * the two environments are running different config, and finishing quietly
     * would report a successful push while some settings never arrived — the
     * exact failure mode this phase exists to end.
     */
    private function settings(SyncClient $client, PushJob $job): void
    {
        $values = $this->exporter->settingsValues();

        if ($values === []) {
            $job->phase = $this->afterSettings($job);

            return;
        }

        $response = $client->run('app/import-syncable-settings', ['values' => $values]);
        $rejected = array_values((array) ($response['rejected'] ?? []));

        if ($rejected !== []) {
            throw new RuntimeException(
                'The target refused settings keys absent from its whitelist: '
                .implode(', ', array_map('strval', $rejected))
                .'. The two environments are on different config.'
            );
        }

        $job->count('settings', count((array) ($response['updated'] ?? [])));
        $job->phase = $this->afterSettings($job);
    }

    private function mediaCheck(SyncClient $client, PushJob $job): void
    {
        $ids = [];
        $after = 0;

        while (($batch = $this->exporter->postIdBatch(
            $job->manifest, DatasetRegistry::MEDIA, $after, 200
        )) !== []) {
            $ids = array_merge($ids, $batch);
            $after = (int) end($batch);
        }

        $manifest = $this->files->manifest($this->files->pathsFor($ids));

        if ($manifest === []) {
            $job->phase = PushJob::PHASE_FINISH;

            return;
        }

        $response = $client->run('app/check-media-files', ['files' => $manifest]);
        $missing = (array) ($response['missing'] ?? []);
        $byPath = array_column($manifest, null, 'path');

        $job->fileQueue = array_values(array_filter(array_map(
            fn ($path) => $byPath[$path] ?? null,
            $missing,
        )));
        $job->fileIndex = 0;
        $job->fileOffset = 0;
        $job->totals['files'] = count($job->fileQueue);
        $job->phase = $job->fileQueue === [] ? PushJob::PHASE_FINISH : PushJob::PHASE_MEDIA_FILES;
    }

    /**
     * Send one chunk of the current file.
     *
     * Chunk-at-a-time rather than file-at-a-time so a single large video cannot
     * blow the step budget on its own.
     */
    private function mediaFile(SyncClient $client, PushJob $job): void
    {
        $file = $job->currentFile();

        if ($file === null) {
            $job->phase = PushJob::PHASE_FINISH;

            return;
        }

        $data = $this->files->readChunk($file['path'], $job->fileOffset, self::FILE_CHUNK_BYTES);

        if ($data === null) {
            throw new RuntimeException("Could not read media file {$file['path']}.");
        }

        $nextOffset = $job->fileOffset + self::FILE_CHUNK_BYTES;
        $final = $nextOffset >= max(0, (int) $file['size']);

        $response = $client->run('app/receive-media-file', [
            'session_id' => $job->sessionId,
            'path' => $file['path'],
            'offset' => $job->fileOffset,
            'data' => $data,
            'final' => $final,
            'sha256' => $file['sha256'],
        ]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException("The target rejected media file {$file['path']}: "
                .($response['error'] ?? 'unknown reason'));
        }

        if ($final) {
            $job->count('files');
            $job->fileIndex++;
            $job->fileOffset = 0;

            if ($job->currentFile() === null) {
                $job->phase = PushJob::PHASE_FINISH;
            }

            return;
        }

        $job->fileOffset = $nextOffset;
    }

    private function finish(SyncClient $client, PushJob $job): void
    {
        $response = $client->run('app/finish-transfer', [
            'session_id' => $job->sessionId,
            'complete' => true,
        ]);

        // Carried through so the operator is told how much is undoable without
        // having to go and ask the target separately.
        $job->counters['undo_entries'] = (int) ($response['undo_entries'] ?? 0);
        $job->phase = PushJob::PHASE_DONE;
    }

    private function client(string $target): SyncClient
    {
        return $this->clientFactory !== null
            ? ($this->clientFactory)($target)
            : new SyncClient($target);
    }
}
