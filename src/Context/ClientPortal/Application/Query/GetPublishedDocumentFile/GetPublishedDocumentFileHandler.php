<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile;

use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Service\ListableSources;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/** The same live source check as the list: a document it hides has no file. */
#[AsMessageHandler]
final readonly class GetPublishedDocumentFileHandler
{
    public function __construct(
        private PublishedDocumentReadModelInterface $documents,
        private ListableSources $sources,
    ) {
    }

    public function __invoke(GetPublishedDocumentFile $query): ?PublishedDocumentFile
    {
        if (!Uuid::isValid($query->documentId)) {
            return null;
        }

        $document = $this->documents->find($query->userId, $query->clinicId, $query->documentId);

        if (null === $document || [] === $this->sources->keep($query->clinicId, [$document])) {
            return null;
        }

        return new PublishedDocumentFile($document->id, $document->fileVersion, $document->storageKey);
    }
}
