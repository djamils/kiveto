<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListBillableLinesForAdmission;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\ListBillableLinesForAdmission\ListBillableLinesForAdmission;
use App\Context\Consultation\Application\Query\ListBillableLinesForAdmission\ListBillableLinesForAdmissionHandler;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListBillableLinesForAdmissionHandlerTest extends TestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000f001';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-00000000f002';

    public function testItHandsTheVisitLinesToTheCaller(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('billingLinesForAdmission')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AdmissionId $id): bool => self::ADMISSION_ID === $id->toString()),
            )
            ->willReturn([
                [
                    'consultationId'      => '01960000-0000-7000-8000-00000000f003',
                    'sourceLineId'        => '01960000-0000-7000-8000-00000000f004',
                    'label'               => 'Consultation standard',
                    'code'                => 'CONS_STD',
                    'quantity'            => '1.00',
                    'unitPriceMinorUnits' => 5000,
                    'currency'            => 'EUR',
                    'unitPriceMode'       => 'incl',
                    'taxCategoryCode'     => 'veterinary.act.consultation',
                ],
            ])
        ;

        $lines = (new ListBillableLinesForAdmissionHandler($repository))(
            new ListBillableLinesForAdmission(self::CLINIC_ID, self::ADMISSION_ID),
        );

        self::assertCount(1, $lines);
        self::assertSame('Consultation standard', $lines[0]->label);
        self::assertSame('CONS_STD', $lines[0]->code);
        self::assertSame('1.00', $lines[0]->quantity);
        self::assertSame(5000, $lines[0]->unitPriceMinorUnits);
        self::assertSame('EUR', $lines[0]->currency);
        self::assertSame('incl', $lines[0]->unitPriceMode);
        self::assertSame('veterinary.act.consultation', $lines[0]->taxCategoryCode);
        self::assertSame('01960000-0000-7000-8000-00000000f003', $lines[0]->consultationId);
        self::assertSame('01960000-0000-7000-8000-00000000f004', $lines[0]->sourceLineId);
    }

    public function testAVisitWithNothingToChargeYieldsNoLines(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::once())->method('billingLinesForAdmission')->willReturn([]);

        $lines = (new ListBillableLinesForAdmissionHandler($repository))(
            new ListBillableLinesForAdmission(self::CLINIC_ID, self::ADMISSION_ID),
        );

        self::assertSame([], $lines);
    }
}
