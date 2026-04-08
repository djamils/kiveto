<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\Scheduling\Factory\AppointmentEntityFactory;
use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineAppointmentRepositoryTest extends KernelTestCase
{
    use Factories;

    private AppointmentRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($repo instanceof AppointmentRepositoryInterface);
        $this->repository = $repo;
    }

    public function testFindByIdReturnsNullWhenAppointmentDoesNotExist(): void
    {
        $result = $this->repository->findById(
            AppointmentId::fromString('00000000-0000-0000-0000-000000000000'),
        );

        self::assertNull($result);
    }

    public function testSaveAndFindByIdRoundTrip(): void
    {
        $appointment = $this->makeAppointment();

        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertNotNull($loaded);
        self::assertTrue($loaded->id()->equals($appointment->id()));
        self::assertSame(AppointmentStatus::PLANNED, $loaded->status());
    }

    public function testSaveUpdatesExistingAppointment(): void
    {
        $appointment = $this->makeAppointment();
        $this->repository->save($appointment);

        $appointment->cancel();
        $this->repository->save($appointment);

        $loaded = $this->repository->findById($appointment->id());
        self::assertNotNull($loaded);
        self::assertSame(AppointmentStatus::CANCELLED, $loaded->status());
    }

    public function testFindByIdRehydratesFromExistingFoundryEntity(): void
    {
        $id       = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withOwnerId('22222222-2222-2222-2222-222222222222')
            ->withAnimalId('33333333-3333-3333-3333-333333333333')
            ->withPractitionerUserId('44444444-4444-4444-4444-444444444444')
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 30)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create()
        ;

        $loaded = $this->repository->findById(AppointmentId::fromString($id));

        self::assertNotNull($loaded);
        self::assertSame($id, $loaded->id()->toString());
        self::assertSame($clinicId, $loaded->clinicId()->toString());
        $owner = $loaded->ownerId();
        self::assertNotNull($owner);
        self::assertSame('22222222-2222-2222-2222-222222222222', $owner->toString());
    }

    private function makeAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
            clinicId: ClinicId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
            ownerId: OwnerId::fromString('cccccccc-cccc-cccc-cccc-cccccccccccc'),
            animalId: AnimalId::fromString('dddddddd-dddd-dddd-dddd-dddddddddddd'),
            practitionerAssignee: new PractitionerAssignee(
                UserId::fromString('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'),
            ),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: 'Vaccination',
            notes: 'Anxious patient',
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
