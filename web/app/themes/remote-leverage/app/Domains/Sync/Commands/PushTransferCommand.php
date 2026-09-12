<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferPusher;
use Illuminate\Console\Command;
use Throwable;

class PushTransferCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:push
        {--target=staging : Remote environment from config/rl-sync.php}
        {--datasets=content,media : Comma-separated datasets to send}
        {--exclude-post-types= : Comma-separated post types to skip}
        {--exclude-ids= : Comma-separated post IDs to skip}
        {--clean= : Comma-separated datasets to empty on the target first}
        {--dry-run : Report what would be sent without sending it}';

    /**
     * @var string
     */
    protected $description = 'Push selected datasets from this environment to a remote one.';

    public function handle(DatasetRegistry $registry, TransferPusher $pusher): int
    {
        try {
            $manifest = TransferManifest::fromArray([
                'direction' => TransferManifest::PUSH,
                'datasets' => $this->list('datasets'),
                'excluded_post_types' => $this->list('exclude-post-types'),
                'excluded_post_ids' => $this->list('exclude-ids'),
                'clean_before_import' => $this->list('clean'),
            ], $registry);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $target = (string) $this->option('target');

        if ($manifest->hasDanglingMediaRisk()) {
            $this->warn('Content selected without media: the target will hold posts referencing '
                .'attachments it does not have.');
        }

        if ($this->option('dry-run')) {
            return $this->reportDryRun($registry, $manifest, $target);
        }

        $this->info('Pushing '.implode(', ', $manifest->datasets)." to {$target}...");

        try {
            $result = $pusher->push($manifest, $target, function (string $dataset, array $sent) {
                $this->line("  {$dataset}: {$sent['posts']} posts, {$sent['meta']} meta");
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Session {$result['session_id']} — {$result['sent']['posts']} posts, "
            ."{$result['sent']['meta']} meta, {$result['undo_entries']} undo entries.");
        $this->line("Roll back with: wp acorn rl:sync:rollback {$result['session_id']} --target={$target}");

        return self::SUCCESS;
    }

    private function reportDryRun(DatasetRegistry $registry, TransferManifest $manifest, string $target): int
    {
        $exporter = app(ContentExporter::class);

        $this->info("Dry run — nothing sent to {$target}.");

        foreach ($registry->importOrder($manifest->datasets) as $dataset) {
            $this->line(sprintf('  %-10s %d posts', $dataset, $exporter->count($manifest, $dataset)));
        }

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
