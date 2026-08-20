<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\AddDiagnosis;

use App\Context\Consultation\Application\Command\AddDiagnosis\AddDiagnosis;
use App\Context\Consultation\Application\Command\AddDiagnosis\AddDiagnosisHandler;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisRecord;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddDiagnosisHandlerTest extends TestCase
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
    private AddDiagnosisHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);
        $this->handler       = new AddDiagnosisHandler($this->consultations, $this->clock);
    }

    public function testAddDiagnosisSuccessfully(): void
    {
        $consultation = $this->makeConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command('PROBABLE', 'MANUAL'));

        self::assertSame(['Otite externe'], self::labelsOf($consultation));
        self::assertSame([DiagnosisCertainty::PROBABLE], self::certaintiesOf($consultation));
        self::assertSame([DiagnosisSource::MANUAL], self::sourcesOf($consultation));
        self::assertSame([true], self::primaryFlagsOf($consultation));
    }

    public function testAddPrimaryDiagnosisDemotesThePreviousOne(): void
    {
        $consultation = $this->makeConsultation();
        $consultation->addDiagnosis(
            null,
            'Ancien diagnostic',
            DiagnosisCertainty::POSSIBLE,
            null,
            true,
            DiagnosisSource::MANUAL,
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:05:00'),
        );

        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:10:00'));
        $this->consultations->expects(self::once())->method('save')->with(self::identicalTo($consultation));

        ($this->handler)($this->command('CERTAIN', 'AI_SUGGESTION'));

        self::assertSame([false, true], self::primaryFlagsOf($consultation));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)($this->command('PROBABLE', 'MANUAL'));
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

        ($this->handler)($this->command('PROBABLE', 'MANUAL'));
    }

    public function testFailsWhenConsultationIsClosed(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeClosedConsultation());
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        ($this->handler)($this->command('PROBABLE', 'MANUAL'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenCertaintyIsUnknown(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown diagnosis certainty');

        ($this->handler)($this->command('MAYBE', 'MANUAL'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenSourceIsUnknown(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn($this->makeConsultation());
        $this->consultations->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown diagnosis source');

        ($this->handler)($this->command('PROBABLE', 'ORACLE'));
    }

    private function command(string $certainty, string $source): AddDiagnosis
    {
        return new AddDiagnosis(
            consultationId: self::CONSULTATION_ID,
            clinicId: self::CLINIC_ID,
            code: 'H60',
            label: 'Otite externe',
            certainty: $certainty,
            note: 'Oreille droite',
            isPrimary: true,
            source: $source,
            createdByUserId: self::USER_ID,
        );
    }

    /**
     * @return list<string>
     */
    private static function labelsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (DiagnosisRecord $diagnosis): string => $diagnosis->getLabel(),
            $consultation->getDiagnoses(),
        );
    }

    /**
     * @return list<DiagnosisCertainty>
     */
    private static function certaintiesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (DiagnosisRecord $diagnosis): DiagnosisCertainty => $diagnosis->getCertainty(),
            $consultation->getDiagnoses(),
        );
    }

    /**
     * @return list<DiagnosisSource>
     */
    private static function sourcesOf(Consultation $consultation): array
    {
        return array_map(
            static fn (DiagnosisRecord $diagnosis): DiagnosisSource => $diagnosis->getSource(),
            $consultation->getDiagnoses(),
        );
    }

    /**
     * @return list<bool>
     */
    private static function primaryFlagsOf(Consultation $consultation): array
    {
        return array_map(
            static fn (DiagnosisRecord $diagnosis): bool => $diagnosis->isPrimary(),
            $consultation->getDiagnoses(),
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
