<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\CountAnimals;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\CountAnimals\CountAnimals;
use App\Context\Animal\Application\Query\CountAnimals\CountAnimalsHandler;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class CountAnimalsHandlerTest extends TestCase
{
    public function testHandleReturnsCountFromRepository(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        $query = new CountAnimals(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: 'Rex',
            status: 'active',
            species: 'dog',
            lifeStatus: 'alive',
            ownerClientId: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        );

        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('countBy')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(
                    static fn (SearchAnimalsCriteria $criteria): bool => 'Rex' === $criteria->searchTerm
                        && 'active' === $criteria->status
                        && 'dog' === $criteria->species
                        && 'alive' === $criteria->lifeStatus
                        && 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' === $criteria->ownerClientId,
                ),
            )
            ->willReturn(4)
        ;

        $handler = new CountAnimalsHandler($readRepository);

        self::assertSame(4, $handler($query));
    }

    public function testHandleWithNullFiltersDelegatesToRepository(): void
    {
        $readRepository = $this->createMock(AnimalReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('countBy')
            ->willReturn(0)
        ;

        $handler = new CountAnimalsHandler($readRepository);
        $query   = new CountAnimals(clinicId: '12345678-9abc-def0-1234-56789abcdef0');

        self::assertSame(0, $handler($query));
    }
}
