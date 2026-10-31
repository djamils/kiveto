<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\MarkDocumentRead;

use App\Shared\Application\Bus\CommandInterface;

/** An owner opened a document (its access was checked by the caller). */
final readonly class MarkDocumentRead implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $documentId,
    ) {
    }
}
