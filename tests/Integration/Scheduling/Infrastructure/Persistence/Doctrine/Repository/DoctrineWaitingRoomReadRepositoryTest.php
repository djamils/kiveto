<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use App\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineWaitingRoomReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID      = '12345678-9abc-def0-1234-56789abcdef0';
    private const string APPOINTMENT_ID = '01234567-89ab-cdef-0123-456789abcdef';

    private WaitingRoomReadRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = self::getContainer()->get(WaitingRoomReadRepositoryInterface::class);
        \assert($repo instanceof WaitingRoomReadRepositoryInterface);
        $this->repository = $repo;
    }

    public function testHasActiveEntryReturnsTrueForActiveStatuses(): void
    {
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withLinkedAppointmentId(self::APPOINTMENT_ID)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create()
        ;

        $result = $this->repository->hasActiveEntryForAppointment(
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
        );

        self::assertTrue($result);
    }

    public function testHasActiveEntryReturnsFalseForClosedStatus(): void
    {
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withLinkedAppointmentId(self::APPOINTMENT_ID)
            ->withStatus(WaitingRoomEntryStatus::CLOSED)
            ->create()
        ;

        $result = $this->repository->hasActiveEntryForAppointment(
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
        );

        self::assertFalse($result);
    }

    public function testHasActiveEntryReturnsFalseWhenNoEntryExists(): void
    {
        $result = $this->repository->hasActiveEntryForAppointment(
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
        );

        self::assertFalse($result);
    }

    public function testGetActiveStatusesReturnsTheExpectedSet(): void
    {
        $statuses = $this->repository->getActiveStatuses();

        self::assertSame(
            [
                WaitingRoomEntryStatus::WAITING,
                WaitingRoomEntryStatus::CALLED,
                WaitingRoomEntryStatus::IN_SERVICE,
            ],
            $statuses,
        );
    }
}
