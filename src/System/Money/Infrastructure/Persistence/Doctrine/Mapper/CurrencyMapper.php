<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\CurrencyEntity;

final readonly class CurrencyMapper
{
    public function toDomain(CurrencyEntity $entity): Currency
    {
        return Currency::reconstitute(
            code: CurrencyCode::fromString($entity->getCode()),
            symbol: CurrencySymbol::fromString($entity->getSymbol()),
            decimals: CurrencyDecimals::of($entity->getDecimals()),
            displayName: $entity->getDisplayName(),
            active: $entity->isActive(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(Currency $currency): CurrencyEntity
    {
        $entity = new CurrencyEntity();
        $entity->setCode($currency->code()->toString());
        $entity->setSymbol($currency->symbol()->toString());
        $entity->setDecimals($currency->decimals()->value());
        $entity->setDisplayName($currency->displayName());
        $entity->setActive($currency->isActive());
        $entity->setCreatedAt($currency->createdAt());
        $entity->setUpdatedAt($currency->updatedAt());

        return $entity;
    }
}
