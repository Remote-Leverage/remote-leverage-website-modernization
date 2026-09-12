<?php

declare(strict_types=1);

namespace App\Domains\Sync\Abilities;

use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Media\MediaFileReceiver;

/**
 * Tells the sender which media files this environment is missing.
 *
 * The whole point of asking first: the sender has ~512 attachments and their
 * generated sizes, and re-uploading all of them on every sync would dominate
 * the transfer. Comparing checksums means a re-run after a partial transfer
 * moves only what genuinely did not arrive.
 */
class CheckMediaFilesAbility extends TransferAbility
{
    public function __construct(private readonly MediaFileExporter $exporter) {}

    public function label(): string
    {
        return 'Check Media Files';
    }

    public function description(): string
    {
        return 'Returns which of the given media files this environment does not already have. '
            .'Internal sync tooling only.';
    }

    public function execute(array $input): mixed
    {
        $manifest = $input['files'] ?? [];

        if (! is_array($manifest)) {
            return ['ok' => false, 'error' => 'files must be an array.'];
        }

        $receiver = new MediaFileReceiver($this->exporter->baseDir());

        $missing = $receiver->missing(array_values(array_filter($manifest, 'is_array')));

        return ['ok' => true, 'missing' => $missing, 'checked' => count($manifest)];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'files' => [
                    'type' => 'array',
                    'description' => 'Entries of {path, size, sha256} relative to the uploads directory.',
                ],
            ],
            'required' => ['files'],
        ];
    }
}
