<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'animal__preventive_care_records')]
#[ORM\Index(name: 'idx_animal_preventive_care_animal', columns: ['clinic_id', 'animal_id', 'administered_on'])]
class PreventiveCareRecordEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $animalId,
        #[ORM\Column(type: 'string', length: 30, enumType: PreventiveCareKind::class)]
        public PreventiveCareKind $kind,
        #[ORM\Column(type: 'string', length: 120)]
        public string $label,
        #[ORM\Column(type: 'date_immutable')]
        public \DateTimeImmutable $administeredOn,
        #[ORM\Column(type: 'string', length: 60, nullable: true)]
        public ?string $batchNumber,
        #[ORM\Column(type: 'date_immutable', nullable: true)]
        public ?\DateTimeImmutable $nextDueOn,
        #[ORM\Column(type: 'string', length: 255)]
        public string $authorName,
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $recordedAt,
    ) {
    }
}
