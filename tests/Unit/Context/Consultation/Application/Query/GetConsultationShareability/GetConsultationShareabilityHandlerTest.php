<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Query\GetConsultationShareability;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientAnimalOwnership;
use App\Context\Consultation\Application\Port\PatientAnimalOwnershipInterface;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Port\ReportPractitioner;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationShareability\ConsultationShareability;
use App\Context\Consultation\Application\Query\GetConsultationShareability\GetConsultationShareability;
use App\Context\Consultation\Application\Query\GetConsultationShareability\GetConsultationShareabilityHandler;
use PHPUnit\Framework\TestCase;

/**
 * « Partager sur le Portal »: closed, animal, active primary owner — in that order.
 */
final class GetConsultationShareabilityHandlerTest extends TestCase
{
    private const string CLINIC       = '01960000-0000-7000-8000-0000000c0001';
    private const string CONSULTATION = '01960000-0000-7000-8000-0000000c0002';
    private const string PATIENT      = '01960000-0000-7000-8000-0000000c0004';
    private const string VET          = '01960000-0000-7000-8000-0000000c0005';
    private const string ANIMAL       = '01960000-0000-7000-8000-0000000c0006';
    private const string CLIENT       = '01960000-0000-7000-8000-0000000c0007';

    public function testAClosedConsultationOfAnOwnedAnimalIsShareable(): void
    {
        $result = $this->handle('CLOSED', new PatientAnimalOwnership(self::ANIMAL, self::CLIENT));

        self::assertNotNull($result);
        self::assertTrue($result->shareable);
        self::assertNull($result->reason);
        self::assertSame(self::PATIENT, $result->patientId);
        self::assertSame(self::ANIMAL, $result->animalId);
        self::assertSame(self::CLIENT, $result->primaryOwnerClientId);
        self::assertSame('2026-10-16T07:30:00+00:00', $result->startedAtUtc->format(\DATE_ATOM));
        self::assertSame('2026-10-16T08:00:00+00:00', $result->closedAtUtc?->format(\DATE_ATOM));
        self::assertSame('Dr Claire Lambert', $result->practitionerName);
    }

    public function testAnOpenConsultationIsNotClosedFirst(): void
    {
        $result = $this->handle('OPEN', new PatientAnimalOwnership(null, null));

        self::assertNotNull($result);
        self::assertFalse($result->shareable);
        self::assertSame(ConsultationShareability::NOT_CLOSED, $result->reason);
        self::assertNull($result->closedAtUtc);
    }

    public function testAPatientWithoutAnimalHasNoAnimal(): void
    {
        $result = $this->handle('CLOSED', new PatientAnimalOwnership(null, null));

        self::assertSame(ConsultationShareability::NO_ANIMAL, $result?->reason);
        self::assertFalse($result->shareable);
    }

    public function testAnAnimalWithoutActivePrimaryOwnerHasNoPrimaryOwner(): void
    {
        $result = $this->handle('CLOSED', new PatientAnimalOwnership(self::ANIMAL, null));

        self::assertSame(ConsultationShareability::NO_PRIMARY_OWNER, $result?->reason);
        self::assertFalse($result->shareable);
        self::assertSame(self::ANIMAL, $result->animalId);
    }

    public function testAnUnresolvablePractitionerHasNoName(): void
    {
        self::assertNull($this->handle('CLOSED', new PatientAnimalOwnership(self::ANIMAL, self::CLIENT), null)?->practitionerName);
    }

    public function testAnUnknownConsultationAnswersNull(): void
    {
        $reads = $this->createStub(ConsultationReadRepositoryInterface::class);
        $reads->method('findById')->willThrowException(new \DomainException('Consultation not found.'));

        $handler = new GetConsultationShareabilityHandler($reads, $this->ownerships(new PatientAnimalOwnership(null, null)), $this->practitioners(null));

        self::assertNull($handler(new GetConsultationShareability(self::CLINIC, self::CONSULTATION)));
        self::assertNull($handler(new GetConsultationShareability(self::CLINIC, 'not-a-uuid')));
    }

    private function handle(string $status, PatientAnimalOwnership $ownership, ?ReportPractitioner $practitioner = new ReportPractitioner('Claire Lambert', 'DR')): ?ConsultationShareability
    {
        $reads = $this->createStub(ConsultationReadRepositoryInterface::class);
        $reads->method('findById')->willReturn(self::consultation($status));

        $handler = new GetConsultationShareabilityHandler($reads, $this->ownerships($ownership), $this->practitioners($practitioner));

        return $handler(new GetConsultationShareability(self::CLINIC, self::CONSULTATION));
    }

    private function ownerships(PatientAnimalOwnership $ownership): PatientAnimalOwnershipInterface
    {
        return new class($ownership) implements PatientAnimalOwnershipInterface {
            public function __construct(private PatientAnimalOwnership $ownership)
            {
            }

            public function ownershipOf(string $clinicId, string $patientId): PatientAnimalOwnership
            {
                return $this->ownership;
            }
        };
    }

    private function practitioners(?ReportPractitioner $practitioner): PractitionerDirectoryInterface
    {
        return new class($practitioner) implements PractitionerDirectoryInterface {
            public function __construct(private ?ReportPractitioner $practitioner)
            {
            }

            public function find(string $clinicId, string $userId): ?ReportPractitioner
            {
                return $this->practitioner;
            }
        };
    }

    private static function consultation(string $status): ConsultationDetailsDTO
    {
        return new ConsultationDetailsDTO(
            consultationId: self::CONSULTATION,
            clinicId: self::CLINIC,
            practitionerUserId: self::VET,
            status: $status,
            appointmentId: null,
            admissionId: '01960000-0000-7000-8000-0000000c0003',
            patientId: self::PATIENT,
            chiefComplaint: null,
            vitals: null,
            notes: [],
            acts: [],
            summary: null,
            startedAtUtc: '2026-10-16 07:30:00',
            closedAtUtc: 'CLOSED' === $status ? '2026-10-16 08:00:00' : null,
            subjectiveText: null,
            objectiveObservations: null,
            teamMemo: null,
            motifs: [],
            typedVitals: [],
            examSystems: [],
            diagnoses: [],
            planActions: [],
            prescriptionLines: [],
            billingLines: [],
            totals: ['totalHtMinorUnits' => 0, 'totalTvaMinorUnits' => 0, 'totalTtcMinorUnits' => 0, 'currency' => 'EUR'],
        );
    }
}
