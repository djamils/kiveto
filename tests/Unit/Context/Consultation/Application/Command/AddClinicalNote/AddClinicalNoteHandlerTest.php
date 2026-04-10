<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\AddClinicalNote;

use App\Context\Consultation\Application\Command\AddClinicalNote\AddClinicalNote;
use App\Context\Consultation\Application\Command\AddClinicalNote\AddClinicalNoteHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddClinicalNoteHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private ClockInterface&MockObject $clock;
    private AddClinicalNoteHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new AddClinicalNoteHandler($this->consultations, $this->clock);
    }

    public function testAddNoteSuccessfully(): void
    {
        $consultation = $this->makeConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:15:00'));
        $this->consultations->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Consultation $c): bool => 1 === \count($c->getNotes())))
        ;

        ($this->handler)(new AddClinicalNote(
            consultationId: self::CONSULTATION_ID,
            noteType: 'DIAGNOSIS',
            content: 'Otitis externa',
            createdByUserId: self::USER_ID,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)(new AddClinicalNote(
            consultationId: self::CONSULTATION_ID,
            noteType: 'DIAGNOSIS',
            content: 'Otitis externa',
            createdByUserId: self::USER_ID,
        ));
    }

    private function makeConsultation(): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }
}
