<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Domains\Sync\SyncCapability;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

/**
 * Read the application log on a remote environment, and truncate it once it has been read.
 *
 * ## Why this exists
 *
 * Nobody on this team has a shell on staging or production, and every failure path in this
 * codebase is deliberately quiet: the cost alert's cron callback swallows its exception so a
 * reporting job cannot 500 a visitor's page, `replyWithFindings()` logs and moves on, `SoftCache`
 * degrades silently. All of those are the right behaviour and all of them put the only account of
 * what happened in a file nobody can open.
 *
 * On 2026-09-21 that turned "the hourly card stopped posting" into a diagnosis that could not be
 * made at all: the send path was provably fine locally, the snapshot computed, cron was demonstrably
 * alive, and the one thing that would have said which of those was untrue was a `Log::error` line
 * on a container. `tail-logs.yml` exists for this and its production job will not start, because
 * the GitHub environment it names does not run on `workflow_dispatch`.
 *
 * ## Why an ability rather than a shell
 *
 * The same reason the sync commands are abilities: this repo's rule is that remote environments are
 * reached over the Abilities REST API, never over SSH or WP-CLI. The credential already exists — the
 * `sync-service` user holds {@see SyncCapability} on every environment — so this works today without
 * provisioning anything new.
 *
 * ## Truncating is part of reading, on purpose
 *
 * `clear` empties the file rather than deleting it, and only after the content has been read into
 * the response. The point is to be able to say "this is everything since I last looked": a log that
 * is never cleared means the next incident is read through a week of noise, and the usual
 * alternative — a timestamp filter — is unreliable against multi-line stack traces, which do not
 * each carry one.
 *
 * Nothing else writes to this file between the read and the truncate that would not also have been
 * written a moment later, so the worst case is losing lines from the same second. Against that: the
 * file is the only copy. `keep_bytes` exists so a clear can leave the tail in place, and the command
 * defaults to reading without clearing.
 */
class ErrorLogAbility extends Ability
{
    /** Never return more than this, however many lines were asked for. */
    private const MAX_BYTES = 400_000;

    public function label(): string
    {
        return 'Read Error Log';
    }

    public function description(): string
    {
        return 'Returns the tail of this environment\'s application log, optionally filtered, and can '.
            'truncate it once read. Internal diagnostics only — the log carries whatever the '.
            'application logged, which may include customer data.';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input): mixed
    {
        $path = $this->logPath();

        if ($path === null) {
            return [
                'path' => null,
                'exists' => false,
                'lines' => [],
                'message' => 'No log file found. Nothing has been logged, or the log is written elsewhere.',
            ];
        }

        $lines = $this->tail($path, max(1, (int) ($input['lines'] ?? 200)));

        $contains = trim((string) ($input['contains'] ?? ''));

        if ($contains !== '') {
            $lines = array_values(array_filter(
                $lines,
                static fn (string $line): bool => stripos($line, $contains) !== false,
            ));
        }

        $result = [
            'path' => $path,
            'exists' => true,
            'size_bytes' => (int) filesize($path),
            'lines' => $lines,
            'cleared' => false,
        ];

        if (($input['clear'] ?? false) === true) {
            $result['cleared'] = $this->truncate($path, max(0, (int) ($input['keep_bytes'] ?? 0)));
            $result['size_bytes_after'] = file_exists($path) ? (int) filesize($path) : 0;
        }

        return $result;
    }

    /**
     * The newest `.log` under Acorn's storage, which is where `Log::` writes.
     *
     * Newest rather than a fixed name because the channel may be `daily`, which rotates
     * `laravel-YYYY-MM-DD.log`, and a hard-coded `laravel.log` would read an empty file on every
     * environment configured that way and report "nothing was logged".
     */
    private function logPath(): ?string
    {
        $candidates = [];

        if (function_exists('storage_path')) {
            $candidates = glob(rtrim((string) storage_path('logs'), '/').'/*.log') ?: [];
        }

        if ($candidates === [] && defined('WP_CONTENT_DIR')) {
            $debug = rtrim((string) WP_CONTENT_DIR, '/').'/debug.log';

            if (is_readable($debug)) {
                $candidates = [$debug];
            }
        }

        $candidates = array_values(array_filter($candidates, 'is_readable'));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $candidates[0];
    }

    /**
     * The last N lines, without reading the whole file into memory.
     *
     * Logs here reach tens of megabytes between clears, and `file()` on one of those is how a
     * diagnostic tool becomes the second outage.
     *
     * @return array<int, string>
     */
    private function tail(string $path, int $lines): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $size = (int) filesize($path);
        $read = min($size, self::MAX_BYTES);

        fseek($handle, -$read, SEEK_END);
        $chunk = (string) fread($handle, $read);
        fclose($handle);

        $all = explode("\n", $chunk);

        // The first line is almost certainly a fragment when the window cut mid-line.
        if ($read < $size && $all !== []) {
            array_shift($all);
        }

        $all = array_values(array_filter($all, static fn (string $line): bool => trim($line) !== ''));

        return array_slice($all, -$lines);
    }

    /** Empty the file, optionally keeping its tail. Returns whether anything was written. */
    private function truncate(string $path, int $keepBytes): bool
    {
        $keep = '';

        if ($keepBytes > 0) {
            $handle = fopen($path, 'rb');

            if ($handle !== false) {
                $size = (int) filesize($path);
                fseek($handle, -min($size, $keepBytes), SEEK_END);
                $keep = (string) fread($handle, min($size, $keepBytes));
                fclose($handle);
            }
        }

        return file_put_contents($path, $keep) !== false;
    }

    public function permission(): bool|WP_Error
    {
        /*
         * The same capability the sync commands use, for the same reason: the credential holding
         * it is already provisioned on every environment, and this is the same class of access —
         * an authenticated operator reaching a remote environment over HTTPS. A dedicated
         * capability would be tidier and would have to be granted on production first, which needs
         * the WP-CLI access whose absence is the reason this ability exists.
         */
        if (! SyncCapability::currentUserCan()) {
            return new WP_Error('forbidden', 'The '.SyncCapability::NAME.' capability is required.');
        }

        return true;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lines' => [
                    'type' => 'integer',
                    'description' => 'How many of the most recent lines to return. Default 200.',
                ],
                'contains' => [
                    'type' => 'string',
                    'description' => 'Only return lines containing this text, matched case-insensitively.',
                ],
                'clear' => [
                    'type' => 'boolean',
                    'description' => 'Truncate the log after reading it. Default false.',
                ],
                'keep_bytes' => [
                    'type' => 'integer',
                    'description' => 'With clear, leave this many bytes of the tail behind. Default 0.',
                ],
            ],
        ];
    }

    public function category(): ?string
    {
        return 'site';
    }

    /**
     * See ExportSyncableSettingsAbility::meta() — `show_in_rest` without `public` is what makes
     * the REST run endpoint serve this while keeping it out of MCP auto-discovery. A log reader
     * should be reachable by the operator holding the credential, not offered to every MCP client
     * that connects.
     */
    public function meta(): array
    {
        return ['show_in_rest' => true];
    }
}
