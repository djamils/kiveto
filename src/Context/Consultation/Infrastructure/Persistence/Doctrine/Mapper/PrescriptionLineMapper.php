<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\PrescriptionLineRecord;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use Symfony\Component\Uid\Uuid;

final class PrescriptionLineMapper
{
    public function toEntity(
        PrescriptionLineRecord $line,
        string $consultationIdBinary,
        int $position,
        ?PrescriptionLineEntity $entity = null,
    ): PrescriptionLineEntity {
        $articleId = $line->getArticleId();

        $entity ??= new PrescriptionLineEntity();
        $entity->setId(Uuid::fromString($line->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setArticleId(null !== $articleId ? Uuid::fromString($articleId)->toBinary() : null);
        $entity->setCode($line->getCode());
        $entity->setLabel($line->getLabel());
        $entity->setDose($line->getDose());
        $entity->setFrequency($line->getFrequency());
        $entity->setDurationDays($line->getDurationDays());
        $entity->setRoute($line->getRoute());
        $entity->setQuantity((string) $line->getQuantity());
        $entity->setUnitPriceMinorUnits($line->getUnitPriceMinorUnits());
        $entity->setCurrency($line->getCurrency());
        $entity->setTaxCategoryCode($line->getTaxCategoryCode());
        $entity->setCreatedAtUtc($line->getCreatedAtUtc());
        $entity->setCreatedByUserId(Uuid::fromString($line->getCreatedByUserId())->toBinary());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(PrescriptionLineEntity $entity): PrescriptionLineRecord
    {
        $articleId = $entity->getArticleId();

        return PrescriptionLineRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            articleId: null !== $articleId ? Uuid::fromBinary($articleId)->toRfc4122() : null,
            code: $entity->getCode(),
            label: $entity->getLabel(),
            dose: $entity->getDose(),
            frequency: $entity->getFrequency(),
            durationDays: $entity->getDurationDays(),
            route: $entity->getRoute(),
            quantity: (float) $entity->getQuantity(),
            unitPriceMinorUnits: $entity->getUnitPriceMinorUnits(),
            currency: $entity->getCurrency(),
            taxCategoryCode: $entity->getTaxCategoryCode(),
            createdAtUtc: $entity->getCreatedAtUtc(),
            createdByUserId: Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122(),
        );
    }
}
