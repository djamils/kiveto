<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Taxation;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Consultation\Infrastructure\Adapter\Taxation\QueryBusTaxRateProvider;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\EffectiveRateResult;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\GetEffectiveRateForUI;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBusTaxRateProviderTest extends TestCase
{
    private const string CLINIC_ID     = '22222222-2222-4222-8222-222222222222';
    private const string CATEGORY_CODE = 'veterinary.act.consultation';

    public function testReturnsTheEffectiveRateAsAFloat(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): ClinicDto|EffectiveRateResult {
                if ($query instanceof GetClinic) {
                    self::assertSame(self::CLINIC_ID, $query->clinicId);

                    return $this->clinic();
                }

                self::assertInstanceOf(GetEffectiveRateForUI::class, $query);
                self::assertSame(self::CATEGORY_CODE, $query->categoryCode->toString());
                self::assertSame('FR', $query->fiscalContext->country()->toString());
                self::assertSame(
                    '2026-04-10 09:00:00',
                    $query->fiscalContext->saleDate()->format('Y-m-d H:i:s'),
                );

                return new EffectiveRateResult('20.00', 'FR');
            },
        );

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());

        self::assertSame(20.0, $provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID));
    }

    public function testTheClinicIsLoadedOnlyOncePerClinicId(): void
    {
        $clinicLookups = 0;

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query) use (&$clinicLookups): ClinicDto|EffectiveRateResult {
                if ($query instanceof GetClinic) {
                    ++$clinicLookups;

                    return $this->clinic();
                }

                return new EffectiveRateResult('10.00', 'FR');
            },
        );

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());

        self::assertSame(10.0, $provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID));
        self::assertSame(10.0, $provider->effectiveRatePercent('veterinary.medicine.oral', self::CLINIC_ID));
        self::assertSame(1, $clinicLookups);
    }

    public function testReturnsNullWhenTheClinicLookupThrows(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('clinic unavailable'));

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());

        self::assertNull($provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID));
    }

    public function testReturnsNullWhenTheClinicLookupAnswersWithAnotherType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn('not a clinic');

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());

        self::assertNull($provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID));
    }

    public function testReturnsNullWhenTheRateQueryThrows(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): ClinicDto {
                if ($query instanceof GetClinic) {
                    return $this->clinic();
                }

                throw new \RuntimeException('no rate');
            },
        );

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());

        self::assertNull($provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID));
    }

    public function testReturnsNullWhenTheRateQueryAnswersWithAnotherType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            fn (object $query): ClinicDto|string => $query instanceof GetClinic ? $this->clinic() : 'no rate',
        );

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());

        self::assertNull($provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID));
    }

    #[DataProvider('provideTheRegimeIdCombinesCountryAndJurisdictionCases')]
    public function testTheRegimeIdCombinesCountryAndJurisdiction(
        string $countryCode,
        ?string $jurisdictionCode,
        string $expectedRegimeId,
    ): void {
        $regimeId = null;

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query) use (&$regimeId, $countryCode, $jurisdictionCode): ClinicDto|EffectiveRateResult {
                if ($query instanceof GetClinic) {
                    return $this->clinic($countryCode, $jurisdictionCode);
                }

                self::assertInstanceOf(GetEffectiveRateForUI::class, $query);
                $regimeId = $query->regimeId->toString();

                return new EffectiveRateResult('20.00', $regimeId);
            },
        );

        $provider = new QueryBusTaxRateProvider($queryBus, $this->clock());
        $provider->effectiveRatePercent(self::CATEGORY_CODE, self::CLINIC_ID);

        self::assertSame($expectedRegimeId, $regimeId);
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null, 2: string}>
     */
    public static function provideTheRegimeIdCombinesCountryAndJurisdictionCases(): iterable
    {
        yield 'no jurisdiction' => ['FR', null, 'FR'];
        yield 'empty jurisdiction' => ['FR', '', 'FR'];
        yield 'with jurisdiction' => ['FR', 'COR', 'FR-COR'];
        yield 'lowercase jurisdiction' => ['FR', 'cor', 'FR-COR'];
    }

    private function clock(): ClockInterface
    {
        return new FrozenClock(new \DateTimeImmutable('2026-04-10 09:00:00'));
    }

    private function clinic(string $countryCode = 'FR', ?string $jurisdictionCode = null): ClinicDto
    {
        return new ClinicDto(
            id: self::CLINIC_ID,
            name: 'Clinique du Parc',
            slug: 'clinique-du-parc',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: $countryCode,
            jurisdictionCode: $jurisdictionCode,
            currencyCode: 'EUR',
            status: ClinicStatus::ACTIVE,
            clinicGroupId: null,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00',
        );
    }
}
