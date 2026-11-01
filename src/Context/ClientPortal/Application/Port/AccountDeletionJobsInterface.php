<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The erasure of a deleted account, queued in the current transaction
 * (Doctrine transport): nothing runs for a request rolled back.
 */
interface AccountDeletionJobsInterface
{
    public function queueDeletion(string $userId): void;
}
