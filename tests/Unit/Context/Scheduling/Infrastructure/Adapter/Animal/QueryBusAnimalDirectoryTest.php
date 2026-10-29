<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Scheduling\Infrastructure\Adapter\Animal\QueryBusAnimalDirectory;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusAnimalDirectoryTest extends TestCase
{
    public function testTheNameOfTheAnimalIsRead(): void
    {
        $view = new AnimalView(
            id: 'animal-1',
            clinicId: 'clinic-1',
            name: 'Milo',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'neutered',
            isMixedBreed: true,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: new IdentificationDto('250269812345678', null, null, 'ICAD', null, null),
            lifeCycle: new LifeCycleDto('alive', null, null),
            transfer: new TransferDto('none', null, null),
            auxiliaryContact: null,
            status: 'active',
            ownerships: [],
            createdAt: '2024-04-10',
            updatedAt: '2024-04-10',
        );

        self::assertSame('Milo', new QueryBusAnimalDirectory(new AnsweringQueryBus([GetAnimalById::class => $view]))->nameOf('clinic-1', 'animal-1'));
    }

    public function testAnAnimalThatCannotBeReadHasNoName(): void
    {
        $refused = new AnsweringQueryBus([GetAnimalById::class => static fn (): never => throw new \DomainException('Animal not found.')]);

        self::assertNull(new QueryBusAnimalDirectory($refused)->nameOf('clinic-1', 'animal-1'));
        self::assertNull(new QueryBusAnimalDirectory(new AnsweringQueryBus())->nameOf('clinic-1', 'animal-1'));
    }
}
