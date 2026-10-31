<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AttachPublishedDocumentFile;

use App\Shared\Application\Bus\CommandInterface;

/** The stored file of a document version: served from now on if still expected. */
final readonly class AttachPublishedDocumentFile implements CommandInterface
{
    public function __construct(
        public string $documentId,
        public int $version,
        public string $storageKey,
    ) {
    }
}
