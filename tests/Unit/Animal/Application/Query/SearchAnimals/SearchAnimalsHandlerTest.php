<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Query\SearchAnimals;

use App\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Animal\Application\Query\SearchAnimals\SearchAnimalsHandler;
use App\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class SearchAnimalsHandlerTest extends TestCase
{
    public function testHandleReturnsSearchResults(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        $items = [
            new AnimalListItemView(
                id: '01234567-89ab-cdef-0123-456789abcdef',
                name: 'Rex',
                species: 'dog',
                sex: 'male',
                breedName: 'Labrador',
                birthDate: '2020-01-01',
                color: 'Golden',
                microchipNumber: '123456789',
                status: 'ACTIVE',
                lifeStatus: 'alive',
                primaryOwnerClientId: 'client-123',
                createdAt: '2024-01-01T10:00:00+00:00'
            ),
        ];

        $expectedResult = ['items' => $items, 'total' => 1];

        $query = new SearchAnimals(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: 'Rex',
            status: 'ACTIVE',
            species: 'dog',
            lifeStatus: 'alive',
            ownerClientId: 'client-123',
            page: 1,
            limit: 20
        );

        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);

        $readRepository->expects(self::once())
            ->method('search')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(function (SearchAnimalsCriteria $c) use ($query): bool {
                    return $c->searchTerm === $query->searchTerm
                        && $c->status === $query->status
                        && $c->species === $query->species
                        && $c->lifeStatus === $query->lifeStatus
                        && $c->ownerClientId === $query->ownerClientId
                        && $c->page === $query->page
                        && $c->limit === $query->limit;
                })
            )
            ->willReturn($expectedResult)
        ;

        $handler = new SearchAnimalsHandler($readRepository);
        $result  = $handler($query);

        self::assertSame($expectedResult, $result);
    }
}
