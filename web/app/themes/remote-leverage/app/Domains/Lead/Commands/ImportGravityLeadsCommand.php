<?php

declare(strict_types=1);

namespace App\Domains\Lead\Commands;

use App\Domains\Lead\Import\GravityCsvReader;
use App\Domains\Lead\Import\GravityLeadImporter;
use App\Domains\Lead\Import\GravityLeadMapper;
use App\Domains\Lead\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Backfills `rl_leads` from Gravity Forms entry exports.
 *
 * The domain logic is in {@see GravityLeadImporter}; this is the operator's end of it — where
 * the files are found, what gets reported before anything is written, and the confirmation in
 * front of `--truncate`.
 */
class ImportGravityLeadsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'lead:import-gravity
        {path* : Gravity CSV exports, or directories containing them}
        {--dry-run : Parse, fold and report without writing anything}
        {--truncate : Delete every existing lead and its dependent rows first}
        {--skip-identity : Do not build the identity profile graph afterwards}';

    /**
     * @var string
     */
    protected $description = 'Import historical Gravity Forms entries into rl_leads, one lead per person, without dispatching lead events';

    /**
     * Tables emptied by --truncate, children first.
     *
     * `rl_integration_calls` and `rl_lead_activity_logs` hold foreign keys onto `rl_leads`, so
     * the order matters, and the identity tables have to go too — a profile left behind would
     * outlive every lead that justified it and then silently adopt an imported lead that merely
     * shares an email with a deleted one.
     *
     * @var array<int, string>
     */
    private const TRUNCATE_ORDER = [
        'rl_integration_calls',
        'rl_lead_activity_logs',
        'rl_lead_identifiers',
        'rl_lead_profiles',
        'rl_leads',
    ];

    public function handle(GravityLeadMapper $mapper, GravityLeadImporter $importer): int
    {
        try {
            $files = $this->resolveFiles();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Reading %d Gravity export(s).', count($files)));

        $entries = [];
        $malformed = 0;
        $skippedRows = 0;

        foreach ($files as $file) {
            $reader = new GravityCsvReader($file);
            $form = $reader->formSlug();

            $unmapped = $mapper->unmappedHeaders($reader->headers());

            if ($unmapped !== []) {
                // Not fatal — the import is still correct for everything it does know about — but
                // it is the one way a backfill quietly loses a field marketing added last month.
                $this->warn(sprintf(
                    '  %s: %d header(s) this importer does not know: %s',
                    basename($file),
                    count($unmapped),
                    implode(', ', $unmapped),
                ));
            }

            $before = count($entries);

            foreach ($reader->rows() as $row) {
                $entry = $mapper->map($row, $form);

                if ($entry === null) {
                    $skippedRows++;

                    continue;
                }

                $entries[] = $entry;
            }

            $malformed += $reader->malformedCount();

            $this->line(sprintf('  %s: %d entries', basename($file), count($entries) - $before));
        }

        if ($entries === []) {
            $this->error('No usable entries found.');

            return self::FAILURE;
        }

        $leads = $importer->fold($entries);

        $this->reportFold($entries, $leads, $skippedRows, $malformed);

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing written.');

            return self::SUCCESS;
        }

        if ($this->option('truncate') && ! $this->truncate()) {
            return self::FAILURE;
        }

        $written = $importer->persist($leads);

        $this->info(sprintf(
            'Wrote %d new lead(s), updated %d, left %d live capture(s) untouched.',
            $written['created'],
            $written['updated'],
            $written['skipped_live'],
        ));

        if (! $this->option('skip-identity')) {
            $bar = $this->output->createProgressBar(count($leads));
            $bar->start();
            $last = 0;

            $resolved = $importer->resolveIdentities($leads, function (int $done) use ($bar, &$last): void {
                $bar->advance($done - $last);
                $last = $done;
            });

            $bar->finish();
            $this->newLine(2);

            $this->info(sprintf('Resolved %d lead(s) onto identity profiles.', $resolved));
        }

        return self::SUCCESS;
    }

    /**
     * Every CSV named by the arguments, whether given directly or as a directory.
     *
     * @return array<int, string>
     */
    private function resolveFiles(): array
    {
        $files = [];

        foreach ((array) $this->argument('path') as $path) {
            if (is_dir($path)) {
                $found = glob(rtrim($path, '/').'/*.csv') ?: [];

                if ($found === []) {
                    throw new RuntimeException("No CSV files in directory: {$path}");
                }

                $files = array_merge($files, $found);

                continue;
            }

            if (! is_file($path)) {
                throw new RuntimeException("Not a file or directory: {$path}");
            }

            $files[] = $path;
        }

        // Sorted so a run is reproducible, and de-duplicated so naming a file and its directory
        // does not import it twice — which would not corrupt anything, because the fold is
        // idempotent per entry id, but would make the reported counts a lie.
        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, array<string, mixed>>  $leads
     */
    private function reportFold(array $entries, array $leads, int $skippedRows, int $malformed): void
    {
        $booked = count(array_filter($leads, static fn (array $lead): bool => $lead['status'] === 'booked'));
        $finals = count(array_filter($entries, static fn (array $entry): bool => (bool) $entry['booked']));

        $dates = array_map(static fn (array $entry) => $entry['submitted_at'], $entries);
        sort($dates);

        $this->newLine();
        $this->table(['', ''], [
            ['Entries read', number_format(count($entries))],
            ['  final (booked)', number_format($finals)],
            ['  partial (abandoned)', number_format(count($entries) - $finals)],
            ['Folded to leads', number_format(count($leads))],
            ['  booked', number_format($booked)],
            ['  abandoned', number_format(count($leads) - $booked)],
            ['Date range (UTC)', reset($dates)->toDateTimeString().'  ..  '.end($dates)->toDateTimeString()],
            ['Rows without an email or entry id', number_format($skippedRows)],
            ['Rows skipped as malformed', number_format($malformed)],
        ]);
    }

    /**
     * Empty the lead tables, once the operator has said so out loud.
     */
    private function truncate(): bool
    {
        $existing = Lead::query()->withTrashed()->count();

        if (! $this->confirm(sprintf('Delete all %d existing lead(s) and their activity, integration and identity rows?', $existing), false)) {
            $this->comment('Truncate declined; nothing written.');

            return false;
        }

        // The FKs onto rl_leads make TRUNCATE illegal in dependency order on MySQL regardless of
        // sequence, so the checks come off for the duration and go straight back on.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TRUNCATE_ORDER as $table) {
                $prefixed = DB::getTablePrefix().$table;

                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  emptied {$prefixed}");
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return true;
    }
}
