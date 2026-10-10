<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\ListAppointmentsForOwner;

use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\ListAppointmentsForOwner;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\ListAppointmentsForOwnerHandler;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class ListAppointmentsForOwnerHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OWNER_ID        = '01234567-89ab-cdef-0123-456789abcdef';
    private const string PRACTITIONER_ID = '44444444-4444-4444-8444-444444444444';
    private const string ANIMAL_ID       = '0192b000-0000-7000-8000-000000000001';

    public function testListsTheAppointmentsOfTheOwnerNewestFirstWithTheAnimalName(): void
    {
        AnimalEntityFactory::createOne([
            'id'       => Uuid::fromString(self::ANIMAL_ID),
            'clinicId' => Uuid::fromString(self::CLINIC_ID),
            'name'     => 'Roxy',
        ]);

        $this->appointment(self::CLINIC_ID, self::OWNER_ID, '2026-04-10 09:00:00', AppointmentStatus::COMPLETED, null, null);
        $this->appointment(self::CLINIC_ID, self::OWNER_ID, '2026-09-19 07:30:00', AppointmentStatus::PLANNED, self::ANIMAL_ID, 'Contrôle rénal');
        // Another owner and another clinic must not leak into the list.
        $this->appointment(self::CLINIC_ID, '99999999-9999-7999-8999-999999999999', '2026-09-20 09:00:00', AppointmentStatus::PLANNED, null, null);
        $this->appointment('88888888-8888-7888-8888-888888888888', self::OWNER_ID, '2026-09-21 09:00:00', AppointmentStatus::PLANNED, null, null);

        $items = $this->handler()(new ListAppointmentsForOwner(self::CLINIC_ID, self::OWNER_ID));

        self::assertCount(2, $items);

        self::assertSame('2026-09-19 07:30:00', $items[0]->startsAtUtc);
        self::assertSame('PLANNED', $items[0]->status);
        self::assertSame('Contrôle rénal', $items[0]->reason);
        self::assertSame(45, $items[0]->durationMinutes);
        self::assertSame(self::PRACTITIONER_ID, $items[0]->practitionerUserId);
        self::assertSame(self::ANIMAL_ID, $items[0]->animalId);
        self::assertSame('Roxy', $items[0]->animalLabel);
        self::assertTrue(Uuid::isValid($items[0]->id));

        self::assertSame('2026-04-10 09:00:00', $items[1]->startsAtUtc);
        self::assertSame('COMPLETED', $items[1]->status);
        self::assertNull($items[1]->reason);
        self::assertNull($items[1]->animalId);
        self::assertNull($items[1]->animalLabel);
    }

    public function testHonoursTheLimit(): void
    {
        $this->appointment(self::CLINIC_ID, self::OWNER_ID, '2026-04-10 09:00:00', AppointmentStatus::COMPLETED, null, null);
        $this->appointment(self::CLINIC_ID, self::OWNER_ID, '2026-09-19 07:30:00', AppointmentStatus::PLANNED, null, null);

        $items = $this->handler()(new ListAppointmentsForOwner(self::CLINIC_ID, self::OWNER_ID, limit: 1));

        self::assertCount(1, $items);
        self::assertSame('2026-09-19 07:30:00', $items[0]->startsAtUtc);
    }

    public function testReturnsAnEmptyListForAnOwnerWithoutAppointment(): void
    {
        self::assertSame([], $this->handler()(new ListAppointmentsForOwner(self::CLINIC_ID, self::OWNER_ID)));
    }

    private function appointment(
        string $clinicId,
        string $ownerId,
        string $startsAtUtc,
        AppointmentStatus $status,
        ?string $animalId,
        ?string $reason,
    ): void {
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId(self::PRACTITIONER_ID)
            ->startingAt(new \DateTimeImmutable($startsAtUtc), 45)
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($ownerId, $animalId, $reason): void {
                $entity->setOwnerId(Uuid::fromString($ownerId));
                $entity->setAnimalId(null === $animalId ? null : Uuid::fromString($animalId));
                $entity->setReason($reason);
            })
            ->create()
        ;
    }

    private function handler(): ListAppointmentsForOwnerHandler
    {
        $handler = self::getContainer()->get(ListAppointmentsForOwnerHandler::class);
        \assert($handler instanceof ListAppointmentsForOwnerHandler);

        return $handler;
    }
}
