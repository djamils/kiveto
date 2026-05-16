<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence\Mapper;

use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxRateMapper;
use PHPUnit\Framework\TestCase;

final class TaxRateMapperTest extends TestCase
{
    private TaxRateMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TaxRateMapper();
    }

    public function testRoundTripSymmetry(): void
    {
        $rate = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );

        $entity        = $this->mapper->toEntity($rate, 'FR');
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame('fr.standard', $reconstituted->id()->toString());
        self::assertSame(2000, $reconstituted->value()->basisPoints());
        self::assertSame('2014-01-01', $reconstituted->validityPeriod()->validFrom()->format('Y-m-d'));
        self::assertNull($reconstituted->validityPeriod()->validTo());
        self::assertSame([], $reconstituted->appliesTo()->categoriesMatching());
        self::assertSame([], $reconstituted->appliesTo()->regionsMatching());
        self::assertSame([], $reconstituted->appliesTo()->customerStatusesMatching());
        self::assertSame([], $reconstituted->appliesTo()->animalUsagesMatching());
        self::assertNull($reconstituted->appliesTo()->clinicLiability());
    }

    public function testRoundTripWithClosedPeriod(): void
    {
        $rate = TaxRate::of(
            TaxRateId::fromString('fr.old.rate'),
            TaxRateValue::ofBasisPoints(1960),
            ValidityPeriod::between(
                new \DateTimeImmutable('2000-01-01'),
                new \DateTimeImmutable('2013-12-31'),
            ),
            TaxRateCondition::of([], [], [], [], null),
        );

        $entity        = $this->mapper->toEntity($rate, 'FR');
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(1960, $reconstituted->value()->basisPoints());
        self::assertSame('2000-01-01', $reconstituted->validityPeriod()->validFrom()->format('Y-m-d'));
        self::assertNotNull($reconstituted->validityPeriod()->validTo());
        self::assertSame('2013-12-31', $reconstituted->validityPeriod()->validTo()->format('Y-m-d'));
    }

    public function testRoundTripWithAllConditionDimensions(): void
    {
        $rate = TaxRate::of(
            TaxRateId::fromString('fr.complex.rate'),
            TaxRateValue::ofBasisPoints(550),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of(
                ['veterinary.drug.companion', 'veterinary.drug.livestock'],
                [RegionCode::fromString('IDF'), RegionCode::fromString('ARA')],
                [CustomerTaxStatus::B2C, CustomerTaxStatus::B2B],
                [AnimalUsage::COMPANION],
                ClinicLiabilityStatus::LIABLE,
            ),
        );

        $entity        = $this->mapper->toEntity($rate, 'FR');
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(
            ['veterinary.drug.companion', 'veterinary.drug.livestock'],
            $reconstituted->appliesTo()->categoriesMatching(),
        );

        $regionStrings = array_map(
            static fn (RegionCode $r) => $r->toString(),
            $reconstituted->appliesTo()->regionsMatching(),
        );
        self::assertSame(['IDF', 'ARA'], $regionStrings);

        $statusValues = array_map(
            static fn (CustomerTaxStatus $s) => $s->value,
            $reconstituted->appliesTo()->customerStatusesMatching(),
        );
        self::assertSame(['b2c', 'b2b'], $statusValues);

        $usageValues = array_map(
            static fn (AnimalUsage $u) => $u->value,
            $reconstituted->appliesTo()->animalUsagesMatching(),
        );
        self::assertSame(['companion'], $usageValues);

        self::assertSame(ClinicLiabilityStatus::LIABLE, $reconstituted->appliesTo()->clinicLiability());
    }

    public function testRoundTripWithWildcardCondition(): void
    {
        $rate = TaxRate::of(
            TaxRateId::fromString('fr.wildcard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of(
                ['veterinary.act.*'],
                [],
                [],
                [],
                null,
            ),
        );

        $entity        = $this->mapper->toEntity($rate, 'FR');
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(['veterinary.act.*'], $reconstituted->appliesTo()->categoriesMatching());
    }

    public function testRegimeIdIsStoredOnEntity(): void
    {
        $rate = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );

        $entity = $this->mapper->toEntity($rate, 'FR');

        self::assertSame('FR', $entity->getRegimeId());
        self::assertSame('fr.standard', $entity->getId());
        self::assertSame(2000, $entity->getValueBp());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
    }
}
