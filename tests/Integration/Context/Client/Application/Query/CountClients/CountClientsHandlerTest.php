<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Application\Query\CountClients;

use App\Context\Client\Application\Query\CountClients\CountClients;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class CountClientsHandlerTest extends KernelTestCase
{
    use Factories;

    public function testCountFilteredBySearchTerm(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        ClientEntityFactory::createOne([
            'clinicId'  => $clinicUuid,
            'firstName' => 'Rex',
            'lastName'  => 'Doe',
        ]);
        ClientEntityFactory::createOne([
            'clinicId'  => $clinicUuid,
            'firstName' => 'Rex',
            'lastName'  => 'Smith',
        ]);
        ClientEntityFactory::createOne([
            'clinicId'  => $clinicUuid,
            'firstName' => 'Max',
            'lastName'  => 'Power',
        ]);

        self::assertSame(2, $this->ask(new CountClients(clinicId: $clinicId, searchTerm: 'rex')));
    }

    public function testCountIsClinicScoped(): void
    {
        $clinicId      = '12345678-9abc-def0-1234-56789abcdef0';
        $otherClinicId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        ClientEntityFactory::createOne(['clinicId' => Uuid::fromString($clinicId)]);
        ClientEntityFactory::createOne(['clinicId' => Uuid::fromString($clinicId)]);
        ClientEntityFactory::createOne(['clinicId' => Uuid::fromString($otherClinicId)]);

        self::assertSame(2, $this->ask(new CountClients(clinicId: $clinicId)));
        self::assertSame(1, $this->ask(new CountClients(clinicId: $otherClinicId)));
    }

    public function testCountFilteredByStatus(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        ClientEntityFactory::createOne(['clinicId' => $clinicUuid, 'status' => ClientStatus::ACTIVE]);
        ClientEntityFactory::createOne(['clinicId' => $clinicUuid, 'status' => ClientStatus::ACTIVE]);
        ClientEntityFactory::createOne(['clinicId' => $clinicUuid, 'status' => ClientStatus::ARCHIVED]);

        self::assertSame(2, $this->ask(new CountClients(clinicId: $clinicId, status: 'active')));
        self::assertSame(1, $this->ask(new CountClients(clinicId: $clinicId, status: 'archived')));
    }

    public function testCountReturnsZeroWhenNoMatch(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        self::assertSame(0, $this->ask(new CountClients(clinicId: $clinicId)));
    }

    private function ask(CountClients $query): int
    {
        $bus = static::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        $result = $bus->ask($query);
        \assert(\is_int($result));

        return $result;
    }
}
