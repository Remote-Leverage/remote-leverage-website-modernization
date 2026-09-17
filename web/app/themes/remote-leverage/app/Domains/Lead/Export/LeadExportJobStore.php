<?php

declare(strict_types=1);

namespace App\Domains\Lead\Export;

/**
 * Persists in-flight exports in wp_options, so the browser's next batch picks up where the last
 * one stopped.
 *
 * The same shape as the Sync domain's PushJobStore — named, not imported, because the Lead
 * domain has no business depending on Sync — and for the same reason: there is no queue worker
 * in this application, so "background work" means a series of short requests that each has to
 * find the state the previous one left. Options are written
 * with autoload off — a finished export has no business being loaded into every page render.
 */
class LeadExportJobStore
{
    /** How many finished exports to keep before the oldest is deleted, file and all. */
    public const RETENTION = 5;

    /** How long a job may sit unfinished before it is treated as abandoned. */
    public const STALE_AFTER = 3600;

    private const OPTION_PREFIX = 'rl_lead_export_';

    private const INDEX_OPTION = 'rl_lead_export_index';

    private const DIRECTORY = 'rl-lead-exports';

    public function create(LeadExportOptions $options): LeadExportJob
    {
        $id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));
        $now = time();

        /*
         * The filename carries a random suffix, not just a timestamp.
         *
         * The uploads directory is web-served, so a guessable name is a public URL to every
         * lead's contact details and revenue. The download itself goes through an authenticated
         * admin handler; the suffix is what stops the file being fetched around it.
         */
        $filename = 'remoteleverage-leads-'.gmdate('Y-m-d-His', $now).'-'.bin2hex(random_bytes(4)).'.csv';

        $job = new LeadExportJob(
            id: $id,
            options: $options,
            filename: $filename,
            path: $this->directory().'/'.$filename,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->save($job);

        $index = $this->ids();
        $index[] = $job->id;
        $this->writeIndex($index);
        $this->prune();

        return $job;
    }

    public function find(string $id): ?LeadExportJob
    {
        // A job id reaches this from a request parameter and is concatenated into an option
        // name. Anything that is not the uuid we minted does not get to become a lookup.
        if (preg_match('/^[a-f0-9-]{16,64}$/i', $id) !== 1) {
            return null;
        }

        $data = get_option(self::OPTION_PREFIX.$id);

        return is_array($data) ? LeadExportJob::fromArray($data) : null;
    }

    public function save(LeadExportJob $job): void
    {
        $job->updatedAt = time();
        update_option(self::OPTION_PREFIX.$job->id, $job->toArray(), false);
    }

    public function delete(string $id): void
    {
        $job = $this->find($id);

        if ($job !== null && $job->path !== '' && is_file($job->path)) {
            @unlink($job->path);
        }

        delete_option(self::OPTION_PREFIX.$id);
        $this->writeIndex(array_values(array_diff($this->ids(), [$id])));
    }

    /**
     * Newest first, skipping ids whose option has already gone.
     *
     * @return array<int, LeadExportJob>
     */
    public function recent(): array
    {
        $jobs = [];

        foreach (array_reverse($this->ids()) as $id) {
            $job = $this->find($id);

            if ($job !== null) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * The export still running, if there is one.
     *
     * Only one at a time. Two concurrent exports are not dangerous the way two concurrent
     * database transfers are, but they are indistinguishable in the UI and each halves the
     * other's throughput while doubling the load on a table this one is already walking whole.
     */
    public function active(): ?LeadExportJob
    {
        foreach ($this->recent() as $job) {
            if ($job->isFinished()) {
                continue;
            }

            // An abandoned tab leaves a job running forever otherwise, and the next export is
            // refused for a job nobody is stepping.
            if (time() - $job->updatedAt > self::STALE_AFTER) {
                continue;
            }

            return $job;
        }

        return null;
    }

    /**
     * Where the files live: a dedicated directory under uploads, guarded as well as a
     * web-served directory can be.
     */
    public function directory(): string
    {
        $uploads = wp_upload_dir();
        $directory = rtrim((string) ($uploads['basedir'] ?? sys_get_temp_dir()), '/').'/'.self::DIRECTORY;

        if (! is_dir($directory)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($directory);
            } else {
                @mkdir($directory, 0755, true);
            }

            /*
             * Belt and braces against the directory being listed or served. Apache honours the
             * .htaccess; nginx does not, which is why the filenames carry random suffixes and
             * the index file exists at all.
             */
            @file_put_contents($directory.'/index.html', '');
            @file_put_contents($directory.'/.htaccess', "Require all denied\n");
        }

        return $directory;
    }

    /** @return array<int, string> */
    public function ids(): array
    {
        $index = get_option(self::INDEX_OPTION, []);

        return is_array($index) ? array_values(array_filter(array_map('strval', $index))) : [];
    }

    /**
     * Drop the oldest jobs past the retention limit, taking their files with them.
     *
     * Each file is the entire lead table in plain text. Keeping them around indefinitely turns a
     * convenience into a pile of unencrypted exports nobody remembers making.
     */
    private function prune(): void
    {
        $ids = $this->ids();

        while (count($ids) > self::RETENTION) {
            $this->delete(array_shift($ids));
            $ids = $this->ids();
        }
    }

    /** @param array<int, string> $ids */
    private function writeIndex(array $ids): void
    {
        update_option(self::INDEX_OPTION, array_values($ids), false);
    }
}
