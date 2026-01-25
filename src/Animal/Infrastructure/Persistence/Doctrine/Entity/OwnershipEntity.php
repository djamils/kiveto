<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Animal\Domain\Enum\OwnershipRole;
use App\Animal\Domain\Enum\OwnershipStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'animal__ownership')]
#[ORM\Index(columns: ['animal_id'], name: 'idx_ownership_animal')]
#[ORM\Index(columns: ['client_id'], name: 'idx_ownership_client')]
#[ORM\Index(columns: ['status'], name: 'idx_ownership_status')]
class OwnershipEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: AnimalEntity::class, inversedBy: 'ownerships')]
    #[ORM\JoinColumn(name: 'animal_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AnimalEntity $animal = null;

    #[ORM\Column(type: UuidType::NAME, name: 'client_id')]
    private Uuid $clientId;

    #[ORM\Column(type: 'string', length: 50, enumType: OwnershipRole::class)]
    private OwnershipRole $role;

    #[ORM\Column(type: 'string', length: 50, enumType: OwnershipStatus::class)]
    private OwnershipStatus $status;

    #[ORM\Column(type: 'datetime_immutable', name: 'started_at')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'ended_at')]
    private ?\DateTimeImmutable $endedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnimal(): ?AnimalEntity
    {
        return $this->animal;
    }

    public function setAnimal(?AnimalEntity $animal): void
    {
        $this->animal = $animal;
    }

    public function getClientId(): Uuid
    {
        return $this->clientId;
    }

    public function setClientId(Uuid $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getRole(): OwnershipRole
    {
        return $this->role;
    }

    public function setRole(OwnershipRole $role): void
    {
        $this->role = $role;
    }

    public function getStatus(): OwnershipStatus
    {
        return $this->status;
    }

    public function setStatus(OwnershipStatus $status): void
    {
        $this->status = $status;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): void
    {
        $this->endedAt = $endedAt;
    }
}
