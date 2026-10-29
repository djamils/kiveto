<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are read and written in SQL by DbalSentDocumentFileStore.
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing__sent_document_files')]
class SentDocumentFileEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'document_id', type: UuidType::NAME)]
        private Uuid $documentId,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Column(name: 'document_kind', type: 'string', length: 16)]
        private string $documentKind,
        #[ORM\Column(name: 'storage_key', type: 'string', length: 255)]
        private string $storageKey,
        #[ORM\Column(type: 'string', length: 255)]
        private string $filename,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array{document_id: Uuid, clinic_id: Uuid, document_kind: string, storage_key: string, filename: string, created_at: \DateTimeImmutable}
     */
    public function columns(): array
    {
        return [
            'document_id'   => $this->documentId,
            'clinic_id'     => $this->clinicId,
            'document_kind' => $this->documentKind,
            'storage_key'   => $this->storageKey,
            'filename'      => $this->filename,
            'created_at'    => $this->createdAt,
        ];
    }
}
