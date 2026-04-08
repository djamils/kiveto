<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\OwnerId;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Context\Scheduling\Domain\WaitingRoomEntry;
use App\Fixtures\Context\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineWaitingRoomEntryRepositoryTest extends KernelTestCase
{
    use Factories;

    private WaitingRoomEntryRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = self::getContainer()->get(WaitingRoomEntryRepositoryInterface::class);
        \assert($repo instanceof WaitingRoomEntryRepositoryInterface);
        $this->repository = $repo;
    }

    public function testFindByIdReturnsNullWhenEntryDoesNotExist(): void
    {
        $result = $this->repository->findById(
            WaitingRoomEntryId::fromString('00000000-0000-0000-0000-000000000000'),
        );

        self::assertNull($result);
    }

    public function testSaveAndFindByIdRoundTrip(): void
    {
        $entry = WaitingRoomEntry::createWalkIn(
            id: WaitingRoomEntryId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            ownerId: null,
            animalId: null,
            foundAnimalDescription: 'Stray dog',
            arrivalMode: WaitingRoomArrivalMode::EMERGENCY,
            priority: 5,
            triageNotes: 'Bleeding paw',
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 11:00:00'),
        );

        $this->repository->save($entry);

        $loaded = $this->repository->findById($entry->id());
        self::assertNotNull($loaded);
        self::assertTrue($loaded->id()->equals($entry->id()));
        self::assertSame(WaitingRoomEntryStatus::WAITING, $loaded->status());
        self::assertSame(5, $loaded->priority());
    }

    public function testSaveUpdatesExistingEntry(): void
    {
        $entry = WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            linkedAppointmentId: AppointmentId::fromString('cccccccc-cccc-cccc-cccc-cccccccccccc'),
            ownerId: OwnerId::fromString('dddddddd-dddd-dddd-dddd-dddddddddddd'),
            animalId: null,
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 08:55:00'),
        );
        $this->repository->save($entry);

        $entry->call(new \DateTimeImmutable('2026-04-10 09:00:00'), null);
        $this->repository->save($entry);

        $loaded = $this->repository->findById($entry->id());
        self::assertNotNull($loaded);
        self::assertSame(WaitingRoomEntryStatus::CALLED, $loaded->status());
    }

    public function testToEntityHandlesAlreadyClosedNewEntry(): void
    {
        // Build an entry that has already gone through call/startService/close
        // BEFORE its first save — this exercises the not-null branches of every
        // user-id setter inside WaitingRoomEntryMapper::toEntity (the "new entity"
        // path), which the lifecycle round trip below could not reach because it
        // saves once per transition through updateEntity.
        $userId = \App\Context\Scheduling\Domain\ValueObject\UserId::fromString(
            'ffffffff-ffff-ffff-ffff-ffffffffffff',
        );

        $entry = WaitingRoomEntry::createWalkIn(
            id: WaitingRoomEntryId::fromString('99999999-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('99999999-2222-2222-2222-222222222222'),
            ownerId: null,
            animalId: null,
            foundAnimalDescription: 'Stray',
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            triageNotes: null,
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 08:55:00'),
        );
        $entry->call(new \DateTimeImmutable('2026-04-10 09:00:00'), $userId);
        $entry->startService(new \DateTimeImmutable('2026-04-10 09:05:00'), $userId);
        $entry->close(new \DateTimeImmutable('2026-04-10 09:30:00'), $userId);

        $this->repository->save($entry);

        $loaded = $this->repository->findById($entry->id());
        self::assertNotNull($loaded);
        self::assertNotNull($loaded->serviceStartedByUserId());
    }

    public function testRoundTripExercisesAllNullableMapperBranches(): void
    {
        // Drive the entry through the full lifecycle, saving at every step so
        // both toEntity (first save) AND updateEntity (subsequent saves) are
        // exercised on every nullable column.
        $entry = WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            linkedAppointmentId: AppointmentId::fromString('cccccccc-cccc-cccc-cccc-cccccccccccc'),
            ownerId: OwnerId::fromString('dddddddd-dddd-dddd-dddd-dddddddddddd'),
            animalId: \App\Context\Scheduling\Domain\ValueObject\AnimalId::fromString('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'),
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 08:55:00'),
        );

        // Save once to create the entity (toEntity branch).
        $this->repository->save($entry);

        $userId = \App\Context\Scheduling\Domain\ValueObject\UserId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff');

        // Save after every transition so updateEntity is exercised on every nullable column.
        $entry->call(new \DateTimeImmutable('2026-04-10 09:00:00'), $userId);
        $this->repository->save($entry);

        $entry->startService(new \DateTimeImmutable('2026-04-10 09:05:00'), $userId);
        $this->repository->save($entry);

        $entry->close(new \DateTimeImmutable('2026-04-10 09:30:00'), $userId);
        $this->repository->save($entry);

        $loaded = $this->repository->findById($entry->id());
        self::assertNotNull($loaded);
        self::assertSame(WaitingRoomEntryStatus::CLOSED, $loaded->status());
        self::assertNotNull($loaded->calledByUserId());
        self::assertNotNull($loaded->serviceStartedByUserId());
        self::assertNotNull($loaded->closedByUserId());
    }

    public function testFindByIdRehydratesFromExistingFoundryEntity(): void
    {
        $id       = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        WaitingRoomEntryEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->withOwnerId('22222222-2222-2222-2222-222222222222')
            ->create()
        ;

        $loaded = $this->repository->findById(WaitingRoomEntryId::fromString($id));

        self::assertNotNull($loaded);
        self::assertSame($id, $loaded->id()->toString());
    }
}
