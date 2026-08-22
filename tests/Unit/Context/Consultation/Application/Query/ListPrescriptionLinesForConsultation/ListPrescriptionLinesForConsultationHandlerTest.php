<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation\ListPrescriptionLinesForConsultation;
use App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation\ListPrescriptionLinesForConsultationHandler;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use PHPUnit\Framework\TestCase;

final class ListPrescriptionLinesForConsultationHandlerTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-00000000f001';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-00000000f002';

    public function testItHandsTheMedicinesToTheCaller(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('prescriptionLinesForConsultation')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ConsultationId $id): bool => self::CONSULTATION_ID === $id->toString()),
            )
            ->willReturn([
                [
                    'sourceLineId' => '01960000-0000-7000-8000-00000000f003',
                    'articleId'    => '01960000-0000-7000-8000-00000000f004',
                    'code'         => 'MELOXIDYL-1',
                    'label'        => 'Meloxidyl 1 mg/mL',
                    'dose'         => '0,1 mg/kg',
                    'frequency'    => '1×/j',
                    'durationDays' => 7,
                    'route'        => 'per os',
                    'quantity'     => '1.00',
                ],
            ])
        ;

        $lines = (new ListPrescriptionLinesForConsultationHandler($repository))(
            new ListPrescriptionLinesForConsultation(self::CLINIC_ID, self::CONSULTATION_ID),
        );

        self::assertCount(1, $lines);
        self::assertSame('Meloxidyl 1 mg/mL', $lines[0]->label);
        self::assertSame('MELOXIDYL-1', $lines[0]->code);
        self::assertSame('01960000-0000-7000-8000-00000000f004', $lines[0]->articleId);
        self::assertSame('01960000-0000-7000-8000-00000000f003', $lines[0]->sourceLineId);
        self::assertSame('0,1 mg/kg', $lines[0]->dose);
        self::assertSame('1×/j', $lines[0]->frequency);
        self::assertSame(7, $lines[0]->durationDays);
        self::assertSame('per os', $lines[0]->route);
        self::assertSame('1.00', $lines[0]->quantity);
    }

    public function testAConsultationWithoutMedicinesYieldsNoLines(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::once())->method('prescriptionLinesForConsultation')->willReturn([]);

        self::assertSame([], (new ListPrescriptionLinesForConsultationHandler($repository))(
            new ListPrescriptionLinesForConsultation(self::CLINIC_ID, self::CONSULTATION_ID),
        ));
    }
}
