<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicalCare\Application\Command\RecordVitals;

use App\Context\ClinicalCare\Application\Command\RecordVitals\RecordVitals;
use App\Context\ClinicalCare\Application\Command\RecordVitals\RecordVitalsHandler;
use App\Context\ClinicalCare\Domain\Consultation;
use App\Context\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Context\ClinicalCare\Domain\ValueObject\ClinicId;
use App\Context\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RecordVitalsHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private ClockInterface&MockObject $clock;
    private RecordVitalsHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new RecordVitalsHandler($this->consultations, $this->clock);
    }

    public function testRecordVitalsSuccessfully(): void
    {
        $consultation = $this->makeConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Consultation $c): bool => null !== $c->getVitals()))
        ;

        ($this->handler)(new RecordVitals(
            consultationId: self::CONSULTATION_ID,
            weightKg: 12.5,
            temperatureC: 38.2,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)(new RecordVitals(
            consultationId: self::CONSULTATION_ID,
            weightKg: 12.5,
            temperatureC: null,
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
