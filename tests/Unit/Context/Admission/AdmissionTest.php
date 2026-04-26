<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Event\AdmissionClosed;
use App\Context\Admission\Domain\Event\AdmissionLocationStatusUpdated;
use App\Context\Admission\Domain\Event\AdmissionOpened;
use App\Context\Admission\Domain\Event\AdmissionReopenedInError;
use App\Context\Admission\Domain\Event\AdmissionTriageDeescalated;
use App\Context\Admission\Domain\Event\AdmissionTriageEscalated;
use App\Context\Admission\Domain\Exception\AdmissionAlreadyClosedException;
use App\Context\Admission\Domain\Exception\AdmissionReopenNotAllowedException;
use App\Context\Admission\Domain\Exception\TriageDeescalationNotAllowedException;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatus;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use PHPUnit\Framework\TestCase;

final class AdmissionTest extends TestCase
{
    public function testOpenRecordsAdmissionOpenedEvent(): void
    {
        $admission = $this->makeOpenAdmission(true);

        $events = $admission->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionOpened::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AdmissionOpened::class, $event);
        self::assertSame($this->makeId()->value(), $event->admissionId);
        self::assertTrue($event->isPatientIdentified);
        self::assertSame(IntakeChannel::WalkIn->value, $event->intakeChannel);
        self::assertSame(TriageLevel::Standard->value, $event->triageLevel);
    }

    public function testOpenWithUnidentifiedPatientRecordsOneEvent(): void
    {
        // Aggregate only records AdmissionOpened — the integration event
        // AdmissionOpenedWithUnidentifiedPatient is published by the service layer.
        $admission = $this->makeOpenAdmission(false);

        $events = $admission->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionOpened::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AdmissionOpened::class, $event);
        self::assertFalse($event->isPatientIdentified);
    }

    public function testOpenSetsInitialLocationStatusToInWaitingRoom(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents(); // clear events

        self::assertSame(LocationStatusValue::InWaitingRoom, $admission->locationStatus()->value());
    }

    public function testCloseThrowsIfAlreadyClosed(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->close(ClosureReason::ConsultationCompleted, $now);
        $_ = $admission->pullDomainEvents();

        $this->expectException(AdmissionAlreadyClosedException::class);
        $admission->close(ClosureReason::ConsultationCompleted, $now);
    }

    public function testCloseRecordsAdmissionClosedEvent(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->close(ClosureReason::ReleasedToOwner, $now);

        $events = $admission->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionClosed::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AdmissionClosed::class, $event);
        self::assertSame(ClosureReason::ReleasedToOwner->value, $event->reason);
        self::assertSame(AdmissionStatus::Closed, $admission->status());
    }

    public function testReopenInErrorSucceedsOnAdministrativeError(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->close(ClosureReason::AdministrativeError, $now);
        $_ = $admission->pullDomainEvents();

        $admission->reopenInError($now);

        $events = $admission->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionReopenedInError::class, $events[0]);
        self::assertSame(AdmissionStatus::Active, $admission->status());
        self::assertNull($admission->closureReason());
        self::assertNull($admission->closedAt());
    }

    public function testReopenInErrorThrowsForOtherClosureReasons(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->close(ClosureReason::ConsultationCompleted, $now);
        $_ = $admission->pullDomainEvents();

        $this->expectException(AdmissionReopenNotAllowedException::class);
        $admission->reopenInError($now);
    }

    public function testReopenInErrorThrowsForAnimalDeceasedReason(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->close(ClosureReason::AnimalDeceased, $now);
        $_ = $admission->pullDomainEvents();

        $this->expectException(AdmissionReopenNotAllowedException::class);
        $admission->reopenInError($now);
    }

    public function testEscalateTriage(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->escalateTriage(TriageLevel::Emergency, $now);

        $events = $admission->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionTriageEscalated::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AdmissionTriageEscalated::class, $event);
        self::assertSame(TriageLevel::Standard->value, $event->oldLevel);
        self::assertSame(TriageLevel::Emergency->value, $event->newLevel);
        self::assertSame(TriageLevel::Emergency, $admission->triageLevel());
    }

    public function testEscalateTriageThrowsWhenSameLevelOrLower(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $this->expectException(TriageDeescalationNotAllowedException::class);
        $admission->escalateTriage(TriageLevel::Standard, $this->makeNow());
    }

    public function testDeescalateTriageOnlyAllowedFromVitalEmergencyToEmergency(): void
    {
        // First escalate to VitalEmergency
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->escalateTriage(TriageLevel::VitalEmergency, $now);
        $_ = $admission->pullDomainEvents();

        // Then de-escalate to Emergency — only allowed combination
        $admission->deescalateTriage(TriageLevel::Emergency, $now);

        $events = $admission->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionTriageDeescalated::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AdmissionTriageDeescalated::class, $event);
        self::assertSame(TriageLevel::VitalEmergency->value, $event->oldLevel);
        self::assertSame(TriageLevel::Emergency->value, $event->newLevel);
    }

    public function testDeescalateTriageThrowsFromEmergencyToStandard(): void
    {
        $admission = Admission::open(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentified: true,
            channel: IntakeChannel::Emergency,
            triage: TriageLevel::Emergency,
            presenter: null,
            now: $this->makeNow(),
        );
        $_ = $admission->pullDomainEvents();

        $this->expectException(TriageDeescalationNotAllowedException::class);
        $admission->deescalateTriage(TriageLevel::Standard, $this->makeNow());
    }

    public function testDeescalateTriageThrowsFromVitalEmergencyToStandard(): void
    {
        $admission = Admission::open(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentified: true,
            channel: IntakeChannel::Emergency,
            triage: TriageLevel::VitalEmergency,
            presenter: null,
            now: $this->makeNow(),
        );
        $_ = $admission->pullDomainEvents();

        $this->expectException(TriageDeescalationNotAllowedException::class);
        $admission->deescalateTriage(TriageLevel::Standard, $this->makeNow());
    }

    public function testUpdateLocationStatus(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now       = $this->makeNow();
        $newStatus = new LocationStatus(LocationStatusValue::InConsultationRoom, $now);
        $admission->updateLocationStatus($newStatus, $now);

        $events = $admission->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AdmissionLocationStatusUpdated::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(AdmissionLocationStatusUpdated::class, $event);
        self::assertSame(LocationStatusValue::InConsultationRoom->value, $event->newLocationValue);
        self::assertSame(LocationStatusValue::InConsultationRoom, $admission->locationStatus()->value());
    }

    public function testReconstitutionDoesNotRecordEvents(): void
    {
        $now       = $this->makeNow();
        $admission = Admission::reconstituteFromPersistence(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentifiedAtOpening: true,
            intakeChannel: IntakeChannel::WalkIn,
            triageLevel: TriageLevel::Standard,
            presenter: null,
            locationStatus: new LocationStatus(LocationStatusValue::InWaitingRoom, $now),
            appointmentId: null,
            physicalDescription: null,
            status: AdmissionStatus::Active,
            closureReason: null,
            triageNotes: null,
            version: 1,
            openedAt: $now,
            closedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertCount(0, $admission->recordedDomainEvents());
    }

    public function testUpdateTriageNotesUpdatesValue(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->updateTriageNotes('Patient in pain, priority elevated.', $now);

        self::assertSame('Patient in pain, priority elevated.', $admission->triageNotes());
    }

    public function testUpdateTriageNotesClearsValue(): void
    {
        $admission = $this->makeOpenAdmission();
        $_         = $admission->pullDomainEvents();

        $now = $this->makeNow();
        $admission->updateTriageNotes(null, $now);

        self::assertNull($admission->triageNotes());
    }

    private function makeId(): AdmissionId
    {
        return AdmissionId::fromString('01234567-89ab-cdef-0123-456789abcdef');
    }

    private function makeClinicId(): ClinicId
    {
        return ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
    }

    private function makeNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
    }

    private function makeOpenAdmission(bool $isIdentified = true): Admission
    {
        return Admission::open(
            id: $this->makeId(),
            clinicId: $this->makeClinicId(),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentified: $isIdentified,
            channel: IntakeChannel::WalkIn,
            triage: TriageLevel::Standard,
            presenter: null,
            now: $this->makeNow(),
        );
    }
}
