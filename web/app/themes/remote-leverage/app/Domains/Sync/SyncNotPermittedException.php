<?php

declare(strict_types=1);

namespace App\Domains\Sync;

use RuntimeException;

/**
 * Thrown when a sync operation is attempted somewhere it must never run —
 * production, or an environment WP_ENV doesn't recognise.
 */
class SyncNotPermittedException extends RuntimeException {}
