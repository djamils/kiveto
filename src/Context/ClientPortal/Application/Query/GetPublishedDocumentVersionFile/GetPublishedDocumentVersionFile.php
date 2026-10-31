<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPublishedDocumentVersionFile;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The storage key of a signed download (clinic, document, version): the
 * version keeps being served after a re-share until its file is deleted,
 * never once the document is withdrawn. Answers a string key, or null.
 */
final readonly class GetPublishedDocumentVersionFile implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $documentId,
        public int $version,
    ) {
    }
}
