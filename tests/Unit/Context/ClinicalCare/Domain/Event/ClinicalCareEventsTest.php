<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicalCare\Domain\Event;

use App\Context\ClinicalCare\Domain\Event\ConsultationChiefComplaintRecorded;
use App\Context\ClinicalCare\Domain\Event\ConsultationClinicalNoteAdded;
use App\Context\ClinicalCare\Domain\Event\ConsultationClosed;
use App\Context\ClinicalCare\Domain\Event\ConsultationPatientIdentityAttached;
use App\Context\ClinicalCare\Domain\Event\ConsultationPerformedActAdded;
use App\Context\ClinicalCare\Domain\Event\ConsultationStartedFromAppointment;
use App\Context\ClinicalCare\Domain\Event\ConsultationStartedFromWaitingRoomEntry;
use App\Context\ClinicalCare\Domain\Event\ConsultationVitalsRecorded;
use App\Context\ClinicalCare\Domain\ValueObject\AnimalId;
use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Context\ClinicalCare\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\ClinicalCare\Domain\ValueObject\ClinicId;
use App\Context\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\Context\ClinicalCare\Domain\ValueObject\NoteType;
use App\Context\ClinicalCare\Domain\ValueObject\OwnerId;
use App\Context\ClinicalCare\Domain\ValueObject\PerformedActRecord;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use App\Context\ClinicalCare\Domain\ValueObject\Vitals;
use App\Context\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use PHPUnit\Framework\TestCase;

final class ClinicalCareEventsTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ENTRY_ID        = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string OWNER_ID        = '66666666-6666-4666-8666-666666666666';
    private const string ANIMAL_ID       = '77777777-7777-4777-8777-777777777777';

    public function testConsultationStartedFromAppointment(): void
    {
        $event = new ConsultationStartedFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10T09:00:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId'     => self::CONSULTATION_ID,
            'clinicId'           => self::CLINIC_ID,
            'appointmentId'      => self::APPOINTMENT_ID,
            'practitionerUserId' => self::USER_ID,
            'occurredOn'         => '2026-04-10T09:00:00+00:00',
        ], $event->payload());
    }

    public function testConsultationStartedFromWaitingRoomEntry(): void
    {
        $event = new ConsultationStartedFromWaitingRoomEntry(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            WaitingRoomEntryId::fromString(self::ENTRY_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10T09:00:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId'     => self::CONSULTATION_ID,
            'clinicId'           => self::CLINIC_ID,
            'waitingRoomEntryId' => self::ENTRY_ID,
            'practitionerUserId' => self::USER_ID,
            'occurredOn'         => '2026-04-10T09:00:00+00:00',
        ], $event->payload());
    }

    public function testConsultationChiefComplaintRecorded(): void
    {
        $event = new ConsultationChiefComplaintRecorded(
            ConsultationId::fromString(self::CONSULTATION_ID),
            'Limping for 3 days',
            new \DateTimeImmutable('2026-04-10T09:05:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId' => self::CONSULTATION_ID,
            'chiefComplaint' => 'Limping for 3 days',
            'occurredOn'     => '2026-04-10T09:05:00+00:00',
        ], $event->payload());
    }

    public function testConsultationPatientIdentityAttached(): void
    {
        $event = new ConsultationPatientIdentityAttached(
            ConsultationId::fromString(self::CONSULTATION_ID),
            OwnerId::fromString(self::OWNER_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            new \DateTimeImmutable('2026-04-10T09:10:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId' => self::CONSULTATION_ID,
            'ownerId'        => self::OWNER_ID,
            'animalId'       => self::ANIMAL_ID,
            'occurredOn'     => '2026-04-10T09:10:00+00:00',
        ], $event->payload());
    }

    public function testConsultationPatientIdentityAttachedAcceptsNullPair(): void
    {
        $event = new ConsultationPatientIdentityAttached(
            ConsultationId::fromString(self::CONSULTATION_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10T09:10:00+00:00'),
        );

        $payload = $event->payload();
        self::assertNull($payload['ownerId']);
        self::assertNull($payload['animalId']);
    }

    public function testConsultationVitalsRecorded(): void
    {
        $event = new ConsultationVitalsRecorded(
            ConsultationId::fromString(self::CONSULTATION_ID),
            Vitals::create(12.5, 38.2),
            new \DateTimeImmutable('2026-04-10T09:15:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        $payload = $event->payload();
        self::assertSame(12.5, $payload['weightKg']);
        self::assertSame(38.2, $payload['temperatureC']);
        self::assertSame('2026-04-10T09:15:00+00:00', $payload['occurredOn']);
    }

    public function testConsultationClinicalNoteAdded(): void
    {
        $note = ClinicalNoteRecord::create(
            NoteType::DIAGNOSIS,
            'Otitis externa',
            new \DateTimeImmutable('2026-04-10T09:20:00+00:00'),
            UserId::fromString(self::USER_ID),
        );

        $event = new ConsultationClinicalNoteAdded(
            ConsultationId::fromString(self::CONSULTATION_ID),
            $note,
            new \DateTimeImmutable('2026-04-10T09:20:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        $payload = $event->payload();
        self::assertSame('DIAGNOSIS', $payload['noteType']);
        self::assertSame('Otitis externa', $payload['content']);
        self::assertSame(self::USER_ID, $payload['createdByUserId']);
    }

    public function testConsultationPerformedActAdded(): void
    {
        $act = PerformedActRecord::create(
            'Vaccine injection',
            1.0,
            new \DateTimeImmutable('2026-04-10T09:25:00+00:00'),
            new \DateTimeImmutable('2026-04-10T09:25:00+00:00'),
            UserId::fromString(self::USER_ID),
        );

        $event = new ConsultationPerformedActAdded(
            ConsultationId::fromString(self::CONSULTATION_ID),
            $act,
            new \DateTimeImmutable('2026-04-10T09:25:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        $payload = $event->payload();
        self::assertSame('Vaccine injection', $payload['label']);
        self::assertSame(1.0, $payload['quantity']);
        self::assertSame(self::USER_ID, $payload['createdByUserId']);
    }

    public function testConsultationClosed(): void
    {
        $event = new ConsultationClosed(
            ConsultationId::fromString(self::CONSULTATION_ID),
            UserId::fromString(self::USER_ID),
            'Stable, follow up in 1 week',
            new \DateTimeImmutable('2026-04-10T09:30:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId' => self::CONSULTATION_ID,
            'closedByUserId' => self::USER_ID,
            'summary'        => 'Stable, follow up in 1 week',
            'occurredOn'     => '2026-04-10T09:30:00+00:00',
        ], $event->payload());
    }

    public function testConsultationClosedAcceptsNullSummary(): void
    {
        $event = new ConsultationClosed(
            ConsultationId::fromString(self::CONSULTATION_ID),
            UserId::fromString(self::USER_ID),
            null,
            new \DateTimeImmutable('2026-04-10T09:30:00+00:00'),
        );

        self::assertNull($event->payload()['summary']);
    }
}
