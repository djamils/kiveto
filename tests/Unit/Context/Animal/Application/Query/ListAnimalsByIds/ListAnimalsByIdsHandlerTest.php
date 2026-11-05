<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListAnimalsByIds;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Animal\Application\Query\ListAnimalsByIds\ListAnimalsByIds;
use App\Context\Animal\Application\Query\ListAnimalsByIds\ListAnimalsByIdsHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListAnimalsByIdsHandlerTest extends TestCase
{
    private const string CLINIC = '12345678-9abc-def0-1234-56789abcdef0';
    private const string REX    = '0192b000-0000-7000-8000-000000000001';
    private const string LUNA   = '0192b000-0000-7000-8000-000000000002';

    public function testNoIdReadsNothing(): void
    {
        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::never())->method('findByIds');

        self::assertSame([], new ListAnimalsByIdsHandler($repository)(new ListAnimalsByIds(self::CLINIC, [])));
    }

    public function testTheAnimalsAreReadOnceEachAndKeyedById(): void
    {
        $rex  = self::animal(self::REX, 'Rex');
        $luna = self::animal(self::LUNA, 'Luna');

        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC === $id->toString()),
                self::identicalTo([self::REX, self::LUNA]),
            )
            ->willReturn([$luna, $rex])
        ;

        $animals = new ListAnimalsByIdsHandler($repository)(new ListAnimalsByIds(self::CLINIC, [self::REX, self::LUNA, self::REX]));

        self::assertSame([self::LUNA => $luna, self::REX => $rex], $animals);
    }

    private static function animal(string $id, string $name): AnimalView
    {
        return new AnimalView(
            $id,
            self::CLINIC,
            $name,
            'dog',
            'male',
            'neutered',
            false,
            null,
            null,
            null,
            null,
            new IdentificationDto(null, null, null, 'icad', null, null),
            new LifeCycleDto('alive', null, null),
            new TransferDto('none', null, null),
            null,
            'active',
            [],
            'c',
            'u',
        );
    }
}
