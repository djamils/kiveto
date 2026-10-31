<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPublishedDocumentFile;

use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetPublishedDocumentFileHandler
{
    public function __construct(private PublishedDocumentReadModelInterface $documents)
    {
    }

    public function __invoke(GetPublishedDocumentFile $query): ?PublishedDocumentFile
    {
        if (!Uuid::isValid($query->documentId)) {
            return null;
        }

        $document = $this->documents->find($query->userId, $query->clinicId, $query->documentId);

        return null === $document ? null : new PublishedDocumentFile($document->id, $document->fileVersion, $document->storageKey);
    }
}
