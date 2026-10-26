<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A document on its way to the platform.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_ei_transmission_tracking', columns: ['tracking_id'])]
#[ORM\UniqueConstraint(name: 'uniq_ei_transmission_document', columns: ['seller_siren', 'kind', 'document_number'])]
#[ORM\UniqueConstraint(name: 'uniq_ei_transmission_flow', columns: ['flow_id'])]
#[ORM\Index(name: 'idx_ei_transmission_schedule', columns: ['seller_siren', 'state', 'submission_requested_at'])]
class OutboundTransmissionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 16)]
    private string $kind;

    #[ORM\Column(name: 'tracking_id', type: 'string', length: 64)]
    private string $trackingId;

    #[ORM\Column(name: 'document_number', type: 'string', length: 40)]
    private string $documentNumber;

    #[ORM\Column(name: 'document_date', type: 'date_immutable')]
    private \DateTimeImmutable $documentDate;

    #[ORM\Column(name: 'seller_siren', type: 'string', length: 9)]
    private string $sellerSiren;

    #[ORM\Column(name: 'buyer_siren', type: 'string', length: 9, nullable: true)]
    private ?string $buyerSiren = null;

    #[ORM\Column(name: 'preceding_document_number', type: 'string', length: 40, nullable: true)]
    private ?string $precedingDocumentNumber = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(name: 'connection_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $connectionId = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $state;

    #[ORM\Column(name: 'block_reason', type: 'string', length: 40, nullable: true)]
    private ?string $blockReason = null;

    #[ORM\Column(name: 'failure_detail', type: 'text', nullable: true)]
    private ?string $failureDetail = null;

    #[ORM\Column(name: 'document_id', type: 'string', length: 255, nullable: true)]
    private ?string $documentId = null;

    #[ORM\Column(name: 'document_sha256', type: 'string', length: 64, nullable: true)]
    private ?string $documentSha256 = null;

    #[ORM\Column(name: 'electronic_address_scheme', type: 'string', length: 8, nullable: true)]
    private ?string $electronicAddressScheme = null;

    #[ORM\Column(name: 'electronic_address_identifier', type: 'string', length: 64, nullable: true)]
    private ?string $electronicAddressIdentifier = null;

    #[ORM\Column(name: 'flow_id', type: 'string', length: 64, nullable: true)]
    private ?string $flowId = null;

    #[ORM\Column(name: 'submission_requested_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submissionRequestedAt = null;

    #[ORM\Column(name: 'submission_attempts', type: 'integer')]
    private int $submissionAttempts = 0;

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(name: 'internal_because', type: 'smallint', nullable: true)]
    private ?int $internalBecause = null;

    #[ORM\Column(type: 'integer')]
    private int $sequence = 0;

    /** Optimistic lock: a concurrent change fails the flush and the message is retried. */
    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'prepared_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $preparedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }

    public function getTrackingId(): string
    {
        return $this->trackingId;
    }

    public function setTrackingId(string $trackingId): void
    {
        $this->trackingId = $trackingId;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): void
    {
        $this->documentNumber = $documentNumber;
    }

    public function getDocumentDate(): \DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function setDocumentDate(\DateTimeImmutable $documentDate): void
    {
        $this->documentDate = $documentDate;
    }

    public function getSellerSiren(): string
    {
        return $this->sellerSiren;
    }

    public function setSellerSiren(string $sellerSiren): void
    {
        $this->sellerSiren = $sellerSiren;
    }

    public function getBuyerSiren(): ?string
    {
        return $this->buyerSiren;
    }

    public function setBuyerSiren(?string $buyerSiren): void
    {
        $this->buyerSiren = $buyerSiren;
    }

    public function getPrecedingDocumentNumber(): ?string
    {
        return $this->precedingDocumentNumber;
    }

    public function setPrecedingDocumentNumber(?string $precedingDocumentNumber): void
    {
        $this->precedingDocumentNumber = $precedingDocumentNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getConnectionId(): ?Uuid
    {
        return $this->connectionId;
    }

    public function setConnectionId(?Uuid $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function setBlockReason(?string $blockReason): void
    {
        $this->blockReason = $blockReason;
    }

    public function getFailureDetail(): ?string
    {
        return $this->failureDetail;
    }

    public function setFailureDetail(?string $failureDetail): void
    {
        $this->failureDetail = $failureDetail;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function setDocumentId(?string $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function getDocumentSha256(): ?string
    {
        return $this->documentSha256;
    }

    public function setDocumentSha256(?string $documentSha256): void
    {
        $this->documentSha256 = $documentSha256;
    }

    public function getElectronicAddressScheme(): ?string
    {
        return $this->electronicAddressScheme;
    }

    public function setElectronicAddressScheme(?string $electronicAddressScheme): void
    {
        $this->electronicAddressScheme = $electronicAddressScheme;
    }

    public function getElectronicAddressIdentifier(): ?string
    {
        return $this->electronicAddressIdentifier;
    }

    public function setElectronicAddressIdentifier(?string $electronicAddressIdentifier): void
    {
        $this->electronicAddressIdentifier = $electronicAddressIdentifier;
    }

    public function getFlowId(): ?string
    {
        return $this->flowId;
    }

    public function setFlowId(?string $flowId): void
    {
        $this->flowId = $flowId;
    }

    public function getSubmissionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->submissionRequestedAt;
    }

    public function setSubmissionRequestedAt(?\DateTimeImmutable $submissionRequestedAt): void
    {
        $this->submissionRequestedAt = $submissionRequestedAt;
    }

    public function getSubmissionAttempts(): int
    {
        return $this->submissionAttempts;
    }

    public function setSubmissionAttempts(int $submissionAttempts): void
    {
        $this->submissionAttempts = $submissionAttempts;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): void
    {
        $this->submittedAt = $submittedAt;
    }

    public function getInternalBecause(): ?int
    {
        return $this->internalBecause;
    }

    public function setInternalBecause(?int $internalBecause): void
    {
        $this->internalBecause = $internalBecause;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setSequence(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function getPreparedAt(): \DateTimeImmutable
    {
        return $this->preparedAt;
    }

    public function setPreparedAt(\DateTimeImmutable $preparedAt): void
    {
        $this->preparedAt = $preparedAt;
    }
}
