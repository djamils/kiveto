<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * Renders (Gotenberg) and stores the file of one version of a published
 * document, outside any transaction, then records the outcome through the
 * short AttachPublishedDocumentFile / RecordDocumentRenderFailure commands.
 */
final readonly class StorePublishedDocumentFile implements ExternalApiCommandInterface
{
    public function __construct(
        public string $documentId,
        public int $version,
    ) {
    }
}
