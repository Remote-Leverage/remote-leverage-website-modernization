<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferPuller;
use Illuminate\Console\Command;
use Throwable;

class PullTransferCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:pull
        {--source=staging : Remote environment to pull from}
        {--datasets=content,media : Comma-separated datasets to fetch}
        {--exclude-post-types= : Comma-separated post types to skip}
        {--exclude-ids= : Comma-separated post IDs to skip}';

    /**
     * @var string
     */
    protected $description = 'Pull selected datasets from a remote environment into this one.';

    public function handle(DatasetRegistry $registry, TransferPuller $puller): int
    {
        try {
            $manifest = TransferManifest::fromArray([
                'direction' => TransferManifest::PULL,
                'datasets' => $this->list('datasets'),
                'excluded_post_types' => $this->list('exclude-post-types'),
                'excluded_post_ids' => $this->list('exclude-ids'),
            ], $registry);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $source = (string) $this->option('source');

        $this->warn("This overwrites local data with {$source}'s.");

        if (! $this->confirm('Continue?', false)) {
            return self::SUCCESS;
        }

        try {
            $result = $puller->pull($manifest, $source, function (string $stage, array $status) {
                $this->line('  '.$stage.': '.json_encode($status['counters'] ?? $status));
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Session {$result['session_id']} — {$result['undo_entries']} undo entries.");
        $this->line("Roll back with: wp acorn rl:sync:rollback-local {$result['session_id']}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function list(string $option): array
    {
        $raw = (string) ($this->option($option) ?? '');

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }
}
