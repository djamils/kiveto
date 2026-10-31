<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPublishedDocumentVersionFile;

use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetPublishedDocumentVersionFileHandler
{
    public function __construct(private PublishedDocumentReadModelInterface $documents)
    {
    }

    public function __invoke(GetPublishedDocumentVersionFile $query): ?string
    {
        if (!Uuid::isValid($query->clinicId) || !Uuid::isValid($query->documentId) || $query->version < 1) {
            return null;
        }

        return $this->documents->versionKey($query->clinicId, $query->documentId, $query->version);
    }
}
