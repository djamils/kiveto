<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\GetConsultationDetails;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetailsHandler;
use PHPUnit\Framework\TestCase;

final class GetConsultationDetailsHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';

    public function testTotalsAreZeroAndCurrencyDefaultsWhenThereIsNoBillingLine(): void
    {
        $taxRates = $this->createMock(TaxRateProviderInterface::class);
        $taxRates->expects(self::never())->method('effectiveRatePercent');

        $result = $this->handle($this->detailsWithBillingLines([]), $taxRates);

        self::assertSame([
            'totalHtMinorUnits'  => 0,
            'totalTvaMinorUnits' => 0,
            'totalTtcMinorUnits' => 0,
            'currency'           => 'EUR',
        ], $result->totals);
    }

    public function testTotalsSumLinesAndApplyTheCategoryRate(): void
    {
        $taxRates = $this->createMock(TaxRateProviderInterface::class);
        $taxRates
            ->expects(self::once())
            ->method('effectiveRatePercent')
            ->with('veterinary.act.consultation', self::CLINIC_ID)
            ->willReturn(20.0)
        ;

        $details = $this->detailsWithBillingLines([
            $this->billingLine(id: 'l1', total: 3500, taxCategoryCode: 'veterinary.act.consultation'),
            // 1233 * 20% = 246.6, rounded to 247.
            $this->billingLine(id: 'l2', total: 1233, taxCategoryCode: 'veterinary.act.consultation'),
        ]);

        $result = $this->handle($details, $taxRates);

        self::assertSame([
            'totalHtMinorUnits'  => 4733,
            'totalTvaMinorUnits' => 947,
            'totalTtcMinorUnits' => 5680,
            'currency'           => 'CHF',
        ], $result->totals);
    }

    public function testEachTaxCategoryIsAskedForOnlyOnce(): void
    {
        $calls = [];

        $taxRates = $this->createStub(TaxRateProviderInterface::class);
        $taxRates
            ->method('effectiveRatePercent')
            ->willReturnCallback(static function (string $categoryCode, string $clinicId) use (&$calls): float {
                $calls[] = [$categoryCode, $clinicId];

                return 'veterinary.act.consultation' === $categoryCode ? 20.0 : 10.0;
            })
        ;

        $details = $this->detailsWithBillingLines([
            $this->billingLine(id: 'l1', total: 1000, taxCategoryCode: 'veterinary.act.consultation'),
            $this->billingLine(id: 'l2', total: 2000, taxCategoryCode: 'veterinary.act.consultation'),
            $this->billingLine(id: 'l3', total: 500, taxCategoryCode: 'veterinary.medicine.oral'),
        ]);

        $result = $this->handle($details, $taxRates);

        self::assertSame([
            ['veterinary.act.consultation', self::CLINIC_ID],
            ['veterinary.medicine.oral', self::CLINIC_ID],
        ], $calls);

        self::assertSame(3500, $result->totals['totalHtMinorUnits']);
        self::assertSame(650, $result->totals['totalTvaMinorUnits']);
        self::assertSame(4150, $result->totals['totalTtcMinorUnits']);
    }

    public function testALineWithoutTaxCategoryCountsInHtOnly(): void
    {
        $taxRates = $this->createMock(TaxRateProviderInterface::class);
        $taxRates->expects(self::never())->method('effectiveRatePercent');

        $details = $this->detailsWithBillingLines([
            $this->billingLine(id: 'l1', total: 4200, taxCategoryCode: null),
        ]);

        $result = $this->handle($details, $taxRates);

        self::assertSame([
            'totalHtMinorUnits'  => 4200,
            'totalTvaMinorUnits' => 0,
            'totalTtcMinorUnits' => 4200,
            'currency'           => 'CHF',
        ], $result->totals);
    }

    public function testACategoryWithoutRateIsUntaxedAndResolvedOnlyOnce(): void
    {
        $calls = 0;

        $taxRates = $this->createStub(TaxRateProviderInterface::class);
        $taxRates
            ->method('effectiveRatePercent')
            ->willReturnCallback(static function () use (&$calls): ?float {
                ++$calls;

                return null;
            })
        ;

        $details = $this->detailsWithBillingLines([
            $this->billingLine(id: 'l1', total: 1000, taxCategoryCode: 'veterinary.act.consultation'),
            $this->billingLine(id: 'l2', total: 2500, taxCategoryCode: 'veterinary.act.consultation'),
        ]);

        $result = $this->handle($details, $taxRates);

        self::assertSame(1, $calls);
        self::assertSame([
            'totalHtMinorUnits'  => 3500,
            'totalTvaMinorUnits' => 0,
            'totalTtcMinorUnits' => 3500,
            'currency'           => 'CHF',
        ], $result->totals);
    }

    private function handle(
        ConsultationDetailsDTO $details,
        TaxRateProviderInterface $taxRates,
    ): ConsultationDetailsDTO {
        $repository = $this->createStub(ConsultationReadRepositoryInterface::class);
        $repository->method('findById')->willReturn($details);

        $handler = new GetConsultationDetailsHandler($repository, $taxRates);

        return $handler(new GetConsultationDetails(self::CONSULTATION_ID, self::CLINIC_ID));
    }

    /**
     * @return array{
     *     id: string,
     *     sourceLineId: string,
     *     source: string,
     *     label: string,
     *     code: ?string,
     *     quantity: float,
     *     unitPriceMinorUnits: int,
     *     currency: string,
     *     taxCategoryCode: ?string,
     *     totalMinorUnits: int
     * }
     */
    private function billingLine(string $id, int $total, ?string $taxCategoryCode): array
    {
        return [
            'id'                  => $id,
            'sourceLineId'        => $id . '-source',
            'source'              => 'PLAN',
            'label'               => 'Consultation',
            'code'                => 'ACT-CONS',
            'quantity'            => 1.0,
            'unitPriceMinorUnits' => $total,
            // A non-EUR currency proves the totals take it from the lines.
            'currency'        => 'CHF',
            'taxCategoryCode' => $taxCategoryCode,
            'totalMinorUnits' => $total,
        ];
    }

    /**
     * @param list<array{
     *     id: string,
     *     sourceLineId: string,
     *     source: string,
     *     label: string,
     *     code: ?string,
     *     quantity: float,
     *     unitPriceMinorUnits: int,
     *     currency: string,
     *     taxCategoryCode: ?string,
     *     totalMinorUnits: int
     * }> $billingLines
     */
    private function detailsWithBillingLines(array $billingLines): ConsultationDetailsDTO
    {
        return new ConsultationDetailsDTO(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            practitionerUserId: '55555555-5555-4555-8555-555555555555',
            status: 'OPEN',
            appointmentId: null,
            admissionId: '33333333-3333-4333-8333-333333333333',
            patientId: '66666666-6666-4666-8666-666666666666',
            chiefComplaint: null,
            vitals: null,
            notes: [],
            acts: [],
            summary: null,
            startedAtUtc: '2026-04-10 09:00:00',
            closedAtUtc: null,
            subjectiveText: null,
            objectiveObservations: null,
            teamMemo: null,
            motifs: [],
            typedVitals: [],
            examSystems: [],
            diagnoses: [],
            planActions: [],
            prescriptionLines: [],
            billingLines: $billingLines,
            totals: [
                'totalHtMinorUnits'  => 0,
                'totalTvaMinorUnits' => 0,
                'totalTtcMinorUnits' => 0,
                'currency'           => 'EUR',
            ],
        );
    }
}
