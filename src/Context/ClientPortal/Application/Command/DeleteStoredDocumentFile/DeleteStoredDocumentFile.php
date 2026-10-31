<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile;

use App\Shared\Application\Bus\CommandInterface;

/** A file of the Portal's own storage, no longer served (routed `async`, delayed). */
final readonly class DeleteStoredDocumentFile implements CommandInterface
{
    public function __construct(
        public string $storageKey,
    ) {
    }
}
