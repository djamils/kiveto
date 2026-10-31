<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals;

use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\GetNextAppointmentForAnimals;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\GetNextAppointmentForAnimalsHandler;
use App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals\NextAppointmentItem;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class GetNextAppointmentForAnimalsHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER_CLINIC_ID = '88888888-8888-7888-8888-888888888888';
    private const string ANIMAL_ID       = '0192b000-0000-7000-8000-000000000001';
    private const string OTHER_ANIMAL_ID = '0192b000-0000-7000-8000-000000000002';
    private const string STRANGER_ID     = '0192b000-0000-7000-8000-000000000099';
    private const string PRACTITIONER_ID = '44444444-4444-4444-8444-444444444444';
    private const string NOW             = '2026-10-31 09:00:00';

    public function testReturnsTheEarliestPlannedAppointmentOfTheAnimals(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000003', self::ANIMAL_ID, '2026-11-12 08:00:00', reason: 'Bilan sanguin');
        $this->appointment('0192c000-0000-7000-8000-000000000002', self::OTHER_ANIMAL_ID, '2026-11-05 14:30:00', reason: 'Rappel vaccinal', notes: 'Venir à jeun');
        // An animal outside the list and another clinic must not leak in.
        $this->appointment('0192c000-0000-7000-8000-000000000004', self::STRANGER_ID, '2026-11-01 08:00:00');
        $this->appointment('0192c000-0000-7000-8000-000000000005', self::ANIMAL_ID, '2026-11-01 08:00:00', clinicId: self::OTHER_CLINIC_ID);

        $item = $this->ask([self::ANIMAL_ID, self::OTHER_ANIMAL_ID]);

        self::assertNotNull($item);
        self::assertSame('0192c000-0000-7000-8000-000000000002', $item->id);
        self::assertSame(self::OTHER_ANIMAL_ID, $item->animalId);
        self::assertSame('2026-11-05T14:30:00+00:00', $item->startsAtUtc->format(\DATE_ATOM));
        self::assertSame(self::PRACTITIONER_ID, $item->practitionerUserId);
        self::assertSame('vaccin', $item->category);
    }

    public function testAnAppointmentStartingExactlyNowIsTheNextOne(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000001', self::ANIMAL_ID, self::NOW);
        $this->appointment('0192c000-0000-7000-8000-000000000002', self::ANIMAL_ID, '2026-10-31 09:15:00');

        $item = $this->ask([self::ANIMAL_ID]);

        self::assertNotNull($item);
        self::assertSame('0192c000-0000-7000-8000-000000000001', $item->id);
        self::assertSame('consult', $item->category);
    }

    public function testNowGivenInAnotherTimezoneIsComparedInUtc(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000001', self::ANIMAL_ID, '2026-10-31 09:30:00');

        // 10:00 in Paris (UTC+1 after the October switch) is 09:00 UTC.
        $item = $this->handler()(new GetNextAppointmentForAnimals(
            self::CLINIC_ID,
            [self::ANIMAL_ID],
            new \DateTimeImmutable('2026-10-31 10:00:00', new \DateTimeZone('Europe/Paris')),
        ));

        self::assertNotNull($item);
        self::assertSame('0192c000-0000-7000-8000-000000000001', $item->id);
    }

    public function testIgnoresPastAppointments(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000001', self::ANIMAL_ID, '2026-10-31 08:59:59');

        self::assertNull($this->ask([self::ANIMAL_ID]));
    }

    public function testIgnoresAppointmentsThatAreNotPlanned(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000001', self::ANIMAL_ID, '2026-11-01 08:00:00', AppointmentStatus::CANCELLED);
        $this->appointment('0192c000-0000-7000-8000-000000000002', self::ANIMAL_ID, '2026-11-02 08:00:00', AppointmentStatus::NO_SHOW);
        $this->appointment('0192c000-0000-7000-8000-000000000003', self::ANIMAL_ID, '2026-11-03 08:00:00', AppointmentStatus::COMPLETED);

        self::assertNull($this->ask([self::ANIMAL_ID]));
    }

    public function testIgnoresAppointmentsWithoutAnimal(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000001', null, '2026-11-01 08:00:00');

        self::assertNull($this->ask([self::ANIMAL_ID]));
    }

    public function testATieOnTheStartIsBrokenById(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000009', self::ANIMAL_ID, '2026-11-01 08:00:00', reason: 'Chirurgie');
        $this->appointment('0192c000-0000-7000-8000-000000000003', self::OTHER_ANIMAL_ID, '2026-11-01 08:00:00', reason: 'Contrôle');

        $item = $this->ask([self::ANIMAL_ID, self::OTHER_ANIMAL_ID]);

        self::assertNotNull($item);
        self::assertSame('0192c000-0000-7000-8000-000000000003', $item->id);
        self::assertSame('suivi', $item->category);
    }

    public function testAnEmptyAnimalListHasNoNextAppointment(): void
    {
        $this->appointment('0192c000-0000-7000-8000-000000000001', self::ANIMAL_ID, '2026-11-01 08:00:00');

        self::assertNull($this->ask([]));
    }

    /**
     * @param list<string> $animalIds
     */
    private function ask(array $animalIds): ?NextAppointmentItem
    {
        return $this->handler()(new GetNextAppointmentForAnimals(
            self::CLINIC_ID,
            $animalIds,
            new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')),
        ));
    }

    private function appointment(
        string $id,
        ?string $animalId,
        string $startsAtUtc,
        AppointmentStatus $status = AppointmentStatus::PLANNED,
        ?string $reason = null,
        ?string $notes = null,
        string $clinicId = self::CLINIC_ID,
    ): void {
        AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withPractitionerUserId(self::PRACTITIONER_ID)
            ->startingAt(new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC')), 30)
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($animalId, $reason, $notes): void {
                $entity->setAnimalId(null !== $animalId ? Uuid::fromString($animalId) : null);
                $entity->setReason($reason);
                $entity->setNotes($notes);
            })
            ->create()
        ;
    }

    private function handler(): GetNextAppointmentForAnimalsHandler
    {
        $handler = self::getContainer()->get(GetNextAppointmentForAnimalsHandler::class);
        \assert($handler instanceof GetNextAppointmentForAnimalsHandler);

        return $handler;
    }
}
