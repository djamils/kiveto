<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Application\Query\CountAnimals;

use App\Context\Animal\Application\Query\CountAnimals\CountAnimals;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Drift-detection: ensures CountAnimals stays in sync with SearchAnimals
 * across criteria combinations. Catches WHERE-clause drift between the two.
 */
final class CountMatchesSearchTest extends KernelTestCase
{
    use Factories;

    public function testCountMatchesSearchAcrossCriteriaCombinations(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        $samples = [
            ['Rex', Species::DOG, AnimalStatus::ACTIVE],
            ['Buddy', Species::DOG, AnimalStatus::ACTIVE],
            ['Whiskers', Species::CAT, AnimalStatus::ACTIVE],
            ['Felix', Species::CAT, AnimalStatus::ARCHIVED],
            ['Daisy', Species::DOG, AnimalStatus::ARCHIVED],
            ['Ginger', Species::CAT, AnimalStatus::ACTIVE],
            ['Max', Species::DOG, AnimalStatus::ACTIVE],
            ['Luna', Species::CAT, AnimalStatus::ACTIVE],
            ['Charlie', Species::DOG, AnimalStatus::ACTIVE],
            ['Milo', Species::DOG, AnimalStatus::ACTIVE],
            ['Bella', Species::DOG, AnimalStatus::ARCHIVED],
            ['Coco', Species::CAT, AnimalStatus::ACTIVE],
            ['Oscar', Species::CAT, AnimalStatus::ACTIVE],
            ['Lily', Species::CAT, AnimalStatus::ARCHIVED],
            ['Toby', Species::DOG, AnimalStatus::ACTIVE],
        ];

        foreach ($samples as [$name, $species, $status]) {
            AnimalEntityFactory::createOne([
                'clinicId' => $clinicUuid,
                'name'     => $name,
                'species'  => $species,
                'status'   => $status,
            ]);
        }

        $bus = static::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        $combinations = [
            ['searchTerm' => null, 'status' => null, 'species' => null],
            ['searchTerm' => 'a', 'status' => null, 'species' => null],
            ['searchTerm' => null, 'status' => 'active', 'species' => null],
            ['searchTerm' => null, 'status' => null, 'species' => 'dog'],
            ['searchTerm' => null, 'status' => 'active', 'species' => 'dog'],
            ['searchTerm' => 'i', 'status' => 'active', 'species' => 'cat'],
        ];

        foreach ($combinations as $combo) {
            $countResult = $bus->ask(new CountAnimals(
                clinicId: $clinicId,
                searchTerm: $combo['searchTerm'],
                status: $combo['status'],
                species: $combo['species'],
            ));
            \assert(\is_int($countResult));

            $searchResult = $bus->ask(new SearchAnimals(
                clinicId: $clinicId,
                searchTerm: $combo['searchTerm'],
                status: $combo['status'],
                species: $combo['species'],
                page: 1,
                limit: 100,
            ));
            \assert(\is_array($searchResult));
            \assert(\is_int($searchResult['total']));

            self::assertSame(
                $searchResult['total'],
                $countResult,
                \sprintf('CountAnimals diverges from SearchAnimals for criteria %s', json_encode($combo)),
            );
        }
    }
}
