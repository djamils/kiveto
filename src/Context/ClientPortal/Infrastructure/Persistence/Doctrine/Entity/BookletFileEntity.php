<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the current vaccination booklet file of each animal; rows are
 * written in SQL by DbalBookletFileRepository.
 */
#[ORM\Entity]
#[ORM\Table]
class BookletFileEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'animal_id', type: UuidType::NAME)]
        public Uuid $animalId,
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(name: 'storage_key', length: 255)]
        public string $storageKey,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
