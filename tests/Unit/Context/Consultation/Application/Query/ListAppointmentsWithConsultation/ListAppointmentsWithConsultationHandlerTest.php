<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\ListAppointmentsWithConsultation;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\ListAppointmentsWithConsultation\ListAppointmentsWithConsultation;
use App\Context\Consultation\Application\Query\ListAppointmentsWithConsultation\ListAppointmentsWithConsultationHandler;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ListAppointmentsWithConsultationHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000f101';
    private const string FIRST     = '01960000-0000-7000-8000-00000000f103';
    private const string SECOND    = '01960000-0000-7000-8000-00000000f104';

    public function testTheValidIdsAreAskedToTheReadModel(): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('appointmentIdsWithConsultation')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()), [self::FIRST, self::SECOND])
            ->willReturn([self::SECOND])
        ;

        self::assertSame(
            [self::SECOND],
            new ListAppointmentsWithConsultationHandler($repository)(new ListAppointmentsWithConsultation(self::CLINIC_ID, [self::FIRST, 'not-a-uuid', self::SECOND])),
        );
    }

    /**
     * @param list<string> $appointmentIds
     */
    #[TestWith(['not-a-uuid', [self::FIRST]])]
    #[TestWith([self::CLINIC_ID, ['not-a-uuid']])]
    #[TestWith([self::CLINIC_ID, []])]
    public function testNothingValidAnswersNothingWithoutReading(string $clinicId, array $appointmentIds): void
    {
        $repository = $this->createMock(ConsultationReadRepositoryInterface::class);
        $repository->expects(self::never())->method('appointmentIdsWithConsultation');

        self::assertSame([], new ListAppointmentsWithConsultationHandler($repository)(new ListAppointmentsWithConsultation($clinicId, $appointmentIds)));
    }
}
