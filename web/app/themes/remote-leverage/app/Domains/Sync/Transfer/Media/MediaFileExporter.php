<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Media;

use Illuminate\Support\Facades\DB;

/**
 * Finds the files behind a set of attachments, on the sending side.
 *
 * An attachment is more than one file: WordPress generates a resized copy per
 * registered image size and records them in _wp_attachment_metadata. Sending
 * only the original would leave the target regenerating nothing — WordPress
 * does not rebuild sizes on demand — so every srcset variant would 404 even
 * though the attachment row and its original file arrived intact.
 *
 * Paths are relative to the uploads basedir, which makes them portable between
 * environments whose absolute upload paths differ.
 */
class MediaFileExporter
{
    /**
     * Relative paths for the given attachments, de-duplicated.
     *
     * @param  array<int, int>  $attachmentIds
     * @return array<int, string>
     */
    public function pathsFor(array $attachmentIds): array
    {
        if ($attachmentIds === []) {
            return [];
        }

        $paths = [];

        $rows = DB::table('postmeta')
            ->whereIn('post_id', $attachmentIds)
            ->whereIn('meta_key', ['_wp_attached_file', '_wp_attachment_metadata'])
            ->get();

        foreach ($rows as $row) {
            $meta = (array) $row;

            if (($meta['meta_key'] ?? '') === '_wp_attached_file') {
                $paths[] = (string) ($meta['meta_value'] ?? '');

                continue;
            }

            $paths = array_merge($paths, $this->sizePathsFrom((string) ($meta['meta_value'] ?? '')));
        }

        return array_values(array_unique(array_filter($paths, UploadPath::isValid(...))));
    }

    /**
     * Pull the generated size filenames out of _wp_attachment_metadata.
     *
     * The sizes are recorded as bare filenames, so they are rejoined onto the
     * original's directory to become paths relative to the uploads root.
     *
     * @return array<int, string>
     */
    private function sizePathsFrom(string $serialised): array
    {
        $meta = @unserialize($serialised, ['allowed_classes' => false]);

        if (! is_array($meta) || ! isset($meta['file']) || ! is_string($meta['file'])) {
            return [];
        }

        $directory = dirname($meta['file']);
        $directory = $directory === '.' ? '' : $directory.'/';
        $paths = [$meta['file']];

        foreach ((array) ($meta['sizes'] ?? []) as $size) {
            if (is_array($size) && isset($size['file']) && is_string($size['file'])) {
                $paths[] = $directory.$size['file'];
            }
        }

        return $paths;
    }

    /**
     * Size and checksum for each path that exists locally.
     *
     * The checksum is what lets the target ask for only what it is missing, so
     * a re-run after a partial transfer moves almost nothing.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array{path: string, size: int, sha256: string}>
     */
    public function manifest(array $paths): array
    {
        $manifest = [];

        foreach ($paths as $path) {
            if (! UploadPath::isValid($path)) {
                continue;
            }

            $absolute = UploadPath::from($path)->absoluteUnder($this->baseDir());

            if (! is_file($absolute)) {
                continue;
            }

            $manifest[] = [
                'path' => $path,
                'size' => (int) filesize($absolute),
                'sha256' => (string) hash_file('sha256', $absolute),
            ];
        }

        return $manifest;
    }

    /**
     * Raw bytes of one slice of a file, base64 encoded for JSON transport.
     */
    public function readChunk(string $path, int $offset, int $length): ?string
    {
        $absolute = UploadPath::from($path)->absoluteUnder($this->baseDir());

        if (! is_file($absolute)) {
            return null;
        }

        $bytes = @file_get_contents($absolute, false, null, $offset, $length);

        return $bytes === false ? null : base64_encode($bytes);
    }

    public function baseDir(): string
    {
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : [];

        return (string) ($uploads['basedir'] ?? '');
    }
}
