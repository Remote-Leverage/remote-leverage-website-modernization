<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Everything that must run once against the database after new code is
 * deployed. Called from docker/entrypoint.sh on container start.
 *
 * Why a command rather than a list of `wp` calls in the entrypoint:
 *
 * ECS rolling deploys can start several tasks at once, so these steps need to
 * be mutually exclusive across containers, not just within one. Acorn's cache
 * store is `file`, which means `migrate --isolated` locks per-container
 * filesystem and gives no cross-task protection at all. A MySQL named lock is
 * held on the connection, is visible to every task pointing at the same
 * database, and is released automatically if the container dies mid-run — so
 * the work is serialised here instead.
 */
class RunDeployTasksCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:deploy
        {--timeout=120 : Seconds to wait for another container to finish first}';

    /**
     * @var string
     */
    protected $description = 'Run post-deploy database tasks (migrations, rewrite rules) under a cross-container lock.';

    private const LOCK_NAME = 'rl_deploy_tasks';

    public function handle(): int
    {
        $timeout = max(1, (int) $this->option('timeout'));

        if (! $this->acquireLock($timeout)) {
            // Another container is mid-deploy. It is running the same code, so
            // the work will be done — this task just must not race it.
            $this->info('Another container holds the deploy lock; skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runTasks();
        } catch (Throwable $e) {
            $this->error('Deploy tasks failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $this->releaseLock();
        }
    }

    private function runTasks(): int
    {
        $this->info('Running database migrations...');

        if (Artisan::call('migrate', ['--force' => true], $this->getOutput()) !== 0) {
            $this->error('Migrations reported a failure.');

            return self::FAILURE;
        }

        $this->flushRewriteRules();

        $this->info('Deploy tasks complete.');

        return self::SUCCESS;
    }

    /**
     * Custom rewrite rules only reach the stored rewrite_rules option after a
     * flush. PartnerPostType adds /partners/{slug}/{tab} via add_rewrite_rule(),
     * and nothing else in the theme ever flushes — without this the rule is
     * registered in code but absent from the option, and the URL 404s.
     */
    private function flushRewriteRules(): void
    {
        if (! function_exists('flush_rewrite_rules')) {
            $this->warn('WordPress not loaded; skipped rewrite rule flush.');

            return;
        }

        $this->info('Flushing rewrite rules...');

        // Soft flush: this runs behind nginx, so there is no .htaccess to write.
        flush_rewrite_rules(false);
    }

    /**
     * MySQL GET_LOCK is scoped to the connection, so this must be acquired and
     * released on the same PDO handle the work runs on — which it is, since
     * everything here uses the default connection in one process.
     */
    private function acquireLock(int $timeout): bool
    {
        try {
            $result = DB::selectOne(
                'SELECT GET_LOCK(?, ?) AS acquired',
                [self::LOCK_NAME, $timeout],
            );
        } catch (Throwable $e) {
            // A database that cannot take a lock cannot be migrated either.
            $this->error('Could not acquire the deploy lock: '.$e->getMessage());

            return false;
        }

        return (int) ($result->acquired ?? 0) === 1;
    }

    private function releaseLock(): void
    {
        try {
            DB::selectOne('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        } catch (Throwable) {
            // The lock expires with the connection; nothing useful to do here.
        }
    }
}
