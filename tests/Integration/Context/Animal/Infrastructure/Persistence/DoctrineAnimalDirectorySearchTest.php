<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Covers what the Répertoire added to the animal search: the multi-value
 * filters, the identifier restriction the caller resolves from free text, and
 * the sortable columns.
 */
final class DoctrineAnimalDirectorySearchTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000000e1';
    private const string OTHER_CLINIC_ID = '01960000-0000-7000-8000-0000000000e2';

    private const string BUDDY   = '01960000-0000-7000-8000-0000000000f1';
    private const string CHARLIE = '01960000-0000-7000-8000-0000000000f2';
    private const string MOKA    = '01960000-0000-7000-8000-0000000000f3';

    private AnimalReadRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(AnimalReadRepositoryInterface::class);
        \assert($repository instanceof AnimalReadRepositoryInterface);
        $this->repository = $repository;
    }

    public function testEveryAnimalOfTheClinicIsListedByName(): void
    {
        $this->seed();

        self::assertSame(
            [self::BUDDY, self::CHARLIE, self::MOKA],
            $this->idsFor(new SearchAnimalsCriteria()),
        );
    }

    public function testAnotherClinicIsNeverVisible(): void
    {
        $this->seed();

        AnimalEntityFactory::new()->withClinicId(self::OTHER_CLINIC_ID)->create(['name' => 'Ailleurs']);

        self::assertCount(3, $this->idsFor(new SearchAnimalsCriteria()));
    }

    public function testSpeciesFilter(): void
    {
        $this->seed();

        self::assertSame([self::MOKA], $this->idsFor(new SearchAnimalsCriteria(speciesList: ['cat'])));
    }

    public function testSeveralSpeciesAtOnce(): void
    {
        $this->seed();

        self::assertSame(
            [self::BUDDY, self::CHARLIE, self::MOKA],
            $this->idsFor(new SearchAnimalsCriteria(speciesList: ['dog', 'cat'])),
        );
    }

    public function testLifeStatusFilter(): void
    {
        $this->seed();

        self::assertSame([self::CHARLIE], $this->idsFor(new SearchAnimalsCriteria(lifeStatuses: ['deceased'])));
    }

    public function testTheIdentifierRestrictionNarrowsTheResult(): void
    {
        $this->seed();

        self::assertSame(
            [self::MOKA],
            $this->idsFor(new SearchAnimalsCriteria(restrictToIds: [self::MOKA])),
        );
    }

    public function testAnEmptyIdentifierRestrictionShortCircuits(): void
    {
        $this->seed();

        $criteria = new SearchAnimalsCriteria(restrictToIds: []);

        self::assertSame(['items' => [], 'total' => 0], $this->repository->search(
            ClinicId::fromString(self::CLINIC_ID),
            $criteria,
        ));
        self::assertSame(0, $this->repository->countBy(ClinicId::fromString(self::CLINIC_ID), $criteria));
    }

    public function testSortByNameDescending(): void
    {
        $this->seed();

        self::assertSame(
            [self::MOKA, self::CHARLIE, self::BUDDY],
            $this->idsFor(new SearchAnimalsCriteria(direction: 'desc')),
        );
    }

    public function testSortBySpecies(): void
    {
        $this->seed();

        // cat before dog, then the name tie-break of the default order.
        self::assertSame(
            self::MOKA,
            $this->idsFor(new SearchAnimalsCriteria(sort: SearchAnimalsCriteria::SORT_SPECIES))[0],
        );
    }

    public function testSortByLifeStatus(): void
    {
        $this->seed();

        // alive vs deceased: "alive" sorts first ascending.
        self::assertSame(
            self::CHARLIE,
            $this->idsFor(new SearchAnimalsCriteria(
                sort: SearchAnimalsCriteria::SORT_STATUS,
                direction: 'desc',
            ))[0],
        );
    }

    public function testSortByCreationDate(): void
    {
        $this->seed();

        self::assertCount(3, $this->idsFor(new SearchAnimalsCriteria(sort: SearchAnimalsCriteria::SORT_CREATED)));
    }

    public function testPagination(): void
    {
        $this->seed();

        $result = $this->repository->search(ClinicId::fromString(self::CLINIC_ID), new SearchAnimalsCriteria(
            page: 2,
            limit: 2,
        ));

        self::assertSame(3, $result['total']);
        self::assertCount(1, $result['items']);
        self::assertSame(self::MOKA, $result['items'][0]->id);
    }

    public function testTheCountMatchesTheSameFilters(): void
    {
        $this->seed();

        self::assertSame(1, $this->repository->countBy(
            ClinicId::fromString(self::CLINIC_ID),
            new SearchAnimalsCriteria(speciesList: ['cat']),
        ));
    }

    /**
     * Three animals of the clinic: Buddy (dog, alive), Charlie (dog, deceased)
     * and Moka (cat, alive).
     */
    private function seed(): void
    {
        AnimalEntityFactory::new()
            ->withId(self::BUDDY)
            ->withClinicId(self::CLINIC_ID)
            ->create(['name' => 'Buddy', 'species' => Species::DOG, 'lifeStatus' => LifeStatus::ALIVE])
        ;
        AnimalEntityFactory::new()
            ->withId(self::CHARLIE)
            ->withClinicId(self::CLINIC_ID)
            ->create(['name' => 'Charlie', 'species' => Species::DOG, 'lifeStatus' => LifeStatus::DECEASED])
        ;
        AnimalEntityFactory::new()
            ->withId(self::MOKA)
            ->withClinicId(self::CLINIC_ID)
            ->create(['name' => 'Moka', 'species' => Species::CAT, 'lifeStatus' => LifeStatus::ALIVE])
        ;
    }

    /**
     * @return list<string>
     */
    private function idsFor(SearchAnimalsCriteria $criteria): array
    {
        $result = $this->repository->search(ClinicId::fromString(self::CLINIC_ID), $criteria);

        return array_map(static fn (AnimalListItemView $item): string => $item->id, $result['items']);
    }
}
