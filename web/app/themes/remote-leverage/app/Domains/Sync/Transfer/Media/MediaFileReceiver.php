<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Media;

use App\Domains\Sync\Transfer\UndoLog;
use RuntimeException;

/**
 * Writes incoming media files on the target.
 *
 * Files are written to a temporary name and moved into place only once the
 * whole file has arrived and its checksum matches. A transfer interrupted
 * halfway therefore leaves a stray temp file rather than a truncated image that
 * looks present but renders broken — the failure mode that would be hardest to
 * notice.
 */
class MediaFileReceiver
{
    private const TEMP_SUFFIX = '.rl-sync-part';

    public function __construct(private readonly string $baseDir) {}

    /**
     * Which of the sender's files this environment actually needs.
     *
     * @param  array<int, array{path: string, size: int, sha256: string}>  $manifest
     * @return array<int, string>
     */
    public function missing(array $manifest): array
    {
        $needed = [];

        foreach ($manifest as $entry) {
            $path = (string) ($entry['path'] ?? '');

            if (! UploadPath::isValid($path)) {
                continue;
            }

            $absolute = UploadPath::from($path)->absoluteUnder($this->baseDir);

            if (! is_file($absolute)) {
                $needed[] = $path;

                continue;
            }

            if (hash_file('sha256', $absolute) !== (string) ($entry['sha256'] ?? '')) {
                $needed[] = $path;
            }
        }

        return $needed;
    }

    /**
     * Append one slice of a file.
     *
     * @param  string  $data  Base64 encoded bytes.
     * @return array{complete: bool, bytes: int}
     */
    public function writeChunk(
        string $path,
        int $offset,
        string $data,
        bool $final,
        string $sha256,
        ?UndoLog $undo = null,
    ): array {
        $uploadPath = UploadPath::from($path);
        $absolute = $uploadPath->absoluteUnder($this->baseDir);
        $temp = $absolute.self::TEMP_SUFFIX;

        $bytes = base64_decode($data, true);

        if ($bytes === false) {
            throw new RuntimeException("Media chunk for {$path} was not valid base64.");
        }

        $this->ensureDirectory(dirname($absolute));

        // Offset 0 starts the file over, so a retried transfer cannot append to
        // the remains of a previous attempt.
        $handle = @fopen($temp, $offset === 0 ? 'wb' : 'cb');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path} for writing.");
        }

        try {
            fseek($handle, $offset);
            fwrite($handle, $bytes);
        } finally {
            fclose($handle);
        }

        if (! $final) {
            return ['complete' => false, 'bytes' => strlen($bytes)];
        }

        return $this->finalise($absolute, $temp, $path, $sha256, $undo);
    }

    /**
     * @return array{complete: bool, bytes: int}
     */
    private function finalise(
        string $absolute,
        string $temp,
        string $path,
        string $sha256,
        ?UndoLog $undo,
    ): array {
        $actual = (string) hash_file('sha256', $temp);

        if ($sha256 !== '' && $actual !== $sha256) {
            @unlink($temp);

            throw new RuntimeException(
                "Checksum mismatch for {$path}: expected {$sha256}, got {$actual}. The file was discarded."
            );
        }

        $this->recordUndo($absolute, $undo);

        if (! @rename($temp, $absolute)) {
            @unlink($temp);

            throw new RuntimeException("Could not move {$path} into place.");
        }

        return ['complete' => true, 'bytes' => (int) filesize($absolute)];
    }

    /**
     * Record how to undo this write — deleting a file that was not there
     * before, or restoring a copy of the one being replaced.
     */
    private function recordUndo(string $absolute, ?UndoLog $undo): void
    {
        if (! $undo instanceof UndoLog) {
            return;
        }

        if (! is_file($absolute)) {
            $undo->recordFileAdd($absolute);

            return;
        }

        $backup = $absolute.'.rl-sync-backup';

        if (@copy($absolute, $backup)) {
            $undo->recordFileReplace($absolute, $backup);
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create the upload directory {$dir}.");
        }
    }
}
