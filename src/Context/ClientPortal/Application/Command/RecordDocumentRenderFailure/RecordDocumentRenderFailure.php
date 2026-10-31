<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RecordDocumentRenderFailure;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordDocumentRenderFailure implements CommandInterface
{
    public function __construct(
        public string $documentId,
    ) {
    }
}
