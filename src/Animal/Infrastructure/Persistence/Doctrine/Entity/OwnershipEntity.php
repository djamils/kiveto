<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'animal__ownership')]
#[ORM\Index(columns: ['animal_id'], name: 'idx_ownership_animal')]
#[ORM\Index(columns: ['client_id'], name: 'idx_ownership_client')]
#[ORM\Index(columns: ['status'], name: 'idx_ownership_status')]
class OwnershipEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnimalEntity::class, inversedBy: 'ownerships')]
    #[ORM\JoinColumn(name: 'animal_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public AnimalEntity $animal;

    #[ORM\Column(type: Types::STRING, length: 36, name: 'client_id')]
    public string $clientId;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $role;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'started_at')]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'ended_at')]
    public ?\DateTimeImmutable $endedAt = null;
}
