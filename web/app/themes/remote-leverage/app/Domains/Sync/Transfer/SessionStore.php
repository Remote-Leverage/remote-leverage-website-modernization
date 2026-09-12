<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;

/**
 * Persists transfer sessions in wp_options.
 *
 * Deliberately not a database table. Nothing in this project runs migrations
 * on a schedule the sync can rely on — until RunDeployTasksCommand landed,
 * migrations had never run on staging at all — and a sync feature that only
 * works after someone remembers to migrate the target is a feature that does
 * not work. Options exist on every WordPress install, always.
 *
 * Sessions are kept to the RETENTION most recent, oldest pruned on create,
 * so an abandoned run cannot accumulate rows in wp_options forever.
 */
class SessionStore
{
    /**
     * Runs kept before the oldest is discarded.
     */
    public const RETENTION = 20;

    private const OPTION_PREFIX = 'rl_sync_session_';

    private const INDEX_OPTION = 'rl_sync_session_index';

    public function __construct(private readonly DatasetRegistry $registry) {}

    public function create(TransferManifest $manifest): TransferSession
    {
        $now = time();

        $session = new TransferSession(
            id: $this->newId(),
            manifest: $manifest,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->save($session);
        $this->addToIndex($session->id);
        $this->prune();

        return $session;
    }

    public function find(string $id): ?TransferSession
    {
        if (! $this->isValidId($id)) {
            return null;
        }

        $data = get_option(self::OPTION_PREFIX.$id);

        if (! is_array($data)) {
            return null;
        }

        return TransferSession::fromArray($data, $this->registry);
    }

    public function save(TransferSession $session): void
    {
        $session->updatedAt = time();

        // autoload "no": these can be large and are only read by the sync
        // itself, so they must not be loaded on every front-end request.
        update_option(self::OPTION_PREFIX.$session->id, $session->toArray(), false);
    }

    public function delete(string $id): void
    {
        if ($this->isValidId($id)) {
            delete_option(self::OPTION_PREFIX.$id);
            $this->removeFromIndex($id);
        }
    }

    /**
     * Session IDs, oldest first.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        $index = get_option(self::INDEX_OPTION, []);

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }

    /**
     * Sessions newest first, for the admin screen's history list.
     *
     * @return array<int, TransferSession>
     */
    public function recent(int $limit = 10): array
    {
        $sessions = [];

        foreach (array_reverse($this->ids()) as $id) {
            if (count($sessions) >= $limit) {
                break;
            }

            $session = $this->find($id);

            if ($session instanceof TransferSession) {
                $sessions[] = $session;
            }
        }

        return $sessions;
    }

    /**
     * Drop the oldest sessions beyond RETENTION.
     */
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

    private function addToIndex(string $id): void
    {
        $ids = $this->ids();
        $ids[] = $id;
        $this->writeIndex($ids);
    }

    private function removeFromIndex(string $id): void
    {
        $this->writeIndex(array_values(array_diff($this->ids(), [$id])));
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function writeIndex(array $ids): void
    {
        update_option(self::INDEX_OPTION, array_values($ids), false);
    }

    private function newId(): string
    {
        return function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : bin2hex(random_bytes(16));
    }

    /**
     * Session IDs reach this class from REST input and are concatenated into an
     * option name, so anything but the generated shape is refused.
     */
    private function isValidId(string $id): bool
    {
        return preg_match('/^[a-f0-9-]{16,64}$/i', $id) === 1;
    }
}
