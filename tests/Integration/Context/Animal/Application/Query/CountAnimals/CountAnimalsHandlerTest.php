<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Application\Query\CountAnimals;

use App\Context\Animal\Application\Query\CountAnimals\CountAnimals;
use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class CountAnimalsHandlerTest extends KernelTestCase
{
    use Factories;

    public function testCountFilteredBySearchTerm(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'name' => 'Rex']);
        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'name' => 'Rexy']);
        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'name' => 'Whiskers']);

        self::assertSame(2, $this->ask(new CountAnimals(clinicId: $clinicId, searchTerm: 'Rex')));
    }

    public function testCountIsClinicScoped(): void
    {
        $clinicId      = '12345678-9abc-def0-1234-56789abcdef0';
        $otherClinicId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        AnimalEntityFactory::createOne(['clinicId' => Uuid::fromString($clinicId)]);
        AnimalEntityFactory::createOne(['clinicId' => Uuid::fromString($clinicId)]);
        AnimalEntityFactory::createOne(['clinicId' => Uuid::fromString($otherClinicId)]);

        self::assertSame(2, $this->ask(new CountAnimals(clinicId: $clinicId)));
        self::assertSame(1, $this->ask(new CountAnimals(clinicId: $otherClinicId)));
    }

    public function testCountFilteredBySpecies(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'species' => Species::DOG]);
        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'species' => Species::DOG]);
        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'species' => Species::CAT]);

        self::assertSame(2, $this->ask(new CountAnimals(clinicId: $clinicId, species: 'dog')));
    }

    public function testCountFilteredByStatus(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'status' => AnimalStatus::ACTIVE]);
        AnimalEntityFactory::createOne(['clinicId' => $clinicUuid, 'status' => AnimalStatus::ARCHIVED]);

        self::assertSame(1, $this->ask(new CountAnimals(clinicId: $clinicId, status: 'active')));
    }

    public function testCountReturnsZeroWhenNoMatch(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        self::assertSame(0, $this->ask(new CountAnimals(clinicId: $clinicId)));
    }

    private function ask(CountAnimals $query): int
    {
        $bus = static::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        $result = $bus->ask($query);
        \assert(\is_int($result));

        return $result;
    }
}
