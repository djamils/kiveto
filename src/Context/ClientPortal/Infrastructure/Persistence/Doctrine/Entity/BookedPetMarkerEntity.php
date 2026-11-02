<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the animals registered by « new pet » bookings, by marker
 * (S4B-01); rows are written and read in SQL by DbalBookedPetMarkerRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_booked_pet_marker_created', columns: ['created_at'])]
class BookedPetMarkerEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64, options: ['fixed' => true])]
        public string $marker,
        #[ORM\Column(name: 'animal_id', type: UuidType::NAME)]
        public Uuid $animalId,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
