<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Application\Command;

use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultation;
use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultationHandler;
use App\Context\Prescription\Application\Port\ConsultationPrescriptionLineDto;
use App\Context\Prescription\Application\Port\ConsultationPrescriptionProviderInterface;
use App\Context\Prescription\Application\Port\DrugDetailsDto;
use App\Context\Prescription\Application\Port\DrugRegistryInterface;
use App\Context\Prescription\Application\Port\PatientDirectoryInterface;
use App\Context\Prescription\Application\Port\PatientSnapshotDto;
use App\Context\Prescription\Application\Port\PrescriberDirectoryInterface;
use App\Context\Prescription\Application\Port\PrescriberSnapshotDto;
use App\Context\Prescription\Domain\Exception\InvalidPatientSnapshotException;
use App\Context\Prescription\Domain\Exception\InvalidPrescriberException;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use App\Context\Prescription\Domain\ValueObject\Renewals;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Several stubs here only feed the handler its inputs; the assertions are on
 * the ordonnance that comes out.
 */
#[AllowMockObjectsWithoutExpectations]
final class PreparePrescriptionForConsultationHandlerTest extends TestCase
{
    private const string CLINIC_ID       = '01960000-0000-7000-8000-000000000201';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-000000000202';
    private const string PRESCRIBER_ID   = '01960000-0000-7000-8000-000000000203';
    private const string PRESCRIPTION_ID = '01960000-0000-7000-8000-000000000204';
    private const string ANIMAL_ID       = '01960000-0000-7000-8000-000000000205';
    private const string CLIENT_ID       = '01960000-0000-7000-8000-000000000206';

    private PrescriptionRepositoryInterface&MockObject $prescriptions;
    private ConsultationPrescriptionProviderInterface&MockObject $consultationLines;
    private PrescriberDirectoryInterface&MockObject $prescribers;
    private PatientDirectoryInterface&MockObject $patients;
    private DrugRegistryInterface&MockObject $drugs;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private ClockInterface&MockObject $clock;
    private PreparePrescriptionForConsultationHandler $handler;

    protected function setUp(): void
    {
        $this->prescriptions     = $this->createMock(PrescriptionRepositoryInterface::class);
        $this->consultationLines = $this->createMock(ConsultationPrescriptionProviderInterface::class);
        $this->prescribers       = $this->createMock(PrescriberDirectoryInterface::class);
        $this->patients          = $this->createMock(PatientDirectoryInterface::class);
        $this->drugs             = $this->createMock(DrugRegistryInterface::class);
        $this->uuidGenerator     = $this->createMock(UuidGeneratorInterface::class);
        $this->clock             = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now());

