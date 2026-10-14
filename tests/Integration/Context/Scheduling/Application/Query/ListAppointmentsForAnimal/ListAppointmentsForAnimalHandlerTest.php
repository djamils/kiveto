<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\ListAppointmentsForAnimal;

use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\ListAppointmentsForAnimal;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\ListAppointmentsForAnimalHandler;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class ListAppointmentsForAnimalHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '12345678-9abc-def0-1234-56789abcdef0';
    private const string ANIMAL_ID       = '0192b000-0000-7000-8000-000000000001';
    private const string PRACTITIONER_ID = '44444444-4444-4444-8444-444444444444';

    public function testListsTheAppointmentsOfTheAnimalNewestFirst(): void
    {
        $this->appointment(self::CLINIC_ID, self::ANIMAL_ID, '2026-04-10 09:00:00', AppointmentStatus::COMPLETED, null);
        $this->appointment(self::CLINIC_ID, self::ANIMAL_ID, '2026-09-19 07:30:00', AppointmentStatus::PLANNED, 'Contrôle rénal', 'Venir à jeun');
        // Another animal and another clinic must not leak into the list.
        $this->appointment(self::CLINIC_ID, '0192b000-0000-7000-8000-000000000099', '2026-09-20 09:00:00', AppointmentStatus::PLANNED, null);
        $this->appointment('88888888-8888-7888-8888-888888888888', self::ANIMAL_ID, '2026-09-21 09:00:00', AppointmentStatus::PLANNED, null);

        $items = $this->handler()(new ListAppointmentsForAnimal(self::CLINIC_ID, self::ANIMAL_ID));

        self::assertCount(2, $items);

        self::assertSame('2026-09-19 07:30:00', $items[0]->startsAtUtc);
        self::assertSame('PLANNED', $items[0]->status);
        self::assertSame('Contrôle rénal', $items[0]->reason);
        self::assertSame('Venir à jeun', $items[0]->notes);
        self::assertSame(45, $items[0]->durationMinutes);
        self::assertSame(self::PRACTITIONER_ID, $items[0]->practitionerUserId);
        self::assertTrue(Uuid::isValid($items[0]->id));

        self::assertSame('2026-04-10 09:00:00', $items[1]->startsAtUtc);
        self::assertSame('COMPLETED', $items[1]->status);
        self::assertNull($items[1]->reason);
        self::assertNull($items[1]->notes);
    }

    public function testHonoursTheLimit(): void
    {
        $this->appointment(self::CLINIC_ID, self::ANIMAL_ID, '2026-04-10 09:00:00', AppointmentStatus::COMPLETED, null);
        $this->appointment(self::CLINIC_ID, self::ANIMAL_ID, '2026-09-19 07:30:00', AppointmentStatus::PLANNED, null);

        $items = $this->handler()(new ListAppointmentsForAnimal(self::CLINIC_ID, self::ANIMAL_ID, limit: 1));

        self::assertCount(1, $items);
        self::assertSame('2026-09-19 07:30:00', $items[0]->startsAtUtc);
    }

    public function testReturnsAnEmptyListForAnAnimalWithoutAppointment(): void
    {
        self::assertSame([], $this->handler()(new ListAppointmentsForAnimal(self::CLINIC_ID, self::ANIMAL_ID)));
    }

    private function appointment(
        string $clinicId,
        string $animalId,
        string $startsAtUtc,
        AppointmentStatus $status,
        ?string $reason,
        ?string $notes = null,
    ): void {
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId(self::PRACTITIONER_ID)
            ->startingAt(new \DateTimeImmutable($startsAtUtc), 45)
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($animalId, $reason, $notes): void {
                $entity->setAnimalId(Uuid::fromString($animalId));
                $entity->setReason($reason);
                $entity->setNotes($notes);
            })
            ->create()
        ;
    }

    private function handler(): ListAppointmentsForAnimalHandler
    {
        $handler = self::getContainer()->get(ListAppointmentsForAnimalHandler::class);
        \assert($handler instanceof ListAppointmentsForAnimalHandler);

        return $handler;
    }
}
