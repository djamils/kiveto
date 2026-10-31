<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Document;

/** Which Portal account has opened which document (read/unread badge). */
interface DocumentReadRepositoryInterface
{
    /** Idempotent: the first read date is kept. */
    public function markRead(string $documentId, string $userId, \DateTimeImmutable $at): void;

    /** A new version of the document is unread again for everyone. */
    public function forgetReads(string $documentId): void;
}
