<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Covers what the Répertoire added to the client search: the city, the wider
 * free text, the multi-value filters and the sortable columns.
 */
final class DoctrineClientDirectorySearchTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000000c1';
    private const string OTHER_CLINIC_ID = '01960000-0000-7000-8000-0000000000c2';

    private const string MARIE  = '01960000-0000-7000-8000-0000000000d1';
    private const string JEAN   = '01960000-0000-7000-8000-0000000000d2';
    private const string SOPHIE = '01960000-0000-7000-8000-0000000000d3';

    private ClientReadRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($repository instanceof ClientReadRepositoryInterface);
        $this->repository = $repository;
    }

    public function testTheCityTravelsWithTheRow(): void
    {
        $this->seed();

        $marie = $this->byId(self::MARIE);

        self::assertNotNull($marie);
        self::assertSame('Marseille', $marie->city);
        self::assertSame('marie@example.fr', $marie->primaryEmail);
        self::assertSame('+33612345678', $marie->primaryPhone);
    }

    public function testAClientWithoutAnAddressHasNoCity(): void
    {
        $this->seed();

        self::assertNull($this->byId(self::SOPHIE)?->city);
    }

    public function testFreeTextMatchesTheName(): void
    {
        $this->seed();

        self::assertSame([self::MARIE], $this->idsFor(new SearchClientsCriteria(searchTerm: 'Lambert')));
    }

    public function testFreeTextMatchesTheCity(): void
    {
        $this->seed();

        self::assertSame([self::MARIE], $this->idsFor(new SearchClientsCriteria(searchTerm: 'Marseille')));
    }

    public function testFreeTextMatchesTheEmail(): void
    {
        $this->seed();

        self::assertSame([self::JEAN], $this->idsFor(new SearchClientsCriteria(searchTerm: 'jean@example')));
    }

    public function testFreeTextMatchesThePhone(): void
    {
        $this->seed();

        self::assertSame([self::MARIE], $this->idsFor(new SearchClientsCriteria(searchTerm: '612345678')));
    }

    public function testStatusFilter(): void
    {
        $this->seed();

        self::assertSame([self::SOPHIE], $this->idsFor(new SearchClientsCriteria(statuses: ['archived'])));
    }

    public function testCityFilter(): void
    {
        $this->seed();

        self::assertSame([self::JEAN], $this->idsFor(new SearchClientsCriteria(cities: ['Aubagne'])));
    }

    public function testSortByCity(): void
    {
        $this->seed();

        // Sophie has no city, which MySQL sorts before any value.
        self::assertSame(
            [self::SOPHIE, self::JEAN, self::MARIE],
            $this->idsFor(new SearchClientsCriteria(sort: SearchClientsCriteria::SORT_CITY)),
        );
    }

    public function testSortByCreationDate(): void
    {
        $this->seed();

        self::assertSame(
            [self::SOPHIE, self::JEAN, self::MARIE],
            $this->idsFor(new SearchClientsCriteria(
                sort: SearchClientsCriteria::SORT_CREATED,
                direction: 'desc',
            )),
        );
    }

    public function testSortByNameDescending(): void
    {
        $this->seed();

        self::assertSame(
            [self::SOPHIE, self::JEAN, self::MARIE],
            $this->idsFor(new SearchClientsCriteria(direction: 'desc')),
        );
    }

    public function testTheCountMatchesTheSameFilters(): void
    {
        $this->seed();

        self::assertSame(
            1,
            $this->repository->countBy(ClinicId::fromString(self::CLINIC_ID), new SearchClientsCriteria(
                cities: ['Aubagne'],
            )),
        );
    }

    public function testCitiesAreListedOnceAndAlphabetically(): void
    {
        $this->seed();

        self::assertSame(
            ['Aubagne', 'Marseille'],
            $this->repository->listCities(ClinicId::fromString(self::CLINIC_ID)),
        );
    }

    public function testAnotherClinicIsNeverVisible(): void
    {
        $this->seed();

        ClientEntityFactory::new()
            ->withClinicId(self::OTHER_CLINIC_ID)
            ->withName('Marie', 'Lambert')
            ->active()
            ->withPostalAddress('1 rue Ailleurs', 'Marseille', 'FR')
            ->create()
        ;

        self::assertSame([self::MARIE], $this->idsFor(new SearchClientsCriteria(searchTerm: 'Lambert')));
        self::assertSame(
            ['Aubagne', 'Marseille'],
            $this->repository->listCities(ClinicId::fromString(self::CLINIC_ID)),
        );
    }

    /**
     * Three clients of the clinic:
     *   Marie Lambert  — Marseille, active, phone + email, oldest
     *   Jean Moreau    — Aubagne, active, email only
     *   Sophie Petit   — no address, archived, newest
     */
    private function seed(): void
    {
        ClientEntityFactory::new()
            ->withId(self::MARIE)
            ->withClinicId(self::CLINIC_ID)
            ->withName('Marie', 'Lambert')
            ->active()
            ->withPostalAddress('1 rue des Fleurs', 'Marseille', 'FR')
            ->create(['createdAt' => new \DateTimeImmutable('2024-01-01 09:00:00')])
        ;
        ContactMethodEntityFactory::new()->forClient(self::MARIE)->phone('+33612345678')->primary()->create();
        ContactMethodEntityFactory::new()->forClient(self::MARIE)->email('marie@example.fr')->primary()->create();

        ClientEntityFactory::new()
            ->withId(self::JEAN)
            ->withClinicId(self::CLINIC_ID)
            ->withName('Jean', 'Moreau')
            ->active()
            ->withPostalAddress('2 avenue du Port', 'Aubagne', 'FR')
            ->create(['createdAt' => new \DateTimeImmutable('2025-01-01 09:00:00')])
        ;
        ContactMethodEntityFactory::new()->forClient(self::JEAN)->email('jean@example.fr')->primary()->create();

        ClientEntityFactory::new()
            ->withId(self::SOPHIE)
            ->withClinicId(self::CLINIC_ID)
            ->withName('Sophie', 'Petit')
            ->archived()
            ->create(['createdAt' => new \DateTimeImmutable('2026-01-01 09:00:00')])
        ;
    }

    /**
     * @return list<string>
     */
    private function idsFor(SearchClientsCriteria $criteria): array
    {
        $result = $this->repository->search(ClinicId::fromString(self::CLINIC_ID), $criteria);

        return array_map(static fn (ClientListItemView $item): string => $item->id, $result['items']);
    }

    private function byId(string $clientId): ?ClientListItemView
    {
        $result = $this->repository->search(ClinicId::fromString(self::CLINIC_ID), new SearchClientsCriteria());

        foreach ($result['items'] as $item) {
            if ($item->id === $clientId) {
                return $item;
            }
        }

        return null;
    }
}
