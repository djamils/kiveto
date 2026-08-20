<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\UpdateConsultationMotifs;

use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifs;
use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifsHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\MotifTag;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateConsultationMotifsHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';
    private const string OTHER_CLINIC_ID = '77777777-7777-4777-8777-777777777777';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private ClockInterface&MockObject $clock;
    private UpdateConsultationMotifsHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new UpdateConsultationMotifsHandler($this->consultations, $this->clock);
    }

    public function testUpdateMotifsSuccessfully(): void
    {
        $consultation = $this->makeConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)(new UpdateConsultationMotifs(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            labels: ['Toux', 'toux', 'Fièvre'],
        ));

        self::assertSame(['Toux', 'Fièvre'], self::labelsOf($consultation));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)(new UpdateConsultationMotifs(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            labels: ['Toux'],
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationBelongsToAnotherClinic(): void
    {
        $this->consultations->expects(self::once())
            ->method('findById')
            ->willReturn($this->makeConsultation(self::OTHER_CLINIC_ID))
        ;
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)(new UpdateConsultationMotifs(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            labels: ['Toux'],
        ));
    }

    public function testFailsWhenConsultationIsClosed(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeClosedConsultation());
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        ($this->handler)(new UpdateConsultationMotifs(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            labels: ['Toux'],
        ));
    }

    /**
     * @return list<string>
     */
    private static function labelsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (MotifTag $motif): string => $motif->getLabel(),
            $consultation->getMotifs(),
        );
    }

    private function makeConsultation(string $clinicId = self::CLINIC_ID): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString($clinicId),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }

    private function makeClosedConsultation(): Consultation
    {
        $consultation = $this->makeConsultation();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 09:30:00'),
        );

        return $consultation;
    }
}
