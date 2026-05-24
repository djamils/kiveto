<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDescription;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Uid\Uuid;

final readonly class ActMapper
{
    public function toEntity(Act $act, ?ActEntity $existing = null): ActEntity
    {
        $entity = $existing ?? new ActEntity();

        $entity->setId(Uuid::fromString($act->id()->toString()));
        $entity->setTenantId(Uuid::fromString($act->clinicId()->toString()));
        $entity->setName($act->name()->toString());
        $entity->setCode($act->code()->toString());
        $entity->setDescription($act->description()?->toString());
        $entity->setCategory($act->category()->value);
        $entity->setTaxCategoryCode($act->taxCategory()->toString());
        $entity->setBasePriceMinorUnits($act->basePrice()->minorUnits());
        $entity->setBasePriceCurrency($act->basePrice()->currency()->toString());
        $entity->setEstimatedDurationMinutes($act->estimatedDuration()->toMinutes());
        $entity->setRequiresAnesthesia($act->requiresAnesthesia());
        $entity->setStatus($act->status());
        $entity->setCreatedAt($act->createdAt());
        $entity->setUpdatedAt($act->updatedAt());

        return $entity;
    }

    public function toDomain(ActEntity $entity): Act
    {
        return Act::reconstitute(
            id: ActId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getTenantId()->toString()),
            name: ActName::fromString($entity->getName()),
            code: ActCode::fromString($entity->getCode()),
            description: ActDescription::fromNullableString($entity->getDescription()),
            category: ActCategory::from($entity->getCategory()),
            taxCategory: TaxCategoryCode::fromString($entity->getTaxCategoryCode()),
            basePrice: Money::fromMinorUnits(
                $entity->getBasePriceMinorUnits(),
                CurrencyCode::fromString($entity->getBasePriceCurrency()),
            ),
            estimatedDuration: ActDuration::ofMinutes($entity->getEstimatedDurationMinutes()),
            requiresAnesthesia: $entity->getRequiresAnesthesia(),
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