        $this->handler = new PreparePrescriptionForConsultationHandler(
            $this->prescriptions,
            $this->consultationLines,
            $this->prescribers,
            $this->patients,
            $this->drugs,
            $this->uuidGenerator,
            $this->clock,
        );
    }

    public function testItSnapshotsTheConsultationIntoADraft(): void
    {
        $this->prescriptions->expects(self::once())->method('findForConsultation')->willReturn(null);
        $this->uuidGenerator->method('generate')->willReturnOnConsecutiveCalls(
            self::PRESCRIPTION_ID,
            '01960000-0000-7000-8000-000000000301',
            '01960000-0000-7000-8000-000000000302',
        );
        $this->prescribers->method('forUser')->willReturn($this->prescriberSnapshot());
        $this->patients->method('forConsultation')->willReturn($this->patientSnapshot());

        $this->consultationLines->expects(self::once())
            ->method('linesForConsultation')
            ->with(self::CONSULTATION_ID, self::CLINIC_ID)
            ->willReturn([
                $this->consultationLine('Meloxidyl', 'MELOXIDYL-1', 'article-1'),
                $this->consultationLine('Note libre', null, null),
            ])
        ;

        // Only the catalogued line is looked up; free text has nothing to look up.
        $this->drugs->expects(self::once())
            ->method('detailsOf')
            ->with('article-1', self::CLINIC_ID)
            ->willReturn(new DrugDetailsDto('Suspension buvable', ['Méloxicam'], 'RX'))
        ;

        $saved = null;
        $this->prescriptions->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Prescription $prescription) use (&$saved): void {
                $saved = $prescription;
            })
        ;

        $id = ($this->handler)($this->command());

        self::assertSame(self::PRESCRIPTION_ID, $id);
        self::assertInstanceOf(Prescription::class, $saved);
        self::assertSame(PrescriptionStatus::DRAFT, $saved->status());
        self::assertCount(2, $saved->lines());
        self::assertSame('Rousseau', $saved->prescriber()->displayName());
        self::assertSame('FR-12345', $saved->prescriber()->registrationNumber());
        self::assertSame('Rex', $saved->patient()->name());
        self::assertSame('Labrador', $saved->patient()->breed());
        self::assertSame('Sophie Dupont', $saved->owner()->displayName());
        self::assertSame(['12 rue des Lilas'], $saved->owner()->addressLines());
        self::assertSame('Suspension buvable', $saved->lines()[0]->pharmaceuticalForm());
        self::assertSame(['Méloxicam'], $saved->lines()[0]->activeSubstances());
        self::assertSame(DrugPrescriptionClass::RX, $saved->lines()[0]->prescriptionClass());
        // The free line falls back to the plainest classification.
        self::assertSame(DrugPrescriptionClass::NONE, $saved->lines()[1]->prescriptionClass());
    }

    public function testAnUnknownDrugClassFallsBackToNone(): void
    {
        $this->prescriptions->method('findForConsultation')->willReturn(null);
        $this->uuidGenerator->method('generate')->willReturn(self::PRESCRIPTION_ID);
        $this->prescribers->method('forUser')->willReturn($this->prescriberSnapshot());
        $this->patients->method('forConsultation')->willReturn($this->patientSnapshot());
        $this->consultationLines->method('linesForConsultation')->willReturn([
            $this->consultationLine('Meloxidyl', 'MELOXIDYL-1', 'article-1'),
        ]);
        $this->drugs->method('detailsOf')->willReturn(new DrugDetailsDto(null, [], 'SOMETHING_ELSE'));

        $saved = null;
        $this->prescriptions->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Prescription $prescription) use (&$saved): void {
                $saved = $prescription;
            })
        ;

        ($this->handler)($this->command());

        self::assertInstanceOf(Prescription::class, $saved);
        self::assertSame(DrugPrescriptionClass::NONE, $saved->lines()[0]->prescriptionClass());
    }

    public function testAnAlreadySignedPrescriptionIsHandedBackUntouched(): void
    {
        $existing = $this->issuedPrescription();

        $this->prescriptions->expects(self::once())->method('findForConsultation')->willReturn($existing);
        $this->prescriptions->expects(self::never())->method('save');
        $this->consultationLines->expects(self::never())->method('linesForConsultation');

        self::assertSame(self::PRESCRIPTION_ID, ($this->handler)($this->command()));
    }

    public function testAnExistingDraftIsRebuiltUnderItsOwnId(): void
    {
        $existing = Prescription::draft(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            prescriber: $this->prescriber(),
            patient: PatientSnapshot::create(null, 'Rex'),
            owner: OwnerSnapshot::unknown(),
            now: $this->now(),
        );

        $this->prescriptions->expects(self::once())->method('findForConsultation')->willReturn($existing);
        $this->uuidGenerator->method('generate')->willReturn('01960000-0000-7000-8000-000000000301');
        $this->prescribers->method('forUser')->willReturn($this->prescriberSnapshot());
        $this->patients->method('forConsultation')->willReturn($this->patientSnapshot(ownerName: null));
        $this->consultationLines->method('linesForConsultation')->willReturn([
            $this->consultationLine('Meloxidyl', 'MELOXIDYL-1', 'article-1'),
        ]);
        $this->drugs->method('detailsOf')->willReturn(DrugDetailsDto::unknown());

        $saved = null;
        $this->prescriptions->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Prescription $prescription) use (&$saved): void {
                $saved = $prescription;
            })
        ;

        self::assertSame(self::PRESCRIPTION_ID, ($this->handler)($this->command()));
        self::assertInstanceOf(Prescription::class, $saved);
        // No owner on file: the ordonnance still has to name a holder.
        self::assertSame('Détenteur non identifié', $saved->owner()->displayName());
        self::assertCount(1, $saved->lines());
    }

    public function testAPractitionerWithoutAProfileCannotPrescribeHere(): void
    {
        $this->prescriptions->method('findForConsultation')->willReturn(null);
        $this->patients->method('forConsultation')->willReturn($this->patientSnapshot());
        $this->prescribers->expects(self::once())->method('forUser')->willReturn(null);

        $this->expectException(InvalidPrescriberException::class);
        $this->expectExceptionMessage('has no profile in the clinic');

        ($this->handler)($this->command());
    }

    public function testAConsultationWithoutAnIdentifiedPatientIsRefused(): void
    {
        $this->prescriptions->method('findForConsultation')->willReturn(null);
        $this->patients->expects(self::once())->method('forConsultation')->willReturn(null);

        $this->expectException(InvalidPatientSnapshotException::class);
        $this->expectExceptionMessage('no identified patient');

        ($this->handler)($this->command());
    }

    private function command(): PreparePrescriptionForConsultation
    {
        return new PreparePrescriptionForConsultation(
            clinicId: self::CLINIC_ID,
            consultationId: self::CONSULTATION_ID,
            prescriberUserId: self::PRESCRIBER_ID,
        );
    }

    private function consultationLine(
        string $label,
        ?string $code,
        ?string $articleId,
    ): ConsultationPrescriptionLineDto {
        return new ConsultationPrescriptionLineDto(
            sourceLineId: '01960000-0000-7000-8000-000000000401',
            articleId: $articleId,
            code: $code,
            label: $label,
            dose: '0,1 mg/kg',
            frequency: '1×/j',
            durationDays: 7,
            route: 'per os',
            quantity: '1',
        );
    }

    private function prescriberSnapshot(): PrescriberSnapshotDto
    {
        return new PrescriberSnapshotDto(self::PRESCRIBER_ID, 'Rousseau', 'FR-12345', 'DR');
    }

    private function patientSnapshot(?string $ownerName = 'Sophie Dupont'): PatientSnapshotDto
    {
        return new PatientSnapshotDto(
            animalId: self::ANIMAL_ID,
            animalName: 'Rex',
            species: 'dog',
            breed: 'Labrador',
            birthDate: new \DateTimeImmutable('2016-05-01'),
            microchipNumber: '250269801234567',
            ownerClientId: self::CLIENT_ID,
            ownerName: $ownerName,
            ownerAddressLines: ['12 rue des Lilas'],
        );
    }

    private function prescriber(): Prescriber
    {
        return Prescriber::create(PrescriberId::fromString(self::PRESCRIBER_ID), 'Rousseau', 'FR-12345', 'DR');
    }

    private function issuedPrescription(): Prescription
    {
        return Prescription::reconstitute(
            id: PrescriptionId::fromString(self::PRESCRIPTION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            consultationId: ConsultationId::fromString(self::CONSULTATION_ID),
            number: PrescriptionNumber::of(2026, 1),
            status: PrescriptionStatus::ISSUED,
            prescriber: $this->prescriber(),
            patient: PatientSnapshot::create(null, 'Rex'),
            owner: OwnerSnapshot::unknown(),
            lines: [],
            validity: null,
            renewals: Renewals::none(),
            renewalHistory: [],
            notes: null,
            createdAt: $this->now(),
            updatedAt: $this->now(),
            cancelledAt: null,
            cancellationReason: null,
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
