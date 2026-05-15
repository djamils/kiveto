<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence;

use App\Fixtures\System\Taxation\Factory\TaxRegimeEntityFactory;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository\DoctrineTaxRegimeRepository;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineTaxRegimeRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveThenFindById(): void
    {
        $clock  = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regime = TaxRegime::create(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            $clock,
            false,
        );

        $rate = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );
        $regime->addRate($rate, $clock);

        $repo = $this->getRepository();
        $repo->save($regime);

        $found = $repo->findById(TaxRegimeId::fromString('FR'));

        self::assertNotNull($found);
        self::assertSame('FR', $found->id()->toString());
        self::assertSame('Régime TVA France', $found->name());
        self::assertSame('FR', $found->country()->toString());
        self::assertFalse($found->isActive());
        self::assertCount(1, $found->rates());
        self::assertSame('fr.standard', $found->rates()[0]->id()->toString());
        self::assertSame(2000, $found->rates()[0]->value()->basisPoints());
    }

    public function testSaveTwiceIsIdempotent(): void
    {
        $clock  = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regime = TaxRegime::create(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            $clock,
            true,
        );

        $rate = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );
        $regime->addRate($rate, $clock);

        $repo = $this->getRepository();
        $repo->save($regime);
        $repo->save($regime);

        $all = $repo->findAllActive();

        self::assertCount(1, $all);
        self::assertSame('FR', $all[0]->id()->toString());
    }

    public function testFindAllActiveReturnsOnlyActiveRegimes(): void
    {
        $now = new \DateTimeImmutable();

        TaxRegimeEntityFactory::createOne([
            'id'          => 'FR',
            'name'        => 'France',
            'countryCode' => 'FR',
            'active'      => true,
            'loadedAt'    => $now,
            'updatedAt'   => $now,
        ]);
        TaxRegimeEntityFactory::createOne([
            'id'          => 'DE',
            'name'        => 'Allemagne',
            'countryCode' => 'DE',
            'active'      => false,
            'loadedAt'    => $now,
            'updatedAt'   => $now,
        ]);

        $repo   = $this->getRepository();
        $active = $repo->findAllActive();

        self::assertCount(1, $active);
        self::assertSame('FR', $active[0]->id()->toString());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo  = $this->getRepository();
        $found = $repo->findById(TaxRegimeId::fromString('FR'));

        self::assertNull($found);
    }

    public function testSaveReplacesRatesOnUpdate(): void
    {
        $clock  = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regime = TaxRegime::create(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            $clock,
            false,
        );

        $rateA = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );
        $regime->addRate($rateA, $clock);

        $repo = $this->getRepository();
        $repo->save($regime);

        // Now replace with two rates
        $rateB = TaxRate::of(
            TaxRateId::fromString('fr.reduced'),
            TaxRateValue::ofBasisPoints(1000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of(['veterinary.act.hospitalization'], [], [], [], null),
        );
        $regime->replaceAllRates([$rateA, $rateB], $clock);
        $repo->save($regime);

        $found = $repo->findById(TaxRegimeId::fromString('FR'));

        self::assertNotNull($found);
        self::assertCount(2, $found->rates());
    }

    private function getRepository(): DoctrineTaxRegimeRepository
    {
        $repo = static::getContainer()->get(DoctrineTaxRegimeRepository::class);
        \assert($repo instanceof DoctrineTaxRegimeRepository);

        return $repo;
    }
}
