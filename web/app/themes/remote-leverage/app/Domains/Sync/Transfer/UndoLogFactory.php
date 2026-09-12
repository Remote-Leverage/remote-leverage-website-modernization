<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

/**
 * Resolves a session's undo log path.
 *
 * Logs live under the uploads directory because that is the one path guaranteed
 * writable on every environment — on staging it is the EFS mount, which also
 * means a log survives the container that wrote it. A rollback can therefore be
 * run by a later request, on a different task, after the one that failed is gone.
 */
class UndoLogFactory
{
    private const DIRECTORY = 'rl-sync';

    public function for(string $sessionId): UndoLog
    {
        return new UndoLog($this->basePath().'/'.$sessionId.'/undo.jsonl');
    }

    /**
     * Remove a session's log directory once it is no longer needed.
     */
    public function forget(string $sessionId): void
    {
        $dir = $this->basePath().'/'.$sessionId;

        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }

    private function basePath(): string
    {
        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir();
            $base = $uploads['basedir'] ?? '';

            if (is_string($base) && $base !== '') {
                return rtrim($base, '/').'/'.self::DIRECTORY;
            }
        }

        return rtrim(sys_get_temp_dir(), '/').'/'.self::DIRECTORY;
    }
}
