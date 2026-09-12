<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\DatasetPurger;
use Illuminate\Console\Command;
use Throwable;

class PurgeDatasetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:sync:purge
        {dataset : leads, referrals or scheduling}
        {--on=local : "local", or a remote environment name}';

    /**
     * @var string
     */
    protected $description = 'Delete every row in a purgeable dataset. Not undoable.';

    public function handle(DatasetPurger $purger): int
    {
        $dataset = (string) $this->argument('dataset');
        $on = (string) $this->option('on');

        return $on === 'local'
            ? $this->purgeLocally($purger, $dataset)
            : $this->purgeRemotely($dataset, $on);
    }

    private function purgeLocally(DatasetPurger $purger, string $dataset): int
    {
        try {
            $preview = $purger->preview($dataset);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->reportCounts($preview);

        $env = SyncEnvironment::current();

        if ($this->ask('Type the environment name to confirm deletion on this environment') !== $env) {
            $this->info('Confirmation did not match. Nothing was deleted.');

            return self::SUCCESS;
        }

        try {
            $this->reportCounts($purger->purge($dataset), 'Deleted');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function purgeRemotely(string $dataset, string $target): int
    {
        $this->warn("This deletes all {$dataset} rows on {$target}, and cannot be undone.");

        if ($this->ask("Type \"{$target}\" to confirm") !== $target) {
            $this->info('Confirmation did not match. Nothing was deleted.');

            return self::SUCCESS;
        }

        try {
            $result = (new SyncClient($target))->run('app/purge-dataset', [
                'dataset' => $dataset,
                'confirm_environment' => $target,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (($result['ok'] ?? false) !== true) {
            $this->error((string) ($result['error'] ?? 'Purge failed.'));

            return self::FAILURE;
        }

        $this->reportCounts((array) $result['deleted'], 'Deleted');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function reportCounts(array $counts, string $verb = 'Holds'): void
    {
        foreach ($counts as $table => $count) {
            $this->line($count < 0
                ? sprintf('  %-28s (table not present)', $table)
                : sprintf('  %-28s %s %d rows', $table, $verb, $count));
        }
    }
}
