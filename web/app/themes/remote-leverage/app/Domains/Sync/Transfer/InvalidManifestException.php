<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use InvalidArgumentException;

/**
 * Thrown when a requested transfer is not one this tool will perform —
 * an unknown dataset, a purge-only dataset asked to transfer, a bad direction.
 */
class InvalidManifestException extends InvalidArgumentException {}
