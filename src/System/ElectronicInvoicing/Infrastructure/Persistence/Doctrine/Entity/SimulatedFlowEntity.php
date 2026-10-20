<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A flow of the local platform simulator (dev / test / demo only).
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_ei_simulated_flow_deposit', columns: ['tracking_id', 'sha256'])]
#[ORM\Index(name: 'idx_ei_simulated_flow_organisation', columns: ['organisation_id'])]
#[ORM\Index(name: 'idx_ei_simulated_flow_document', columns: ['seller_siren', 'document_number'])]
class SimulatedFlowEntity
{
    /**
     * @param list<array{code: int, reason: ?string, at: string}> $statuses
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'flow_id', type: 'string', length: 64)]
        private string $flowId,
        #[ORM\Column(name: 'organisation_id', type: 'string', length: 9)]
        private string $organisationId,
        #[ORM\Column(name: 'tracking_id', type: 'string', length: 64)]
        private string $trackingId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $sha256,
        #[ORM\Column(name: 'document_number', type: 'string', length: 64)]
        private string $documentNumber,
        #[ORM\Column(name: 'type_code', type: 'string', length: 3)]
        private string $typeCode,
        #[ORM\Column(name: 'issue_date', type: 'date_immutable', nullable: true)]
        private ?\DateTimeImmutable $issueDate,
        #[ORM\Column(name: 'seller_siren', type: 'string', length: 9)]
        private string $sellerSiren,
        #[ORM\Column(name: 'buyer_siren', type: 'string', length: 9, nullable: true)]
        private ?string $buyerSiren,
        #[ORM\Column(type: 'string', length: 20)]
        private string $scenario,
        #[ORM\Column(type: 'text')]
        private string $file,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'json')]
        private array $statuses,
        #[ORM\Column(name: 'next_step', type: 'smallint', nullable: true)]
        private ?int $nextStep,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<array{code: int, reason: ?string, at: string}> $statuses
     */
    public function record(array $statuses, ?int $nextStep, \DateTimeImmutable $updatedAt): void
    {
        $this->statuses  = $statuses;
        $this->nextStep  = $nextStep;
        $this->updatedAt = $updatedAt;
    }

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function getOrganisationId(): string
    {
        return $this->organisationId;
    }

    public function getTrackingId(): string
    {
        return $this->trackingId;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getTypeCode(): string
    {
        return $this->typeCode;
    }

    public function getIssueDate(): ?\DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getSellerSiren(): string
    {
        return $this->sellerSiren;
    }

    public function getBuyerSiren(): ?string
    {
        return $this->buyerSiren;
    }

    public function getScenario(): string
    {
        return $this->scenario;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return list<array{code: int, reason: ?string, at: string}>
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function getNextStep(): ?int
    {
        return $this->nextStep;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
