<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An organisation's connection to its approved platform.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_ei_connection_organisation', columns: ['organisation_id'])]
class PlatformConnectionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'organisation_id', type: 'string', length: 9)]
    private string $organisationId;

    #[ORM\Column(type: 'string', length: 32)]
    private string $platform;

    #[ORM\Column(type: 'string', length: 16)]
    private string $state;

    #[ORM\Column(name: 'rejection_reason', type: 'string', length: 255, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(name: 'cursor_updated_after', type: 'datetime_immutable', precision: 6)]
    private \DateTimeImmutable $cursorUpdatedAfter;

    #[ORM\Column(name: 'cursor_last_flow_id', type: 'string', length: 64)]
    private string $cursorLastFlowId = '';

    #[ORM\Column(name: 'opened_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(name: 'verified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getOrganisationId(): string
    {
        return $this->organisationId;
    }

    public function setOrganisationId(string $organisationId): void
    {
        $this->organisationId = $organisationId;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): void
    {
        $this->platform = $platform;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): void
    {
        $this->rejectionReason = $rejectionReason;
    }

    public function getCursorUpdatedAfter(): \DateTimeImmutable
    {
        return $this->cursorUpdatedAfter;
    }

    public function setCursorUpdatedAfter(\DateTimeImmutable $cursorUpdatedAfter): void
    {
        $this->cursorUpdatedAfter = $cursorUpdatedAfter;
    }

    public function getCursorLastFlowId(): string
    {
        return $this->cursorLastFlowId;
    }

    public function setCursorLastFlowId(string $cursorLastFlowId): void
    {
        $this->cursorLastFlowId = $cursorLastFlowId;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeImmutable $openedAt): void
    {
        $this->openedAt = $openedAt;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): void
    {
        $this->verifiedAt = $verifiedAt;
    }
}
