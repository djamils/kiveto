<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Infrastructure\Persistence\Doctrine\Entity\ExchangeRateEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ExchangeRateMapper
{
    public function toDomain(ExchangeRateEntity $entity): ExchangeRate
    {
        return ExchangeRate::reconstitute(
            id: ExchangeRateId::fromString($entity->getId()->toString()),
            pair: ExchangeRatePair::of(
                CurrencyCode::fromString($entity->getCurrencyFrom()),
                CurrencyCode::fromString($entity->getCurrencyTo()),
            ),
            rate: $entity->getRate(),
            effectiveDate: $entity->getEffectiveDate(),
            source: $entity->getSource(),
            createdAt: $entity->getCreatedAt(),
        );
    }

    public function toEntity(ExchangeRate $exchangeRate): ExchangeRateEntity
    {
        $entity = new ExchangeRateEntity();
        $entity->setId(Uuid::fromString($exchangeRate->id()->toString()));
        $entity->setCurrencyFrom($exchangeRate->pair()->from()->toString());
        $entity->setCurrencyTo($exchangeRate->pair()->to()->toString());
        $entity->setRate($exchangeRate->rate());
        $entity->setEffectiveDate($exchangeRate->effectiveDate());
        $entity->setSource($exchangeRate->source());
        $entity->setCreatedAt($exchangeRate->createdAt());

        return $entity;
    }
}
