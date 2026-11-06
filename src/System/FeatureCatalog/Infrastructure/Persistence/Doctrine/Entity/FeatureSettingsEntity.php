<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Schema of the saved global settings of the features; read and written in
 * SQL by DbalFeatureRepository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'feature_catalog__features')]
class FeatureSettingsEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        public string $code,
        #[ORM\Column(type: 'string', length: 16)]
        public string $status,
        #[ORM\Column(name: 'default_on', type: 'boolean')]
        public bool $defaultOn,
        #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }
}
