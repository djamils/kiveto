<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\BillingLineRecord;
use App\Context\Consultation\Domain\ValueObject\BillingLineSource;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\BillingLineEntity;
use Symfony\Component\Uid\Uuid;

final class BillingLineMapper
{
    public function toEntity(
        BillingLineRecord $line,
        string $consultationIdBinary,
        int $position,
        ?BillingLineEntity $entity = null,
    ): BillingLineEntity {
        $entity ??= new BillingLineEntity();
        $entity->setId(Uuid::fromString($line->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setSourceLineId(Uuid::fromString($line->getSourceLineId())->toBinary());
        $entity->setSource($line->getSource()->value);
        $entity->setLabel($line->getLabel());
        $entity->setCode($line->getCode());
        $entity->setQuantity((string) $line->getQuantity());
        $entity->setUnitPriceMinorUnits($line->getUnitPriceMinorUnits());
        $entity->setCurrency($line->getCurrency());
        $entity->setTaxCategoryCode($line->getTaxCategoryCode());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(BillingLineEntity $entity): BillingLineRecord
    {
        return BillingLineRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            sourceLineId: Uuid::fromBinary($entity->getSourceLineId())->toRfc4122(),
            source: BillingLineSource::from($entity->getSource()),
            label: $entity->getLabel(),
            code: $entity->getCode(),
            quantity: (float) $entity->getQuantity(),
            unitPriceMinorUnits: $entity->getUnitPriceMinorUnits(),
            currency: $entity->getCurrency(),
            taxCategoryCode: $entity->getTaxCategoryCode(),
        );
    }
}
