<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain;

use App\Scheduling\Domain\Event\WaitingRoomEntryCalled;
use App\Scheduling\Domain\Event\WaitingRoomEntryClosed;
use App\Scheduling\Domain\Event\WaitingRoomEntryCreatedFromAppointment;
use App\Scheduling\Domain\Event\WaitingRoomEntryLinkedToOwnerAndAnimal;
use App\Scheduling\Domain\Event\WaitingRoomEntryServiceStarted;
use App\Scheduling\Domain\Event\WaitingRoomEntryTriageUpdated;
use App\Scheduling\Domain\Event\WaitingRoomWalkInEntryCreated;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\UserId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryOrigin;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Scheduling\Domain\WaitingRoomEntry;
use PHPUnit\Framework\TestCase;

final class WaitingRoomEntryTest extends TestCase
{
    public function testCreateFromAppointment(): void
    {
        $entryId       = WaitingRoomEntryId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId      = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $appointmentId = AppointmentId::fromString('22222222-2222-2222-2222-222222222222');
        $ownerId       = OwnerId::fromString('33333333-3333-3333-3333-333333333333');
        $animalId      = AnimalId::fromString('44444444-4444-4444-4444-444444444444');
        $arrivedAt     = new \DateTimeImmutable('2026-02-01 09:00:00');

        $entry = WaitingRoomEntry::createFromAppointment(
            id: $entryId,
            clinicId: $clinicId,
            linkedAppointmentId: $appointmentId,
            ownerId: $ownerId,
            animalId: $animalId,
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: $arrivedAt,
        );

        self::assertTrue($entry->id()->equals($entryId));
        self::assertSame(WaitingRoomEntryOrigin::SCHEDULED, $entry->origin());
        self::assertSame(WaitingRoomEntryStatus::WAITING, $entry->status());
        self::assertTrue($entry->linkedAppointmentId()->equals($appointmentId));

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomEntryCreatedFromAppointment::class, $events[0]);
    }

    public function testCreateWalkInEntry(): void
    {
        $entryId   = WaitingRoomEntryId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $arrivedAt = new \DateTimeImmutable('2026-02-01 09:00:00');

        $entry = WaitingRoomEntry::createWalkIn(
            id: $entryId,
            clinicId: $clinicId,
            ownerId: null,
            animalId: null,
            foundAnimalDescription: 'Black cat, injured paw',
            arrivalMode: WaitingRoomArrivalMode::EMERGENCY,
            priority: 10,
            triageNotes: 'Urgent',
            arrivedAtUtc: $arrivedAt,
        );

        self::assertSame(WaitingRoomEntryOrigin::WALK_IN, $entry->origin());
        self::assertNull($entry->linkedAppointmentId());
        self::assertSame('Black cat, injured paw', $entry->foundAnimalDescription());
        self::assertSame(WaitingRoomArrivalMode::EMERGENCY, $entry->arrivalMode());

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomWalkInEntryCreated::class, $events[0]);
    }

    public function testUpdateTriage(): void
    {
        $entry        = $this->createSampleEntry();
        $pulledEvents = $entry->pullDomainEvents();
        unset($pulledEvents);

        $entry->updateTriage(5, 'Updated notes', WaitingRoomArrivalMode::EMERGENCY);

        self::assertSame(5, $entry->priority());
        self::assertSame('Updated notes', $entry->triageNotes());
        self::assertSame(WaitingRoomArrivalMode::EMERGENCY, $entry->arrivalMode());

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomEntryTriageUpdated::class, $events[0]);
    }

    public function testCallEntry(): void
    {
        $entry        = $this->createSampleEntry();
        $pulledEvents = $entry->pullDomainEvents();
        unset($pulledEvents);

        $calledAt     = new \DateTimeImmutable('2026-02-01 09:05:00');
        $calledByUser = UserId::fromString('55555555-5555-5555-5555-555555555555');
        $entry->call($calledAt, $calledByUser);

        self::assertSame(WaitingRoomEntryStatus::CALLED, $entry->status());
        self::assertSame($calledAt, $entry->calledAtUtc());

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomEntryCalled::class, $events[0]);
    }

    public function testStartService(): void
    {
        $entry        = $this->createSampleEntry();
        $pulledEvents = $entry->pullDomainEvents();
        unset($pulledEvents);

        $serviceStartedAt = new \DateTimeImmutable('2026-02-01 09:10:00');
        $startedByUser    = UserId::fromString('55555555-5555-5555-5555-555555555555');
        $entry->startService($serviceStartedAt, $startedByUser);

        self::assertSame(WaitingRoomEntryStatus::IN_SERVICE, $entry->status());
        self::assertSame($serviceStartedAt, $entry->serviceStartedAtUtc());

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomEntryServiceStarted::class, $events[0]);
    }

    public function testCloseEntry(): void
    {
        $entry            = $this->createSampleEntry();
        $serviceStartedAt = new \DateTimeImmutable('2026-02-01 09:10:00');
        $startedByUser    = UserId::fromString('55555555-5555-5555-5555-555555555555');
        $entry->startService($serviceStartedAt, $startedByUser);
        $pulledEvents = $entry->pullDomainEvents();
        unset($pulledEvents);

        $closedAt     = new \DateTimeImmutable('2026-02-01 09:30:00');
        $closedByUser = UserId::fromString('55555555-5555-5555-5555-555555555555');
        $entry->close($closedAt, $closedByUser);

        self::assertSame(WaitingRoomEntryStatus::CLOSED, $entry->status());
        self::assertSame($closedAt, $entry->closedAtUtc());

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomEntryClosed::class, $events[0]);
    }

    public function testLinkToOwnerAndAnimal(): void
    {
        $entry = WaitingRoomEntry::createWalkIn(
            id: WaitingRoomEntryId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            ownerId: null,
            animalId: null,
            foundAnimalDescription: 'Unknown dog',
            arrivalMode: WaitingRoomArrivalMode::EMERGENCY,
            priority: 10,
            triageNotes: null,
            arrivedAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
        );
        $pulledEvents = $entry->pullDomainEvents();
        unset($pulledEvents);

        $ownerId  = OwnerId::fromString('33333333-3333-3333-3333-333333333333');
        $animalId = AnimalId::fromString('44444444-4444-4444-4444-444444444444');
        $entry->linkToOwnerAndAnimal($ownerId, $animalId);

        self::assertTrue($entry->ownerId()->equals($ownerId));
        self::assertTrue($entry->animalId()->equals($animalId));

        $events = $entry->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaitingRoomEntryLinkedToOwnerAndAnimal::class, $events[0]);
    }

    public function testCannotUpdateTriageForClosedEntry(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot update triage for a closed entry.');

        $entry            = $this->createSampleEntry();
        $serviceStartedAt = new \DateTimeImmutable('2026-02-01 09:10:00');
        $entry->startService($serviceStartedAt, null);
        $closedAt = new \DateTimeImmutable('2026-02-01 09:30:00');
        $entry->close($closedAt, null);

        $entry->updateTriage(5, 'Should fail', WaitingRoomArrivalMode::STANDARD);
    }

    public function testCanTransitionWaitingToInServiceDirectly(): void
    {
        $entry        = $this->createSampleEntry();
        $pulledEvents = $entry->pullDomainEvents();
        unset($pulledEvents);

        $serviceStartedAt = new \DateTimeImmutable('2026-02-01 09:05:00');
        $entry->startService($serviceStartedAt, null);

        self::assertSame(WaitingRoomEntryStatus::IN_SERVICE, $entry->status());
    }

    public function testReconstituteCreatesInstanceWithoutEvents(): void
    {
        $entryId  = WaitingRoomEntryId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId = ClinicId::fromString('11111111-1111-1111-1111-111111111111');

        $entry = WaitingRoomEntry::reconstitute(
            id: $entryId,
            clinicId: $clinicId,
            origin: WaitingRoomEntryOrigin::WALK_IN,
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            linkedAppointmentId: null,
            ownerId: null,
            animalId: null,
            foundAnimalDescription: null,
            priority: 0,
            triageNotes: null,
            status: WaitingRoomEntryStatus::CLOSED,
            arrivedAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            calledAtUtc: null,
            serviceStartedAtUtc: null,
            closedAtUtc: new \DateTimeImmutable('2026-02-01 10:00:00'),
            calledByUserId: null,
            serviceStartedByUserId: null,
            closedByUserId: null,
        );

        self::assertTrue($entry->id()->equals($entryId));
        self::assertSame(WaitingRoomEntryStatus::CLOSED, $entry->status());

        $events = $entry->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    private function createSampleEntry(): WaitingRoomEntry
    {
        return WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            linkedAppointmentId: AppointmentId::fromString('22222222-2222-2222-2222-222222222222'),
            ownerId: OwnerId::fromString('33333333-3333-3333-3333-333333333333'),
            animalId: AnimalId::fromString('44444444-4444-4444-4444-444444444444'),
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
        );
    }
}
