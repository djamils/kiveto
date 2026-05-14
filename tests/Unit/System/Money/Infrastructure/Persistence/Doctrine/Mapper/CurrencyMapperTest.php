<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use App\System\Money\Infrastructure\Persistence\Doctrine\Mapper\CurrencyMapper;
use PHPUnit\Framework\TestCase;

final class CurrencyMapperTest extends TestCase
{
    public function testToDomainToEntitySymmetry(): void
    {
        $now    = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $mapper = new CurrencyMapper();

        $original = Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity      = $mapper->toEntity($original);
        $reconverted = $mapper->toDomain($entity);

        self::assertSame($original->code()->toString(), $reconverted->code()->toString());
        self::assertSame($original->symbol()->toString(), $reconverted->symbol()->toString());
        self::assertSame($original->decimals()->value(), $reconverted->decimals()->value());
        self::assertSame($original->displayName(), $reconverted->displayName());
        self::assertSame($original->isActive(), $reconverted->isActive());
        self::assertSame(
            $original->createdAt()->format(\DateTimeInterface::ATOM),
            $reconverted->createdAt()->format(\DateTimeInterface::ATOM),
        );
        self::assertSame(
            $original->updatedAt()->format(\DateTimeInterface::ATOM),
            $reconverted->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
