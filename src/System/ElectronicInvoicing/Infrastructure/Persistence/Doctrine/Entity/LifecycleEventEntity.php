<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A lifecycle status reported for a transmission; append-only.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_ei_lifecycle_event', columns: ['transmission_id', 'status_code', 'source_flow_id'])]
class LifecycleEventEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'transmission_id', type: UuidType::NAME)]
    private Uuid $transmissionId;

    #[ORM\Column(name: 'status_code', type: 'smallint')]
    private int $statusCode;

    #[ORM\Column(name: 'reason_code', type: 'string', length: 40, nullable: true)]
    private ?string $reasonCode = null;

    #[ORM\Column(name: 'reason_text', type: 'string', length: 512, nullable: true)]
    private ?string $reasonText = null;

    #[ORM\Column(name: 'source_flow_id', type: 'string', length: 64)]
    private string $sourceFlowId;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getTransmissionId(): Uuid
    {
        return $this->transmissionId;
    }

    public function setTransmissionId(Uuid $transmissionId): void
    {
        $this->transmissionId = $transmissionId;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function setReasonCode(?string $reasonCode): void
    {
        $this->reasonCode = $reasonCode;
    }

    public function getReasonText(): ?string
    {
        return $this->reasonText;
    }

    public function setReasonText(?string $reasonText): void
    {
        $this->reasonText = $reasonText;
    }

    public function getSourceFlowId(): string
    {
        return $this->sourceFlowId;
    }

    public function setSourceFlowId(string $sourceFlowId): void
    {
        $this->sourceFlowId = $sourceFlowId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }
}
