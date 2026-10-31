<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the read marks of published documents (one per document and
 * Portal account); rows are written in SQL by DbalDocumentReadRepository.
 */
#[ORM\Entity]
#[ORM\Table]
class DocumentReadEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'document_id', type: UuidType::NAME)]
        public Uuid $documentId,
        #[ORM\Id]
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        public Uuid $userId,
        #[ORM\Column(name: 'read_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $readAt,
    ) {
    }
}
