<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Media;

use InvalidArgumentException;

/**
 * A validated relative path inside the uploads directory.
 *
 * Every media path a transfer writes arrives from another environment over
 * HTTP, and is then joined onto the uploads basedir and written to disk. That
 * is an arbitrary-file-write sink, and it is the single most dangerous input in
 * this feature — a path of "../../wp-config.php" would be catastrophic.
 *
 * So paths are not sanitised, they are *validated*: anything that is not a
 * plain relative path made of safe segments is rejected outright rather than
 * cleaned up and used. Rejecting is safe; guessing what a malformed path meant
 * is not.
 */
final class UploadPath
{
    /**
     * Extensions the transfer will write. An allowlist rather than a denylist:
     * a new dangerous extension should fail closed, and the set of things that
     * legitimately live in uploads is small and known.
     *
     * @var array<int, string>
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff',
        'mp4', 'mov', 'webm', 'avi', 'mp3', 'wav', 'ogg', 'm4a',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
        'zip', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'vtt', 'json',
    ];

    private function __construct(public readonly string $relative) {}

    /**
     * @throws InvalidArgumentException
     */
    public static function from(string $candidate): self
    {
        $path = str_replace('\\', '/', trim($candidate));

        if ($path === '') {
            throw new InvalidArgumentException('Empty media path.');
        }

        if (str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:#', $path) === 1) {
            throw new InvalidArgumentException("Absolute media path refused: {$candidate}");
        }

        // A null byte truncates the path at the filesystem layer, so a name that
        // passes an extension check here could still open something else.
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Media path contains a null byte.');
        }

        if (str_contains($path, '://')) {
            throw new InvalidArgumentException("Stream wrapper refused in media path: {$candidate}");
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException("Traversal or empty segment in media path: {$candidate}");
            }

            if (preg_match('/^[A-Za-z0-9._-]+$/', $segment) !== 1) {
                throw new InvalidArgumentException("Unsafe characters in media path: {$candidate}");
            }
        }

        $extension = strtolower(pathinfo(end($segments), PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException(
                "Media path has a disallowed extension \"{$extension}\": {$candidate}"
            );
        }

        return new self(implode('/', $segments));
    }

    public static function isValid(string $candidate): bool
    {
        try {
            self::from($candidate);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Absolute path under the given base.
     *
     * The base is re-checked after joining, so even if validation above were
     * somehow bypassed the result still cannot escape the uploads directory.
     */
    public function absoluteUnder(string $baseDir): string
    {
        $base = rtrim(str_replace('\\', '/', $baseDir), '/');
        $full = $base.'/'.$this->relative;

        if (! str_starts_with($full, $base.'/')) {
            throw new InvalidArgumentException('Resolved media path escapes the uploads directory.');
        }

        return $full;
    }

    public function directory(): string
    {
        $dir = dirname($this->relative);

        return $dir === '.' ? '' : $dir;
    }
}
