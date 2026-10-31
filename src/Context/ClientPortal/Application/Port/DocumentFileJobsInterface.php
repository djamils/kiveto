<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Work queued for the published documents' files. Queued in the current
 * transaction (Doctrine transport): nothing runs for a change rolled back.
 */
interface DocumentFileJobsInterface
{
    /** Renders and stores the file of a document version, outside any transaction. */
    public function storeFile(string $documentId, int $version): void;

    /** Deletes a replaced file later, once no signed URL can still point to it. */
    public function deleteFileLater(string $storageKey): void;
}
