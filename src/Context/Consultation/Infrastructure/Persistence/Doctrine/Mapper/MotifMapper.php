<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\MotifTag;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\MotifEntity;
use Symfony\Component\Uid\Uuid;

final class MotifMapper
{
    public function toEntity(
        MotifTag $motif,
        string $consultationIdBinary,
        int $position,
        ?MotifEntity $entity = null,
    ): MotifEntity {
        $entity ??= new MotifEntity();
        $entity->setId(Uuid::fromString($motif->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setLabel($motif->getLabel());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(MotifEntity $entity): MotifTag
    {
        return MotifTag::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            label: $entity->getLabel(),
        );
    }
}
