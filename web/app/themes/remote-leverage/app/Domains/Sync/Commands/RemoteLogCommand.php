<?php

declare(strict_types=1);

namespace App\Domains\Sync\Commands;

use App\Ai\Abilities\ErrorLogAbility;
use App\Domains\Sync\SyncClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read — and optionally clear — a remote environment's application log.
 *
 * The counterpart to {@see ErrorLogAbility}, which explains why this exists at
 * all. Short version: every failure path in this codebase logs and carries on, by design, and
 * without this the log is on a container nobody can reach.
 *
 *     wp acorn rl:logs --source=production
 *     wp acorn rl:logs --source=production --contains="cost alert" --lines=500
 *     wp acorn rl:logs --source=production --clear
 *
 * `--clear` is deliberately not the default. Reading is something you do while guessing; clearing
 * is something you do once, knowingly, so the next read is only the new incident.
 */
class RemoteLogCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rl:logs
        {--source=production : Remote environment to read from}
        {--lines=200 : How many of the most recent lines to show}
        {--contains= : Only show lines containing this text}
        {--clear : Truncate the log once it has been read}
        {--keep-bytes=0 : With --clear, leave this many bytes of the tail behind}
        {--status : Report why the hourly cost alert will or will not post there, instead of reading the log}
        {--wp : Read WordPress\'s own debug.log instead of this application\'s channel}';

    /**
     * @var string
     */
    protected $description = 'Read the application log on a remote environment, and optionally clear it.';

    public function handle(): int
    {
        $source = (string) $this->option('source');

        if ($this->option('status')) {
            return $this->showStatus($source);
        }

        $input = array_filter([
            'lines' => (int) $this->option('lines'),
            'contains' => trim((string) $this->option('contains')),
            'clear' => (bool) $this->option('clear'),
            'keep_bytes' => (int) $this->option('keep-bytes'),
            'log' => $this->option('wp') ? 'wordpress' : '',
        ], static fn ($value): bool => $value !== '' && $value !== false && $value !== 0);

        if ($this->option('clear')) {
            $this->warn("This empties {$source}'s log after reading it. The file is the only copy.");

            if (! $this->confirm('Continue?', false)) {
                return self::SUCCESS;
            }

            $input['clear'] = true;
        }

        try {
            $result = (new SyncClient($source))->run('app/error-log', $input);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /*
         * The ability's return value arrives inside whatever envelope the abilities endpoint uses,
         * and that has changed shape before. Reaching for the payload rather than assuming it is
         * at the top level keeps this working either way.
         */
        $payload = $result['result'] ?? $result['data'] ?? $result;

        if (($payload['exists'] ?? false) !== true) {
            $this->warn((string) ($payload['message'] ?? 'No log file on that environment.'));

            return self::SUCCESS;
        }

        $lines = (array) ($payload['lines'] ?? []);

        $this->line("<comment>{$payload['path']}</comment> — ".$this->bytes((int) ($payload['size_bytes'] ?? 0)));

        if ($lines === []) {
            $this->info('Nothing matched.');
        }

        foreach ($lines as $line) {
            $this->line((string) $line);
        }

        if (($payload['cleared'] ?? false) === true) {
            $this->info('Cleared. Now '.$this->bytes((int) ($payload['size_bytes_after'] ?? 0)).'.');
        }

        return self::SUCCESS;
    }

    /**
     * The one question the log cannot answer.
     *
     * When the alert is switched off its WP-Cron event is removed, so the code that would log a
     * reason never runs again — the silence is total and self-sustaining. This asks the
     * environment what it currently believes instead.
     */
    private function showStatus(string $source): int
    {
        try {
            $result = (new SyncClient($source))->run('app/cost-alert-status', []);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $payload = $result['result'] ?? $result['data'] ?? $result;

        foreach ($payload as $key => $value) {
            $shown = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => json_encode($value),
                $value === null => 'null',
                default => (string) $value,
            };

            $this->line(sprintf('  <comment>%-22s</comment> %s', $key, $shown));
        }

        if (($payload['will_post'] ?? true) === false) {
            $this->warn('The alert is switched off here, so its hourly event is not scheduled and nothing will log a reason.');
        } elseif (($payload['cron_scheduled'] ?? true) === false) {
            $this->warn('Enabled, but the hourly event is missing — it was dropped while switched off and has not been re-added yet.');
        }

        return self::SUCCESS;
    }

    private function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        return $bytes < 1024 * 1024
            ? round($bytes / 1024, 1).' KB'
            : round($bytes / 1024 / 1024, 1).' MB';
    }
}
