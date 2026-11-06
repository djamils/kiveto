<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\Repository;

use App\System\Audit\Domain\AuditEntry;

/**
 * Append-only: the log offers no update and no delete.
 */
interface AuditEntryRepositoryInterface
{
    public function append(AuditEntry $entry): void;
}
