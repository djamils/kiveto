<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Infrastructure\Persistence\Doctrine\Mapper\ExchangeRateMapper;
use PHPUnit\Framework\TestCase;

final class ExchangeRateMapperTest extends TestCase
{
    public function testToDomainToEntitySymmetry(): void
    {
        $now    = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $mapper = new ExchangeRateMapper();

        $original = ExchangeRate::reconstitute(
            id: ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            pair: ExchangeRatePair::of(
                CurrencyCode::fromString('EUR'),
                CurrencyCode::fromString('CHF'),
            ),
            rate: '0.9523000000',
            effectiveDate: new \DateTimeImmutable('2026-05-14'),
            source: 'ECB',
            createdAt: $now,
        );

        $entity      = $mapper->toEntity($original);
        $reconverted = $mapper->toDomain($entity);

        self::assertSame($original->id()->toString(), $reconverted->id()->toString());
        self::assertSame($original->pair()->toString(), $reconverted->pair()->toString());
        self::assertSame($original->rate(), $reconverted->rate());
        self::assertSame(
            $original->effectiveDate()->format('Y-m-d'),
            $reconverted->effectiveDate()->format('Y-m-d'),
        );
        self::assertSame($original->source(), $reconverted->source());
        self::assertSame(
            $original->createdAt()->format(\DateTimeInterface::ATOM),
            $reconverted->createdAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
