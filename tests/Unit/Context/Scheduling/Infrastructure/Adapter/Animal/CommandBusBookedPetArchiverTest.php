<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Command\ArchiveAnimal\ArchiveAnimal;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Patient\Application\Query\HasPatientForAnimal\HasPatientForAnimal;
use App\Context\Scheduling\Infrastructure\Adapter\Animal\CommandBusBookedPetArchiver;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandBusBookedPetArchiverTest extends TestCase
{
    public function testAnActiveAnimalWithoutRecordIsArchived(): void
    {
        $queries  = new AnsweringQueryBus([GetAnimalById::class => self::animal('active'), HasPatientForAnimal::class => false]);
        $commands = self::commands();

        new CommandBusBookedPetArchiver($queries, $commands)->archiveUnlessRecorded('clinic-1', 'animal-1');

        self::assertCount(1, $commands->commands);
        $archive = $commands->commands[0];
        self::assertInstanceOf(ArchiveAnimal::class, $archive);
        self::assertSame(['clinic-1', 'animal-1'], [$archive->clinicId, $archive->animalId]);
        self::assertSame([GetAnimalById::class, HasPatientForAnimal::class], array_map(static fn (object $query): string => $query::class, $queries->asked));
    }

    /**
     * @param array<class-string, mixed> $answers
     */
    #[DataProvider('provideTheAnimalIsKeptCases')]
    public function testTheAnimalIsKept(array $answers): void
    {
        $commands = self::commands();

        new CommandBusBookedPetArchiver(new AnsweringQueryBus($answers), $commands)->archiveUnlessRecorded('clinic-1', 'animal-1');

        self::assertSame([], $commands->commands);
    }

    /**
     * @return iterable<string, array{array<class-string, mixed>}>
     */
    public static function provideTheAnimalIsKeptCases(): iterable
    {
        yield 'a clinical record' => [[GetAnimalById::class => self::animal('active'), HasPatientForAnimal::class => true]];
        yield 'already archived' => [[GetAnimalById::class => self::animal('archived'), HasPatientForAnimal::class => false]];
        yield 'unknown' => [[GetAnimalById::class => static fn (): never => throw new \DomainException('Animal not found.')]];
    }

    /**
     * @return CommandBusInterface&object{commands: list<object>}
     */
    private static function commands(): CommandBusInterface
    {
        return new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return null;
            }
        };
    }

    private static function animal(string $status): AnimalView
    {
        return new AnimalView(
            id: 'animal-1',
            clinicId: 'clinic-1',
            name: 'Pixel',
            species: 'cat',
            sex: 'unknown',
            reproductiveStatus: 'unknown',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: new IdentificationDto(null, null, null, 'none', null, null),
            lifeCycle: new LifeCycleDto('alive', null, null),
            transfer: new TransferDto('none', null, null),
            auxiliaryContact: null,
            status: $status,
            ownerships: [],
            createdAt: '2026-04-13',
            updatedAt: '2026-04-13',
        );
    }
}
