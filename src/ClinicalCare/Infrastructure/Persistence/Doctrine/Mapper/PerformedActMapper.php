<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Mapper;

use App\ClinicalCare\Domain\ValueObject\PerformedActRecord;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PerformedActMapper
{
    public function __construct(
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function toEntity(PerformedActRecord $act, string $consultationIdBinary): PerformedActEntity
    {
        $entity = new PerformedActEntity();
        $entity->setId(Uuid::fromString($this->uuidGenerator->generate())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setLabel($act->label);
        $entity->setQuantity((string) $act->quantity);
        $entity->setPerformedAtUtc($act->performedAt);
        $entity->setCreatedAtUtc($act->createdAt);
        $entity->setCreatedByUserId(Uuid::fromString($act->createdByUserId->toString())->toBinary());

        return $entity;
    }

    public function toDomain(PerformedActEntity $entity): PerformedActRecord
    {
        return new PerformedActRecord(
            label: $entity->getLabel(),
            quantity: (float) $entity->getQuantity(),
            performedAt: $entity->getPerformedAtUtc(),
            createdAt: $entity->getCreatedAtUtc(),
            createdByUserId: UserId::fromString(Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122()),
        );
    }
}
