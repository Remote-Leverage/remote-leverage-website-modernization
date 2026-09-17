<?php

declare(strict_types=1);

namespace App\Domains\Lead\Export;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a lead export a batch at a time.
 *
 * The unit of work is "as much as fits in a few seconds", not "the whole export": each call
 * arrives as its own HTTP request from the browser, so the only limit that matters is the one
 * that keeps a single request comfortably inside PHP's execution time and any proxy's read
 * timeout. Finishing is the browser's job — it calls back until the job says it is done.
 *
 * That is what makes this survive a 50,000-row export on a host that would kill a single
 * long-running request at 30 seconds, without needing a queue worker this application does not
 * have.
 */
class LeadExportRunner
{
    /** Rows per query. Large enough to amortise the round trip, small enough to stay in memory. */
    private const BATCH = 250;

    /**
     * Seconds of work per request.
     *
     * Well under a default 30-second max_execution_time and under the 60-second read timeout
     * most proxies in front of WordPress use, so a batch cannot be cut off mid-row and leave a
     * half-written line in the file.
     */
    private const TIME_BUDGET = 3.0;

    public function __construct(private readonly LeadExportJobStore $store) {}

    /**
     * Open a file, write its header row, and record how much work is coming.
     */
    public function start(LeadExportOptions $options): LeadExportJob
    {
        $job = $this->store->create($options);

        try {
            /*
             * Freeze the table at the highest id that exists right now. Everything after this
             * belongs to the next export, and the total below is counted against the same
             * snapshot so the progress bar and the file agree on what is coming.
             */
            $job->ceiling = (int) ($options->query()->max('id') ?? 0);
            $job->total = $options->total($job->ceiling);

            $handle = fopen($job->path, 'w');

            if ($handle === false) {
                throw new \RuntimeException('could not open '.$job->path.' for writing');
            }

            /*
             * A UTF-8 BOM, because the audience for this file is Excel.
             *
             * Without it Excel reads the file as the system's legacy codepage and every accented
             * name in it — which here includes the person running the export — comes out
             * mojibaked. Google Sheets, Numbers and every CSV parser worth the name skip it.
             */
            fwrite($handle, "\xEF\xBB\xBF");
            self::writeRow($handle, LeadExportColumns::headers($options->effectiveGroups()));
            fclose($handle);

            $job->phase = $job->total === 0 ? 'done' : 'running';
        } catch (Throwable $e) {
            $job->phase = 'failed';
            $job->error = $e->getMessage();
            Log::error('LeadExportRunner: could not start the export: '.$e->getMessage());
        }

        $this->store->save($job);

        return $job;
    }

    /**
     * Append as many rows as fit in this request's budget.
     */
    public function step(LeadExportJob $job): LeadExportJob
    {
        if ($job->isFinished()) {
            return $job;
        }

        $deadline = microtime(true) + self::TIME_BUDGET;
        $groups = $job->options->effectiveGroups();

        try {
            $handle = fopen($job->path, 'a');

            if ($handle === false) {
                throw new \RuntimeException('the export file has gone from '.$job->path);
            }

            try {
                do {
                    $remaining = $job->total - $job->written;

                    if ($remaining <= 0) {
                        break;
                    }

                    $leads = $this->batch($job, min(self::BATCH, $remaining));

                    /*
                     * An empty batch before the total is reached means rows were deleted while
                     * the export was running. Finishing here is the only safe move: looping
                     * again would ask the same question and get the same answer forever.
                     */
                    if ($leads->isEmpty()) {
                        $job->total = $job->written;
                        break;
                    }

                    foreach ($leads as $lead) {
                        self::writeRow($handle, LeadExportColumns::row($lead, $groups));
                        $job->written++;
                        $job->cursor = (int) $lead->id;
                    }
                } while (microtime(true) < $deadline);
            } finally {
                fclose($handle);
            }

            if ($job->written >= $job->total) {
                $job->phase = 'done';
            }
        } catch (Throwable $e) {
            $job->phase = 'failed';
            $job->error = $e->getMessage();
            Log::error('LeadExportRunner: export '.$job->id.' failed: '.$e->getMessage());
        }

        $this->store->save($job);

        return $job;
    }

    /**
     * Write one CSV line.
     *
     * The escape character is pinned to none, which is both RFC 4180's behaviour and what PHP
     * 8.4 deprecates leaving to the default. PHP's historic backslash escaping is what turns a
     * trailing backslash in a notes field into a quote that swallows the rest of the file.
     *
     * @param  resource  $handle
     * @param  array<int, string>  $row
     */
    private static function writeRow($handle, array $row): void
    {
        fputcsv($handle, $row, ',', '"', '');
    }

    /**
     * The next slice of leads, walking down from the last id written.
     *
     * @return Collection<int, Lead>
     */
    private function batch(LeadExportJob $job, int $take)
    {
        $query = $job->options->query()->limit($take);

        if ($job->ceiling > 0) {
            $query->where('id', '<=', $job->ceiling);
        }

        if ($job->cursor > 0) {
            $query->where('id', '<', $job->cursor);
        }

        $groups = $job->options->effectiveGroups();

        if ($relations = LeadExportColumns::eagerLoad($groups)) {
            $query->with($relations);
        }

        if ($counts = LeadExportColumns::withCount($groups)) {
            $query->withCount($counts);
        }

        return $query->get();
    }
}
