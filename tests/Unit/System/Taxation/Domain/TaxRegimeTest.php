<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Event\TaxRateAdded;
use App\System\Taxation\Domain\Event\TaxRegimeActivated;
use App\System\Taxation\Domain\Event\TaxRegimeDeactivated;
use App\System\Taxation\Domain\Event\TaxRegimeLoaded;
use App\System\Taxation\Domain\Exception\EmptyTaxRegimeCannotBeActivatedException;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use PHPUnit\Framework\TestCase;

final class TaxRegimeTest extends TestCase
{
    private \DateTimeImmutable $now;
    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->now   = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now);
    }

    public function testCreateRecordsTaxRegimeLoadedEvent(): void
    {
        $regime = $this->makeRegime();

        $events = $regime->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRegimeLoaded::class, $events[0]);
    }

    public function testReconstituteRestoresStateWithoutEvents(): void
    {
        $regime = TaxRegime::reconstitute(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            [],
            true,
            $this->now,
            $this->now,
        );

        self::assertSame([], $regime->pullDomainEvents());
        self::assertTrue($regime->isActive());
    }

    public function testAddRateRecordsTaxRateAddedEvent(): void
    {
        $regime = $this->makeRegime();
        $_      = $regime->pullDomainEvents(); // clear create event

        $rate = $this->makeRate('fr.standard', 2000);
        $regime->addRate($rate, $this->clock);

        $events = $regime->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRateAdded::class, $events[0]);
    }

    public function testReplaceAllRatesClearsAndSetsRates(): void
    {
        $regime = $this->makeRegime();
        $_      = $regime->pullDomainEvents(); // clear create event

        $regime->addRate($this->makeRate('fr.standard', 2000), $this->clock);
        $regime->addRate($this->makeRate('fr.reduced', 1000), $this->clock);
        $_ = $regime->pullDomainEvents(); // clear addRate events

        $newRate = $this->makeRate('fr.particular', 210);
        $regime->replaceAllRates([$newRate], $this->clock);

        self::assertCount(1, $regime->rates());
        self::assertSame('fr.particular', $regime->rates()[0]->id()->toString());
        // replaceAllRates does not record events
        self::assertSame([], $regime->pullDomainEvents());
    }

    public function testActivateOnEmptyRegimeThrowsException(): void
    {
        $regime = $this->makeRegime();

        $this->expectException(EmptyTaxRegimeCannotBeActivatedException::class);

        $regime->activate($this->clock);
    }

    public function testActivateOnNonEmptyRegimeRecordsTaxRegimeActivated(): void
    {
        $regime = $this->makeRegime();
        $regime->addRate($this->makeRate('fr.standard', 2000), $this->clock);
        $_ = $regime->pullDomainEvents(); // clear previous events

        $regime->activate($this->clock);

        $events = $regime->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRegimeActivated::class, $events[0]);
        self::assertTrue($regime->isActive());
    }

    public function testDeactivateRecordsTaxRegimeDeactivated(): void
    {
        $regime = $this->makeRegime(active: true);
        $regime->addRate($this->makeRate('fr.standard', 2000), $this->clock);
        $_ = $regime->pullDomainEvents(); // clear previous events

        $regime->deactivate($this->clock);

        $events = $regime->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRegimeDeactivated::class, $events[0]);
        self::assertFalse($regime->isActive());
    }

    public function testFindCandidateRatesReturnsMatchingRates(): void
    {
        $date    = new \DateTimeImmutable('2024-06-01');
        $context = FiscalContext::minimal(CountryCode::fromString('FR'), $date);
        $cat     = TaxCategoryCode::fromString('veterinary.act.consultation');

        // matching: empty condition matches everything
        $rate1 = $this->makeRate('fr.standard', 2000);
        $rate2 = $this->makeRate('fr.reduced', 1000);
        // non-matching: condition restricts to a different category
        $rate3 = $this->makeRate(
            'fr.particular',
            210,
            condition: TaxRateCondition::of(['veterinary.drug.livestock'], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            TaxRegimeId::fromString('FR'),
            'Test',
            CountryCode::fromString('FR'),
            [$rate1, $rate2, $rate3],
            true,
            $this->now,
            $this->now,
        );

        $candidates = $regime->findCandidateRates($cat, $context);

        self::assertCount(2, $candidates);
    }

    public function testFindCandidateRatesExcludesOutsideValidityDate(): void
    {
        // Rate only valid in 2018
        $date    = new \DateTimeImmutable('2024-06-01');
        $context = FiscalContext::minimal(CountryCode::fromString('FR'), $date);
        $cat     = TaxCategoryCode::fromString('veterinary.act.consultation');

        $expiredRate = $this->makeRate('fr.old', 2000, '2014-01-01', '2018-12-31');

        $regime = TaxRegime::reconstitute(
            TaxRegimeId::fromString('FR'),
            'Test',
            CountryCode::fromString('FR'),
            [$expiredRate],
            true,
            $this->now,
            $this->now,
        );

        $candidates = $regime->findCandidateRates($cat, $context);

        self::assertCount(0, $candidates);
    }

    public function testFindCandidateRatesExcludesConditionMismatch(): void
    {
        $date    = new \DateTimeImmutable('2024-06-01');
        $context = FiscalContext::minimal(CountryCode::fromString('FR'), $date);
        $cat     = TaxCategoryCode::fromString('veterinary.act.consultation');

        $mismatchedRate = $this->makeRate(
            'fr.drug',
            550,
            condition: TaxRateCondition::of(['veterinary.drug.*'], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            TaxRegimeId::fromString('FR'),
            'Test',
            CountryCode::fromString('FR'),
            [$mismatchedRate],
            true,
            $this->now,
            $this->now,
        );

        $candidates = $regime->findCandidateRates($cat, $context);

        self::assertCount(0, $candidates);
    }

    public function testActivateWhenAlreadyActiveIsIdempotent(): void
    {
        $regime = $this->makeRegime(active: true);
        $regime->addRate($this->makeRate('fr.standard', 2000), $this->clock);
        $_ = $regime->pullDomainEvents(); // clear events

        // activate an already-active regime: must be a no-op (no event recorded)
        $regime->activate($this->clock);

        self::assertSame([], $regime->pullDomainEvents());
        self::assertTrue($regime->isActive());
    }

    public function testDeactivateWhenAlreadyInactiveIsIdempotent(): void
    {
        $regime = $this->makeRegime(active: false);
        $_      = $regime->pullDomainEvents(); // clear events

        // deactivate an already-inactive regime: must be a no-op (no event recorded)
        $regime->deactivate($this->clock);

        self::assertSame([], $regime->pullDomainEvents());
        self::assertFalse($regime->isActive());
    }

    public function testGettersReturnCorrectValues(): void
    {
        $id      = TaxRegimeId::fromString('FR');
        $country = CountryCode::fromString('FR');
        $regime  = TaxRegime::create($id, 'Régime TVA France', $country, $this->clock);

        self::assertSame('FR', $regime->id()->toString());
        self::assertSame('Régime TVA France', $regime->name());
        self::assertTrue($regime->country()->equals($country));
        self::assertSame([], $regime->rates());
        self::assertSame($this->now, $regime->loadedAt());
        self::assertSame($this->now, $regime->updatedAt());
    }

    private function makeRate(
        string $id,
        int $basisPoints,
        string $validFrom = '2020-01-01',
        ?string $validTo = null,
        ?TaxRateCondition $condition = null,
    ): TaxRate {
        $validity = null === $validTo
            ? ValidityPeriod::openEndedFrom(new \DateTimeImmutable($validFrom))
            : ValidityPeriod::between(new \DateTimeImmutable($validFrom), new \DateTimeImmutable($validTo));

        return TaxRate::of(
            TaxRateId::fromString($id),
            TaxRateValue::ofBasisPoints($basisPoints),
            $validity,
            $condition ?? TaxRateCondition::of([], [], [], [], null),
        );
    }

    private function makeRegime(bool $active = false): TaxRegime
    {
        return TaxRegime::create(
            TaxRegimeId::fromString('FR'),
            'Régime TVA France',
            CountryCode::fromString('FR'),
            $this->clock,
            $active,
        );
    }
}
