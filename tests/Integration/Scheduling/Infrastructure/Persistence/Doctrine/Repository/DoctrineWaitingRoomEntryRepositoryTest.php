<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Scheduling\Domain\WaitingRoomEntry;
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

    public function testRoundTripExercisesAllNullableMapperBranches(): void
    {
        // Drive the entry through the full lifecycle so every nullable field
        // (calledAt/UserId, serviceStartedAt/UserId, closedAt/UserId, owner/animal)
        // ends up populated, exercising every branch in WaitingRoomEntryMapper::toDomain.
        $entry = WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            linkedAppointmentId: AppointmentId::fromString('cccccccc-cccc-cccc-cccc-cccccccccccc'),
            ownerId: OwnerId::fromString('dddddddd-dddd-dddd-dddd-dddddddddddd'),
            animalId: \App\Scheduling\Domain\ValueObject\AnimalId::fromString('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'),
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
            arrivedAtUtc: new \DateTimeImmutable('2026-04-10 08:55:00'),
        );

        $userId = \App\Scheduling\Domain\ValueObject\UserId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $entry->call(new \DateTimeImmutable('2026-04-10 09:00:00'), $userId);
        $entry->startService(new \DateTimeImmutable('2026-04-10 09:05:00'), $userId);
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
