<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Media\UploadPath;
use Throwable;

/**
 * Returns one slice of one media file, for a pull.
 *
 * The path is validated by UploadPath before anything is opened. That matters
 * as much reading as writing: without it this would be an arbitrary-file-*read*
 * endpoint, and "give me ../../wp-config.php" would return the database
 * credentials to any caller holding the sync capability.
 *
 * Read-only.
 */
class ReadMediaFileAbility extends TransferAbility
{
    public function __construct(private readonly MediaFileExporter $files) {}

    public function label(): string
    {
        return 'Read Media File';
    }

    public function description(): string
    {
        return 'Returns a base64 encoded slice of one media file from the uploads directory. '
            .'Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $path = (string) ($input['path'] ?? '');

        if (! UploadPath::isValid($path)) {
            return ['ok' => false, 'error' => 'Refused: that is not a valid uploads path.'];
        }

        try {
            $data = $this->files->readChunk(
                $path,
                max(0, (int) ($input['offset'] ?? 0)),
                min(4194304, max(1, (int) ($input['length'] ?? 1048576))),
            );
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($data === null) {
            return ['ok' => false, 'error' => "No such media file: {$path}"];
        }

        return ['ok' => true, 'data' => $data];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Path relative to the uploads directory.'],
                'offset' => ['type' => 'integer', 'description' => 'Byte offset to read from.'],
                'length' => ['type' => 'integer', 'description' => 'Bytes to read (capped at 4MB).'],
            ],
            'required' => ['path'],
        ];
    }
}
