<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Domain\Dispatch\SentDocumentKind;

/**
 * Which document PDFs were stored at their first send, and where.
 */
interface SentDocumentFileStoreInterface
{
    /** A plain read: the file recorded for this document of the clinic, if any. */
    public function find(string $clinicId, string $documentId): ?SentDocumentFile;

    /**
     * Records the file unless the document already has one (INSERT IGNORE),
     * then reads the row as committed — a locking read, which sees the row a
     * concurrent first send committed although this transaction's snapshot
     * predates it.
     */
    public function record(string $clinicId, SentDocumentKind $kind, string $documentId, SentDocumentFile $file, \DateTimeImmutable $at): SentDocumentFile;
}
