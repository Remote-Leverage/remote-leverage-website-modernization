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
        {--keep-bytes=0 : With --clear, leave this many bytes of the tail behind}';

    /**
     * @var string
     */
    protected $description = 'Read the application log on a remote environment, and optionally clear it.';

    public function handle(): int
    {
        $source = (string) $this->option('source');

        $input = array_filter([
            'lines' => (int) $this->option('lines'),
            'contains' => trim((string) $this->option('contains')),
            'clear' => (bool) $this->option('clear'),
            'keep_bytes' => (int) $this->option('keep-bytes'),
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
