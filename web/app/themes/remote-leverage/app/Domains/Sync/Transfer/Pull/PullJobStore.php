<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Pull;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferManifest;

/**
 * Persists in-flight pulls in wp_options, so the browser's next poll resumes
 * exactly where the last one stopped.
 */
class PullJobStore
{
    public const RETENTION = 10;

    private const OPTION_PREFIX = 'rl_sync_pull_';

    private const INDEX_OPTION = 'rl_sync_pull_index';

    public function __construct(private readonly DatasetRegistry $registry) {}

    public function create(TransferManifest $manifest, string $source): PullJob
    {
        $now = time();

        $job = new PullJob(
            id: function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16)),
            manifest: $manifest,
            source: $source,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->save($job);

        $ids = $this->ids();
        $ids[] = $job->id;
        $this->writeIndex($ids);
        $this->prune();

        return $job;
    }

    public function find(string $id): ?PullJob
    {
        if (preg_match('/^[a-f0-9-]{16,64}$/i', $id) !== 1) {
            return null;
        }

        $data = get_option(self::OPTION_PREFIX.$id);

        return is_array($data) ? PullJob::fromArray($data, $this->registry) : null;
    }

    public function save(PullJob $job): void
    {
        $job->updatedAt = time();
        update_option(self::OPTION_PREFIX.$job->id, $job->toArray(), false);
    }

    /**
     * The job still in flight, if there is one.
     *
     * Only one transfer may be running at a time. Two overlapping runs would
     * interleave their writes on the target and, worse, interleave their undo
     * entries — rolling either one back would then restore rows the other had
     * legitimately changed.
     */
    public function active(): ?PullJob
    {
        foreach (array_reverse($this->ids()) as $id) {
            $job = $this->find($id);

            if ($job !== null && ! $job->isFinished()) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Abandon an in-flight job.
     *
     * Deliberately does not roll back: what already landed on the target may be
     * exactly what you wanted, and discarding it should be a separate, explicit
     * choice made from the session list.
     */
    public function cancel(string $id): bool
    {
        $job = $this->find($id);

        if ($job === null || $job->isFinished()) {
            return false;
        }

        $job->fail('Cancelled.');
        $this->save($job);

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        $index = get_option(self::INDEX_OPTION, []);

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }

    private function prune(): void
    {
        $ids = $this->ids();
        $excess = count($ids) - self::RETENTION;

        if ($excess <= 0) {
            return;
        }

        foreach (array_slice($ids, 0, $excess) as $id) {
            delete_option(self::OPTION_PREFIX.$id);
        }

        $this->writeIndex(array_slice($ids, $excess));
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function writeIndex(array $ids): void
    {
        update_option(self::INDEX_OPTION, array_values($ids), false);
    }
}
