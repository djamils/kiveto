<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListDirectoryMatches;

use App\Context\Animal\Application\Port\AnimalDirectoryReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\AnimalDirectoryFacts;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\ListAnimalDirectoryFacts;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\ListAnimalDirectoryFactsHandler;
use App\Context\Animal\Application\Query\ListDirectoryMatches\DirectoryMatches;
use App\Context\Animal\Application\Query\ListDirectoryMatches\ListDirectoryMatches;
use App\Context\Animal\Application\Query\ListDirectoryMatches\ListDirectoryMatchesHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class DirectoryQueriesTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000001';

    public function testMatchesForwardTheFacetsWithTheDueHorizon(): void
    {
        $matches    = new DirectoryMatches(['a1' => 'c1', 'a2' => null, 'a3' => 'c1']);
        $repository = $this->createMock(AnimalDirectoryReadRepositoryInterface::class);
        $repository->expects(self::once())->method('matches')->with(
            self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
            'Rex',
            ['dog'],
            true,
            false,
            new \DateTimeImmutable('2026-10-31 00:00:00'),
        )->willReturn($matches);

        $result = (new ListDirectoryMatchesHandler($repository))(new ListDirectoryMatches(
            clinicId: self::CLINIC_ID,
            searchTerm: '  Rex ',
            species: ['dog'],
            withMedicalAlert: true,
            asOf: new \DateTimeImmutable('2026-10-01 15:30:00'),
        ));

        self::assertSame($matches, $result);
        self::assertSame(['a1', 'a2', 'a3'], $result->animalIds());
        self::assertSame(['c1'], $result->ownerIds());
    }

    public function testABlankTermIsNoTerm(): void
    {
        $repository = $this->createMock(AnimalDirectoryReadRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('matches')->with(
            self::anything(),
            null,
        )->willReturn(new DirectoryMatches([]));

        $handler = new ListDirectoryMatchesHandler($repository);
        $handler(new ListDirectoryMatches(self::CLINIC_ID, '   '));
        $handler(new ListDirectoryMatches(self::CLINIC_ID));
    }

    public function testFactsAreAskedForTheListedAnimals(): void
    {
        $facts      = ['a1' => new AnimalDirectoryFacts([], [])];
        $repository = $this->createMock(AnimalDirectoryReadRepositoryInterface::class);
        $repository->expects(self::once())->method('facts')->with(
            self::anything(),
            ['a1'],
            new \DateTimeImmutable('2026-10-31 00:00:00'),
        )->willReturn($facts);

        $result = (new ListAnimalDirectoryFactsHandler($repository))(
            new ListAnimalDirectoryFacts(self::CLINIC_ID, ['a1'], new \DateTimeImmutable('2026-10-01')),
        );

        self::assertSame($facts, $result);
    }

    public function testNoAnimalSkipsTheRepository(): void
    {
        $repository = $this->createMock(AnimalDirectoryReadRepositoryInterface::class);
        $repository->expects(self::never())->method('facts');

        self::assertSame([], (new ListAnimalDirectoryFactsHandler($repository))(new ListAnimalDirectoryFacts(self::CLINIC_ID, [])));
    }

    public function testTheHorizonDefaultsToToday(): void
    {
        self::assertSame(
            (new \DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d H:i'),
            ListAnimalDirectoryFacts::dueBy(null)->format('Y-m-d H:i'),
        );
    }
}
