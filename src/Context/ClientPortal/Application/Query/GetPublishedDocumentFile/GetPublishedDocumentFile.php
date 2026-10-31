<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The served file of a document the account sees, to sign a download URL.
 * Answers a PublishedDocumentFile, or null (not listable for the account).
 */
final readonly class GetPublishedDocumentFile implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $documentId,
    ) {
    }
}
