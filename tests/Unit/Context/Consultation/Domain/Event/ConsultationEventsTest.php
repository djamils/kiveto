<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\Event;

use App\Context\Consultation\Domain\Event\ConsultationChiefComplaintRecorded;
use App\Context\Consultation\Domain\Event\ConsultationClinicalNoteAdded;
use App\Context\Consultation\Domain\Event\ConsultationClosed;
use App\Context\Consultation\Domain\Event\ConsultationPerformedActAdded;
use App\Context\Consultation\Domain\Event\ConsultationStartedFromAdmission;
use App\Context\Consultation\Domain\Event\ConsultationStartedFromAppointment;
use App\Context\Consultation\Domain\Event\ConsultationVitalsRecorded;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\NoteType;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\PerformedActRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
use PHPUnit\Framework\TestCase;

final class ConsultationEventsTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';

    public function testConsultationStartedFromAppointment(): void
    {
        $event = new ConsultationStartedFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10T09:00:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId'     => self::CONSULTATION_ID,
            'clinicId'           => self::CLINIC_ID,
            'appointmentId'      => self::APPOINTMENT_ID,
            'admissionId'        => self::ADMISSION_ID,
            'patientId'          => self::PATIENT_ID,
            'practitionerUserId' => self::USER_ID,
            'occurredOn'         => '2026-04-10T09:00:00+00:00',
        ], $event->payload());
    }

    public function testConsultationStartedFromAdmission(): void
    {
        $event = new ConsultationStartedFromAdmission(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10T09:00:00+00:00'),
        );

        self::assertSame(self::CONSULTATION_ID, $event->aggregateId());
        self::assertSame([
            'consultationId'     => self::CONSULTATION_ID,
            'clinicId'           => self::CLINIC_ID,
            'admissionId'        => self::ADMISSION_ID,
            'patientId'          => self::PATIENT_ID,
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
