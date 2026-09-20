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

        foreach ($manifest->datasets as $dataset) {
            if ($registry->get($dataset)->isTableBacked()) {
                $this->warn("{$dataset}: whole rows, real contact details included — "
                    ."those tables are emptied on {$target} and replaced, never merged.");
            }
        }

        $this->info('Pushing '.implode(', ', $manifest->datasets)." to {$target}...");

        try {
            // The callback is handed the phase and the same status array the
            // admin screen renders, not a counters map. Reading counters off it
            // directly is what made every CLI push die on an undefined key, and
            // die *inside the progress callback*, which replaced the real error
            // with a confusing one.
            $lastPhase = null;

            $result = $pusher->push($manifest, $target, function (string $phase, array $status) use (&$lastPhase) {
                if ($phase === $lastPhase) {
                    return;
                }

                $lastPhase = $phase;
                $this->line('  '.($status['label'] ?? $phase));
            });
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $sent = (array) ($result['sent'] ?? []);

        $removed = (int) ($sent['removed'] ?? 0);

        // Reported on its own line rather than folded into the summary: a clean
        // is the only part of a push that destroys anything, and how much it
        // destroyed is the number an operator needs before deciding whether the
        // run did what they meant.
        if ($removed > 0) {
            $this->warn("Cleaned {$removed} existing rows off {$target} before importing.");
        }

        $this->info(sprintf(
            'Done. Session %s — %d posts, %d meta, %d files, %d settings, %d undo entries.',
            $result['session_id'],
            (int) ($sent['posts'] ?? 0),
            (int) ($sent['meta'] ?? 0),
            (int) ($sent['files'] ?? 0),
            (int) ($sent['settings'] ?? 0),
            (int) ($result['undo_entries'] ?? 0),
        ));
        $this->line("Roll back with: wp acorn rl:sync:rollback {$result['session_id']} --target={$target}");

        return self::SUCCESS;
    }

    private function reportDryRun(DatasetRegistry $registry, TransferManifest $manifest, string $target): int
    {
        $exporter = app(ContentExporter::class);

        $this->info("Dry run — nothing sent to {$target}.");

        $replaced = [];

        foreach ($registry->importOrder($manifest->datasets) as $dataset) {
            $definition = $registry->get($dataset);

            // A table-backed dataset owns no posts, and a dataset that owns no
            // posts must not be counted in them: settings reported under a post
            // count read as though a settings push were about to ship the whole
            // site, and leads would report a flat zero.
            if ($definition->isTableBacked()) {
                $replaced[] = $dataset;

                foreach ($definition->transferTables as $table) {
                    $this->line(sprintf('  %-10s %d rows in %s  (replaced on the target)',
                        $dataset, $exporter->tableCount($table), $table));
                }

                continue;
            }

            [$count, $unit] = $dataset === DatasetRegistry::SETTINGS
                ? [count($exporter->settingsValues()), 'settings']
                : [$exporter->count($manifest, $dataset), 'posts'];

            $this->line(sprintf('  %-10s %d %s%s', $dataset, $count, $unit,
                $manifest->shouldClean($dataset) ? '  (target emptied first)' : ''));
        }

        if ($replaced !== []) {
            $this->warn(implode(', ', $replaced).' travel as whole rows, real contact details '
                ."included: those tables are emptied on {$target} and replaced, never merged.");
        }

        // The row count on the far side is not knowable without calling it, so
        // the dry run names what will be destroyed rather than quantifying it.
        // Saying nothing would let --clean pass a dry run looking identical to a
        // merge, which is the one difference the dry run exists to show.
        if ($manifest->cleanBeforeImport !== []) {
            $this->warn('Clean is on for '.implode(', ', $manifest->cleanBeforeImport)
                .": every existing row in those datasets is deleted on {$target} before the import, "
                .'including anything that exists only there.');
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
