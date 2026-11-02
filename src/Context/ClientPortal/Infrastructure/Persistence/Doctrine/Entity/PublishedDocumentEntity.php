<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the Portal's publication registry; rows are written and read in
 * SQL by DbalPublishedDocumentRepository and DbalPublishedDocumentReadModel.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_published_document_source', columns: ['clinic_id', 'type', 'source_ref'])]
#[ORM\Index(name: 'idx_published_document_client', columns: ['clinic_id', 'client_id', 'status', 'issued_at', 'id'])]
#[ORM\Index(name: 'idx_published_document_patient', columns: ['clinic_id', 'patient_id'])]
#[ORM\Index(name: 'idx_published_document_pending', columns: ['status', 'updated_at'])]
#[ORM\Index(name: 'idx_published_document_key', columns: ['storage_key'])]
class PublishedDocumentEntity
{
    /**
     * @param list<string> $petIds
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'client_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $clientId,
        #[ORM\Column(type: 'string', length: 16)]
        public string $type,
        #[ORM\Column(name: 'source_ref', type: UuidType::NAME)]
        public Uuid $sourceRef,
        #[ORM\Column(name: 'consultation_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $consultationId,
        #[ORM\Column(name: 'patient_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $patientId,
        #[ORM\Column(name: 'pet_ids', type: 'json')]
        public array $petIds,
        #[ORM\Column(type: 'string', length: 255)]
        public string $title,
        #[ORM\Column(name: 'issued_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $issuedAt,
        #[ORM\Column(name: 'practitioner_name', type: 'string', length: 255, nullable: true)]
        public ?string $practitionerName,
        #[ORM\Column(name: 'storage_key', type: 'string', length: 255, nullable: true)]
        public ?string $storageKey,
        #[ORM\Column(name: 'file_version', type: 'integer')]
        public int $fileVersion,
        #[ORM\Column(name: 'pending_file_version', type: 'integer', nullable: true)]
        public ?int $pendingFileVersion,
        #[ORM\Column(name: 'render_attempts', type: 'integer')]
        public int $renderAttempts,
        #[ORM\Column(type: 'string', length: 16)]
        public string $status,
        #[ORM\Column(name: 'published_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $publishedAt,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'shared_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $sharedAt,
    ) {
    }
}
