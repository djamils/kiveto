<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Export;

/**
 * `pending` while being built, `ready` while the archive can be downloaded
 * (48 h), `failed` when the build failed, `expired` once the archive is
 * gone (or its 48 h are over, even before the deletion ran).
 */
enum DataExportStatus: string
{
    case PENDING = 'pending';
    case READY   = 'ready';
    case FAILED  = 'failed';
    case EXPIRED = 'expired';
}
