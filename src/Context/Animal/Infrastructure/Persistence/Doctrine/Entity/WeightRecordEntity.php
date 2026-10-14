<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'animal__weight_records')]
#[ORM\Index(name: 'idx_animal_weight_record_animal', columns: ['clinic_id', 'animal_id', 'measured_on'])]
class WeightRecordEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $animalId,
        #[ORM\Column(type: 'integer')]
        public int $weightGrams,
        #[ORM\Column(type: 'smallint', nullable: true)]
        public ?int $bodyConditionScore,
        #[ORM\Column(type: 'date_immutable')]
        public \DateTimeImmutable $measuredOn,
        #[ORM\Column(type: 'string', length: 255)]
        public string $authorName,
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $recordedAt,
    ) {
    }
}
