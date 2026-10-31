<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile;

final readonly class PublishedDocumentFile
{
    public function __construct(
        public string $documentId,
        public int $version,
        public string $storageKey,
    ) {
    }
}
