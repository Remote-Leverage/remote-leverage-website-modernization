<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\WordPress\Security\WordfenceConfigurator;
use Illuminate\Console\Command;

/**
 * Apply `config/wordfence.php` to WordFence.
 *
 * Runs automatically from `rl:deploy` on every container start; this command exists so it can
 * also be run on demand, and so `--dry-run` can answer "what has drifted?" without writing.
 */
class ApplyWordfenceConfigCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:wordfence
        {--dry-run : Report what would change without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Apply the WordFence settings in config/wordfence.php. Idempotent; '.
        'the repository is the source of truth and wp-admin drift is reverted.';

    public function handle(WordfenceConfigurator $configurator): int
    {
        if ($this->option('dry-run')) {
            return $this->reportDrift($configurator);
        }

        $result = $configurator->apply();

        if ($result['skipped'] !== null) {
            $this->warn('WordFence config skipped: '.$result['skipped']);

            return self::SUCCESS;
        }

        foreach ($result['applied'] as $key => $value) {
            $this->line("  set      {$key} = ".$this->display($value));
        }

        if ($result['unknown'] !== []) {
            // Not a failure: a key WordFence does not recognise is almost always a typo or a
            // setting from another version, and writing it would create a row nothing reads.
            $this->warn('  skipped unknown key(s): '.implode(', ', $result['unknown']));
        }

        $this->info(sprintf(
            'WordFence: %d changed, %d already correct%s.',
            count($result['applied']),
            count($result['unchanged']),
            $result['unknown'] === [] ? '' : ', '.count($result['unknown']).' unknown',
        ));

        return self::SUCCESS;
    }

    /**
     * Report drift without writing.
     *
     * Implemented by comparing against the same configurator rather than a second code path,
     * so a dry run cannot disagree with what a real run would do.
     */
    private function reportDrift(WordfenceConfigurator $configurator): int
    {
        if (! $configurator->available()) {
            $this->warn('WordFence is not loaded on this environment; nothing to compare.');

            return self::SUCCESS;
        }

        $this->comment('Dry run — nothing will be written.');

        $probe = new class extends WordfenceConfigurator
        {
            /** @var array<string, mixed> */
            public array $wouldWrite = [];

            protected function set(string $key, mixed $value): void
            {
                $this->wouldWrite[$key] = $value;
            }
        };

        $result = $probe->apply();

        if ($result['skipped'] !== null) {
            $this->warn('WordFence config skipped: '.$result['skipped']);

            return self::SUCCESS;
        }

        foreach ($probe->wouldWrite as $key => $value) {
            $this->line("  would set  {$key} = ".$this->display($value));
        }

        if ($result['unknown'] !== []) {
            $this->warn('  unknown key(s): '.implode(', ', $result['unknown']));
        }

        $this->info(sprintf(
            '%d would change, %d already correct.',
            count($probe->wouldWrite),
            count($result['unchanged']),
        ));

        return self::SUCCESS;
    }

    private function display(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => gettype($value),
        };
    }
}
