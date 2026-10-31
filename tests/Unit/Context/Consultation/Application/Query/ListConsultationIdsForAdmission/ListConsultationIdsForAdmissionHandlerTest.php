<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListConsultationIdsForAdmission;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\ListConsultationIdsForAdmission\ListConsultationIdsForAdmission;
use App\Context\Consultation\Application\Query\ListConsultationIdsForAdmission\ListConsultationIdsForAdmissionHandler;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ListConsultationIdsForAdmissionHandlerTest extends TestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000f101';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-00000000f102';
    private const string FIRST        = '01960000-0000-7000-8000-00000000f103';
    private const string SECOND       = '01960000-0000-7000-8000-00000000f104';

    public function testItHandsTheConsultationsOfTheVisitToTheCaller(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('consultationIdsForAdmission')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AdmissionId $id): bool => self::ADMISSION_ID === $id->toString()),
            )
            ->willReturn([self::FIRST, self::SECOND])
        ;

        self::assertSame(
            [self::FIRST, self::SECOND],
            new ListConsultationIdsForAdmissionHandler($repository)(
                new ListConsultationIdsForAdmission(self::CLINIC_ID, self::ADMISSION_ID),
            ),
        );
    }

    #[TestWith(['not-a-uuid', self::ADMISSION_ID])]
    #[TestWith([self::CLINIC_ID, 'not-a-uuid'])]
    #[TestWith(['', ''])]
    public function testAMalformedIdAnswersNothingWithoutReading(string $clinicId, string $admissionId): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::never())->method('consultationIdsForAdmission');

        self::assertSame(
            [],
            new ListConsultationIdsForAdmissionHandler($repository)(
                new ListConsultationIdsForAdmission($clinicId, $admissionId),
            ),
        );
    }
}
