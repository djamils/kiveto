<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are written and read in SQL by
 * DbalVeterinarianCountRepository.
 */
#[ORM\Entity]
#[ORM\Table]
class VeterinarianCountEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Id]
        #[ORM\Column(type: 'date_immutable')]
        private \DateTimeImmutable $day,
        #[ORM\Column(type: 'integer')]
        private int $count,
    ) {
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
