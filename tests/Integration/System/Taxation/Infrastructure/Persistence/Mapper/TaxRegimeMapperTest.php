<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence\Mapper;

use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxRateMapper;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxRegimeMapper;
use PHPUnit\Framework\TestCase;

final class TaxRegimeMapperTest extends TestCase
{
    private TaxRegimeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TaxRegimeMapper(new TaxRateMapper());
    }

    public function testRoundTrip(): void
    {
        $now   = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $rateA = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );
        $rateB = TaxRate::of(
            TaxRateId::fromString('fr.reduced'),
            TaxRateValue::ofBasisPoints(1000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of(['veterinary.act.hospitalization'], [], [], [], null),
        );
        $regime = TaxRegime::reconstitute(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            [$rateA, $rateB],
            true,
            $now,
            $now,
        );

        $rateMapper   = new TaxRateMapper();
        $entity       = $this->mapper->toEntity($regime);
        $rateEntities = array_map(
            static fn (TaxRate $r) => $rateMapper->toEntity($r, 'FR'),
            $regime->rates(),
        );
        $reconstituted = $this->mapper->toDomain($entity, $rateEntities);

        self::assertSame('FR', $reconstituted->id()->toString());
        self::assertSame('Régime TVA France', $reconstituted->name());
        self::assertSame('FR', $reconstituted->country()->toString());
        self::assertTrue($reconstituted->isActive());
        self::assertCount(2, $reconstituted->rates());
        self::assertSame('fr.standard', $reconstituted->rates()[0]->id()->toString());
        self::assertSame('fr.reduced', $reconstituted->rates()[1]->id()->toString());
    }

    public function testRoundTripWithNoRates(): void
    {
        $now    = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $regime = TaxRegime::reconstitute(
            TaxRegimeId::fromString('DE'),
            'Régime TVA Allemagne',
            CountryCode::fromString('DE'),
            [],
            false,
            $now,
            $now,
        );

        $entity        = $this->mapper->toEntity($regime);
        $reconstituted = $this->mapper->toDomain($entity, []);

        self::assertSame('DE', $reconstituted->id()->toString());
        self::assertSame('Régime TVA Allemagne', $reconstituted->name());
        self::assertFalse($reconstituted->isActive());
        self::assertCount(0, $reconstituted->rates());
    }

    public function testToEntityPreservesTimestamps(): void
    {
        $loadedAt  = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-05-15T10:00:00+00:00');
        $regime    = TaxRegime::reconstitute(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            [],
            true,
            $loadedAt,
            $updatedAt,
        );

        $entity = $this->mapper->toEntity($regime);

        self::assertSame($loadedAt->getTimestamp(), $entity->getLoadedAt()->getTimestamp());
        self::assertSame($updatedAt->getTimestamp(), $entity->getUpdatedAt()->getTimestamp());
    }
}
