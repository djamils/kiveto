<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Domain;

use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\Event\ConsultationChiefComplaintRecorded;
use App\ClinicalCare\Domain\Event\ConsultationClinicalNoteAdded;
use App\ClinicalCare\Domain\Event\ConsultationClosed;
use App\ClinicalCare\Domain\Event\ConsultationPatientIdentityAttached;
use App\ClinicalCare\Domain\Event\ConsultationPerformedActAdded;
use App\ClinicalCare\Domain\Event\ConsultationStartedFromAppointment;
use App\ClinicalCare\Domain\Event\ConsultationStartedFromWaitingRoomEntry;
use App\ClinicalCare\Domain\Event\ConsultationVitalsRecorded;
use App\ClinicalCare\Domain\ValueObject\AnimalId;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\ConsultationStatus;
use App\ClinicalCare\Domain\ValueObject\NoteType;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\Vitals;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use PHPUnit\Framework\TestCase;

final class ConsultationTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ENTRY_ID        = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string OWNER_ID        = '66666666-6666-4666-8666-666666666666';
    private const string ANIMAL_ID       = '77777777-7777-4777-8777-777777777777';

    public function testStartFromAppointment(): void
    {
        $startedAt = new \DateTimeImmutable('2026-04-10 09:00:00');

        $consultation = Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
            OwnerId::fromString(self::OWNER_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            $startedAt,
        );

        self::assertSame(self::CONSULTATION_ID, $consultation->getId()->toString());
        self::assertSame(self::CLINIC_ID, $consultation->getClinicId()->toString());
        self::assertSame(self::APPOINTMENT_ID, $consultation->getAppointmentId()?->toString());
        self::assertNull($consultation->getWaitingRoomEntryId());
        self::assertSame(self::USER_ID, $consultation->getPractitionerUserId()->toString());
        self::assertSame(self::OWNER_ID, $consultation->getOwnerId()?->toString());
        self::assertSame(self::ANIMAL_ID, $consultation->getAnimalId()?->toString());
        self::assertSame(ConsultationStatus::OPEN, $consultation->getStatus());
        self::assertNull($consultation->getChiefComplaint());
        self::assertNull($consultation->getVitals());
        self::assertNull($consultation->getSummary());
        self::assertNull($consultation->getClosedAtUtc());
        self::assertSame($startedAt, $consultation->getStartedAtUtc());
        self::assertSame($startedAt, $consultation->getCreatedAtUtc());
        self::assertSame($startedAt, $consultation->getUpdatedAtUtc());
        self::assertSame([], $consultation->getNotes());
        self::assertSame([], $consultation->getActs());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationStartedFromAppointment::class, $events[0]);
    }

    public function testStartFromWaitingRoomEntry(): void
    {
        $consultation = Consultation::startFromWaitingRoomEntry(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            WaitingRoomEntryId::fromString(self::ENTRY_ID),
            UserId::fromString(self::USER_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );

        self::assertSame(self::ENTRY_ID, $consultation->getWaitingRoomEntryId()?->toString());
        self::assertNull($consultation->getAppointmentId());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationStartedFromWaitingRoomEntry::class, $events[0]);
    }

    public function testAttachPatientIdentity(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->attachPatientIdentity(
            OwnerId::fromString(self::OWNER_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            new \DateTimeImmutable('2026-04-10 09:05:00'),
        );

        self::assertSame(self::OWNER_ID, $consultation->getOwnerId()?->toString());
        self::assertSame(self::ANIMAL_ID, $consultation->getAnimalId()?->toString());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationPatientIdentityAttached::class, $events[0]);
    }

    public function testAttachPatientIdentityRequiresAtLeastOneOfOwnerOrAnimal(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('At least one of owner or animal must be provided');

        $consultation->attachPatientIdentity(null, null, new \DateTimeImmutable('2026-04-10 09:05:00'));
    }

    public function testAttachPatientIdentityFailsOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        $consultation->attachPatientIdentity(
            OwnerId::fromString(self::OWNER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testRecordChiefComplaint(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->recordChiefComplaint(
            'Limping right paw for 3 days',
            new \DateTimeImmutable('2026-04-10 09:05:00'),
        );

        self::assertSame('Limping right paw for 3 days', $consultation->getChiefComplaint());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationChiefComplaintRecorded::class, $events[0]);
    }

    public function testRecordChiefComplaintRejectsEmptyContent(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chief complaint cannot be empty');

        $consultation->recordChiefComplaint('   ', new \DateTimeImmutable('2026-04-10 09:05:00'));
    }

    public function testRecordVitals(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $vitals = Vitals::create(weightKg: 12.5, temperatureC: 38.2);
        $consultation->recordVitals($vitals, new \DateTimeImmutable('2026-04-10 09:10:00'));

        self::assertSame($vitals, $consultation->getVitals());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationVitalsRecorded::class, $events[0]);
    }

    public function testAddClinicalNote(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->addClinicalNote(
            NoteType::DIAGNOSIS,
            'Otitis externa',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:15:00'),
        );

        self::assertCount(1, $consultation->getNotes());
        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationClinicalNoteAdded::class, $events[0]);
    }

    public function testAddPerformedAct(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->addPerformedAct(
            'Otoscopy',
            1.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
        );

        self::assertCount(1, $consultation->getActs());
        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationPerformedActAdded::class, $events[0]);
    }

    public function testCloseConsultation(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $pulled       = $consultation->pullDomainEvents();
        unset($pulled);

        $consultation->close(
            UserId::fromString(self::USER_ID),
            'All good',
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        self::assertSame(ConsultationStatus::CLOSED, $consultation->getStatus());
        self::assertSame('All good', $consultation->getSummary());
        self::assertNotNull($consultation->getClosedAtUtc());

        $events = $consultation->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsultationClosed::class, $events[0]);
    }

    public function testCannotCloseTwice(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot modify a closed consultation');

        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testCannotRecordVitalsOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->recordVitals(
            Vitals::create(10.0, null),
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testCannotRecordChiefComplaintOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->recordChiefComplaint('Late', new \DateTimeImmutable('2026-04-10 10:05:00'));
    }

    public function testCannotAddNoteOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->addClinicalNote(
            NoteType::GENERAL,
            'Late note',
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testCannotAddPerformedActOnClosedConsultation(): void
    {
        $consultation = $this->makeOpenConsultationWithoutPatient();
        $consultation->close(
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10 10:00:00'),
        );

        $this->expectException(\DomainException::class);
        $consultation->addPerformedAct(
            'Late act',
            1.0,
            new \DateTimeImmutable('2026-04-10 10:05:00'),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 10:05:00'),
        );
    }

    public function testReconstituteRehydratesWithoutEvents(): void
    {
        $consultation = Consultation::reconstitute(
            id: ConsultationId::fromString(self::CONSULTATION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            appointmentId: null,
            waitingRoomEntryId: WaitingRoomEntryId::fromString(self::ENTRY_ID),
            practitionerUserId: UserId::fromString(self::USER_ID),
            ownerId: OwnerId::fromString(self::OWNER_ID),
            animalId: AnimalId::fromString(self::ANIMAL_ID),
            status: ConsultationStatus::CLOSED,
            chiefComplaint: 'Pre-existing',
            vitals: Vitals::create(10.0, 38.0),
            summary: 'Done',
            startedAtUtc: new \DateTimeImmutable('2026-04-10 09:00:00'),
            closedAtUtc: new \DateTimeImmutable('2026-04-10 10:00:00'),
            createdAtUtc: new \DateTimeImmutable('2026-04-10 09:00:00'),
            updatedAtUtc: new \DateTimeImmutable('2026-04-10 10:00:00'),
            notes: [],
            acts: [],
        );

        self::assertSame(ConsultationStatus::CLOSED, $consultation->getStatus());
        self::assertCount(0, $consultation->recordedDomainEvents());
    }

    private function makeOpenConsultationWithoutPatient(): Consultation
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
