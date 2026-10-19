<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Billing\Infrastructure\Adapter\Admission\MessengerAdmissionCoordinator;
use App\Context\Billing\Infrastructure\Adapter\Consultation\QueryBusConsultationBillingProvider;
use App\Context\Billing\Infrastructure\Adapter\Taxation\QueryBusTaxResolver;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Consultation\Application\Query\ListBillableLinesForAdmission\BillableLine;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;
use App\System\Taxation\Application\Query\ResolveTax\ResolveTax;
use App\System\Taxation\Domain\ValueObject\AppliedTaxRate;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\LegalMentions;
use App\System\Taxation\Domain\ValueObject\TaxApplication;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class BillingAdaptersTest extends TestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-000000000801';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-000000000802';

    // ── Admission coordinator ──

    public function testClosingTheVisitReleasesItToItsOwner(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (CloseAdmission $command): bool {
                self::assertSame(self::ADMISSION_ID, $command->admissionId);
                self::assertSame(self::CLINIC_ID, $command->clinicId);
                self::assertSame('released_to_owner', $command->closureReason);

                return true;
            }))
        ;

        (new MessengerAdmissionCoordinator($commandBus))->closeAfterCheckout(self::ADMISSION_ID, self::CLINIC_ID);
    }

    public function testAVisitAlreadyClosedDoesNotBreakTheCheckout(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \DomainException('Admission is already closed'));

        (new MessengerAdmissionCoordinator($commandBus))->closeAfterCheckout(self::ADMISSION_ID, self::CLINIC_ID);

        $this->expectNotToPerformAssertions();
    }

    // ── Consultation billing provider ──

    public function testTheVisitLinesAreMappedAcrossTheBoundary(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects(self::once())->method('ask')->willReturn([
            new BillableLine('c1', 'l1', 'Consultation', 'CONS', '1', 5000, 'EUR', 'veterinary.act.consultation'),
            'not a line',
        ]);

        $lines = (new QueryBusConsultationBillingProvider($queryBus))
            ->billableLinesForAdmission(self::ADMISSION_ID, self::CLINIC_ID)
        ;

        // The stray value is dropped rather than crashing the checkout.
        self::assertCount(1, $lines);
        self::assertSame('Consultation', $lines[0]->label);
        self::assertSame('CONS', $lines[0]->code);
        self::assertSame(5000, $lines[0]->unitPriceMinorUnits);
        self::assertSame('c1', $lines[0]->consultationId);
        self::assertSame('l1', $lines[0]->sourceLineId);
        self::assertSame('veterinary.act.consultation', $lines[0]->taxCategoryCode);
    }

    public function testAnUnexpectedAnswerYieldsNoLines(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn('nothing useful');

        self::assertSame(
            [],
            (new QueryBusConsultationBillingProvider($queryBus))
                ->billableLinesForAdmission(self::ADMISSION_ID, self::CLINIC_ID),
        );
    }

    // ── Tax resolver ──

    public function testTheTaxationBcSplitsTheAmount(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            fn (object $query): object => $query instanceof GetClinic
                ? $this->clinic()
                : $this->taxApplication(5000, 1000),
        );

        $resolved = $this->resolver($queryBus)->resolve(5000, 'EUR', 'veterinary.act.consultation', self::CLINIC_ID);

        self::assertSame(5000, $resolved->netMinorUnits);
        self::assertSame(1000, $resolved->taxMinorUnits);
        self::assertSame(6000, $resolved->grossMinorUnits);
        self::assertSame('20.00', $resolved->ratePercent);
    }

    public function testTheClinicIsLookedUpOnlyOnce(): void
    {
        $queryBus    = $this->createMock(QueryBusInterface::class);
        $clinicCalls = 0;
        $queryBus->method('ask')->willReturnCallback(
            function (object $query) use (&$clinicCalls): object {
                if ($query instanceof GetClinic) {
                    ++$clinicCalls;

                    return $this->clinic();
                }

                return $this->taxApplication(1000, 200);
            },
        );

        $resolver = $this->resolver($queryBus);
        $resolver->resolve(1000, 'EUR', 'veterinary.act.consultation', self::CLINIC_ID);
        $resolver->resolve(1000, 'EUR', 'veterinary.act.consultation', self::CLINIC_ID);

        self::assertSame(1, $clinicCalls);
    }

    public function testALineWithoutATaxCategoryIsUntaxed(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects(self::never())->method('ask');

        $resolved = $this->resolver($queryBus)->resolve(4500, 'EUR', null, self::CLINIC_ID);

        self::assertSame(0, $resolved->taxMinorUnits);
        self::assertSame(4500, $resolved->grossMinorUnits);
    }

    public function testAnUnknownClinicLeavesTheLineUntaxed(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('no such clinic'));

        $resolved = $this->resolver($queryBus)->resolve(4500, 'EUR', 'veterinary.act.consultation', self::CLINIC_ID);

        self::assertSame(0, $resolved->taxMinorUnits);
    }

    public function testAFailingTaxRuleLeavesTheLineUntaxed(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): object {
                if ($query instanceof GetClinic) {
                    return $this->clinic();
                }

                throw new \RuntimeException('no rate for this category');
            },
        );

        $resolved = $this->resolver($queryBus)->resolve(4500, 'EUR', 'veterinary.act.unknown', self::CLINIC_ID);

        self::assertSame(0, $resolved->taxMinorUnits);
        self::assertSame('0', $resolved->ratePercent);
    }

    public function testAnUnexpectedTaxAnswerLeavesTheLineUntaxed(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            fn (object $query): mixed => $query instanceof GetClinic ? $this->clinic() : 'not a tax application',
        );

        $resolved = $this->resolver($queryBus)->resolve(4500, 'EUR', 'veterinary.act.consultation', self::CLINIC_ID);

        self::assertSame(0, $resolved->taxMinorUnits);
    }

    public function testAJurisdictionNarrowsTheRegime(): void
    {
        $captured = null;
        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query) use (&$captured): object {
                if ($query instanceof GetClinic) {
                    return $this->clinic('COR');
                }

                self::assertInstanceOf(ResolveTax::class, $query);
                $captured = $query->regimeId->toString();

                return $this->taxApplication(1000, 200);
            },
        );

        $this->resolver($queryBus)->resolve(1000, 'EUR', 'veterinary.act.consultation', self::CLINIC_ID);

        self::assertSame('FR-COR', $captured);
    }

    private function resolver(QueryBusInterface $queryBus): QueryBusTaxResolver
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22 10:00:00'));

        return new QueryBusTaxResolver($queryBus, $clock);
    }

    private function clinic(?string $jurisdiction = null): ClinicDto
    {
        return new ClinicDto(
            id: self::CLINIC_ID,
            name: 'Clinique de Paris',
            slug: 'clinic-paris',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            jurisdictionCode: $jurisdiction,
            currencyCode: 'EUR',
            status: ClinicStatus::ACTIVE,
            clinicGroupId: null,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        );
    }

    private function taxApplication(int $net, int $tax): TaxApplication
    {
        $eur = CurrencyCode::fromString('EUR');

        return TaxApplication::of(
            taxRegimeId: TaxRegimeId::fromString('FR'),
            taxCategoryCode: TaxCategoryCode::fromString('veterinary.act.consultation'),
            nature: TaxCategoryNature::SERVICES,
            appliedRate: AppliedTaxRate::of(
                TaxRateId::fromString('fr.standard'),
                TaxRegimeId::fromString('FR'),
                TaxRateValue::ofBasisPoints(2000),
                new \DateTimeImmutable('2014-01-01'),
                'regime',
            ),
            netAmount: Money::fromMinorUnits($net, $eur),
            taxAmount: Money::fromMinorUnits($tax, $eur),
            grossAmount: Money::fromMinorUnits($net + $tax, $eur),
            roundingPolicyId: RoundingPolicyId::COMMERCIAL,
            legalMentions: LegalMentions::empty(),
            fiscalContext: FiscalContext::minimal(
                CountryCode::fromString('FR'),
                new \DateTimeImmutable('2026-08-22 10:00:00'),
            ),
            resolvedAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
        );
    }
}
