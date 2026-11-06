<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the features set for each clinic; read and written in SQL by
 * DbalFeatureRepository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'feature_catalog__clinic_features')]
#[ORM\Index(name: 'idx_clinic_feature_code', columns: ['code'])]
class ClinicFeatureEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        public Uuid $clinicId,
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        public string $code,
        #[ORM\Column(type: 'boolean')]
        public bool $enabled,
        #[ORM\Column(name: 'changed_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $changedAt,
        #[ORM\Column(name: 'changed_by_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $changedById,
        #[ORM\Column(name: 'changed_by_name', type: 'string', length: 160, nullable: true)]
        public ?string $changedByName,
    ) {
    }
}
