<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Work queued for the data exports. Queued in the current transaction
 * (Doctrine transport): nothing runs for a change rolled back.
 */
interface DataExportJobsInterface
{
    /** Builds and stores the archive, outside any transaction. */
    public function queueBuild(string $exportId): void;

    /** Deletes the archive once its 48 hours are over. */
    public function deleteFileLater(string $exportId): void;
}
