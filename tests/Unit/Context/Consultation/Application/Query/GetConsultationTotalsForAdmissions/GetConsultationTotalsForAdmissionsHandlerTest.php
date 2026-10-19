<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions\GetConsultationTotalsForAdmissions;
use App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions\GetConsultationTotalsForAdmissionsHandler;
use PHPUnit\Framework\TestCase;

final class GetConsultationTotalsForAdmissionsHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000a001';
    private const string VISIT_1   = '01960000-0000-7000-8000-00000000a002';
    private const string VISIT_2   = '01960000-0000-7000-8000-00000000a003';

    public function testNoVisitAsksNothing(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::never())->method('totalsForAdmissions');

        $handler = new GetConsultationTotalsForAdmissionsHandler($repository, $this->createStub(TaxRateProviderInterface::class));

        self::assertSame([], $handler(new GetConsultationTotalsForAdmissions(self::CLINIC_ID, [])));
    }

    public function testEachVisitShowsItsPreTaxVatAndTtcWhateverTheModeOfItsPrices(): void
    {
        $repository = $this->createStub(ConsultationReadRepositoryInterface::class);
        $repository->method('totalsForAdmissions')->willReturn([
            self::VISIT_1 => [
                'htByTaxCategory'  => ['veterinary.act.consultation' => 1000],
                'ttcByTaxCategory' => ['veterinary.act.consultation' => 6000],
                'currency'         => 'EUR',
            ],
            self::VISIT_2 => ['htByTaxCategory' => [], 'ttcByTaxCategory' => ['' => 500], 'currency' => ''],
        ]);

        $calls    = 0;
        $taxRates = $this->createStub(TaxRateProviderInterface::class);
        $taxRates->method('effectiveRatePercent')->willReturnCallback(static function () use (&$calls): float {
            ++$calls;

            return 20.0;
        });

        $totals = (new GetConsultationTotalsForAdmissionsHandler($repository, $taxRates))(
            new GetConsultationTotalsForAdmissions(self::CLINIC_ID, [self::VISIT_1, self::VISIT_2]),
        );

        // 10,00 € HT + 2,00 € VAT, and 60,00 € TTC holding 50,00 € + 10,00 € of VAT.
        self::assertSame(6000, $totals[self::VISIT_1]->totalHtMinorUnits);
        self::assertSame(1200, $totals[self::VISIT_1]->totalTvaMinorUnits);
        self::assertSame(7200, $totals[self::VISIT_1]->totalTtcMinorUnits);
        self::assertSame('EUR', $totals[self::VISIT_1]->currency);
        // The rate of a category is asked once for the whole page.
        self::assertSame(1, $calls);

        // An untaxed line shows as is, and a visit without currency falls back to the euro.
        self::assertSame(500, $totals[self::VISIT_2]->totalTtcMinorUnits);
        self::assertSame(0, $totals[self::VISIT_2]->totalTvaMinorUnits);
        self::assertSame('EUR', $totals[self::VISIT_2]->currency);
    }
}
