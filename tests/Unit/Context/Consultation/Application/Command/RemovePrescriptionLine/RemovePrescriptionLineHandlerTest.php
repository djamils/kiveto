<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\RemovePrescriptionLine;

use App\Context\Consultation\Application\Command\RemovePrescriptionLine\RemovePrescriptionLine;
use App\Context\Consultation\Application\Command\RemovePrescriptionLine\RemovePrescriptionLineHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\BillingLineRecord;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PrescriptionLineRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RemovePrescriptionLineHandlerTest extends TestCase
{
    private const string CONSULTATION_ID      = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID            = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID       = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID         = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID              = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID           = '66666666-6666-4666-8666-666666666666';
    private const string OTHER_CLINIC_ID      = '77777777-7777-4777-8777-777777777777';
    private const string ARTICLE_ID           = '88888888-8888-4888-8888-888888888888';
    private const string PRESCRIPTION_LINE_ID = '99999999-9999-4999-8999-999999999999';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private ClockInterface&MockObject $clock;
    private RemovePrescriptionLineHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new RemovePrescriptionLineHandler($this->consultations, $this->clock);
    }

    public function testRemovePrescriptionLineDropsItsBillingLine(): void
    {
        $consultation = $this->makeConsultationWithPrescriptionLine();
        $lineId       = self::firstPrescriptionLineId($consultation);

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command($lineId));

        self::assertSame([], self::labelsOf($consultation));
        self::assertSame([], self::billedLabelsOf($consultation));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command(self::PRESCRIPTION_LINE_ID));
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

        ($this->handler)($this->command(self::PRESCRIPTION_LINE_ID));
    }

    public function testFailsWhenConsultationIsClosed(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeClosedConsultation());
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        ($this->handler)($this->command(self::PRESCRIPTION_LINE_ID));
    }

    private function command(string $prescriptionLineId): RemovePrescriptionLine
    {
        return new RemovePrescriptionLine(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            prescriptionLineId: $prescriptionLineId,
        );
    }

    private static function firstPrescriptionLineId(Consultation $consultation): string
    {
        $lines = $consultation->getPrescriptionLines();

        if ([] === $lines) {
            self::fail('Expected the consultation to hold a prescription line.');
        }

        return $lines[0]->getId();
    }

    /**
     * @return list<string>
     */
    private static function labelsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (PrescriptionLineRecord $line): string => $line->getLabel(),
            $consultation->getPrescriptionLines(),
        );
    }

    /**
     * @return list<string>
     */
    private static function billedLabelsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (BillingLineRecord $line): string => $line->getLabel(),
            $consultation->getBillingLines(),
        );
    }

    private function makeConsultationWithPrescriptionLine(): Consultation
    {
        $consultation = $this->makeConsultation();
        $consultation->addPrescriptionLine(
            self::ARTICLE_ID,
            'MED-001',
            'Amoxicilline 500mg',
            '1 comprimé',
            '2x/jour',
            7,
            'Orale',
            14.0,
            1250,
            'EUR',
            'REDUCED',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:05:00'),
        );

        return $consultation;
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
