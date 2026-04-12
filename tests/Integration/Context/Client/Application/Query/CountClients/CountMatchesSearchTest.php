<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Application\Query\CountClients;

use App\Context\Client\Application\Query\CountClients\CountClients;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Drift-detection: ensures CountClients always agrees with SearchClients
 * for any criteria combination. If a future change to the SearchClients
 * WHERE clause is not mirrored in CountClients, this test fails.
 */
final class CountMatchesSearchTest extends KernelTestCase
{
    use Factories;

    public function testCountMatchesSearchAcrossCriteriaCombinations(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        $names = [
            ['Alice', 'Anderson', ClientStatus::ACTIVE],
            ['Bob', 'Brown', ClientStatus::ACTIVE],
            ['Charlie', 'Carter', ClientStatus::ARCHIVED],
            ['Diana', 'Davies', ClientStatus::ACTIVE],
            ['Edward', 'Edmonds', ClientStatus::ARCHIVED],
            ['Felicity', 'Fairbanks', ClientStatus::ACTIVE],
            ['George', 'Gardner', ClientStatus::ACTIVE],
            ['Helen', 'Hughes', ClientStatus::ARCHIVED],
            ['Iris', 'Ingram', ClientStatus::ACTIVE],
            ['Jack', 'Johnson', ClientStatus::ACTIVE],
            ['Karen', 'Kennedy', ClientStatus::ARCHIVED],
            ['Liam', 'Larson', ClientStatus::ACTIVE],
            ['Marie', 'Morrison', ClientStatus::ACTIVE],
            ['Noah', 'Nelson', ClientStatus::ARCHIVED],
            ['Olivia', 'Owens', ClientStatus::ACTIVE],
        ];

        foreach ($names as [$first, $last, $status]) {
            ClientEntityFactory::createOne([
                'clinicId'  => $clinicUuid,
                'firstName' => $first,
                'lastName'  => $last,
                'status'    => $status,
            ]);
        }

        $bus = static::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        $combinations = [
            ['searchTerm' => null, 'status' => null],
            ['searchTerm' => 'a', 'status' => null],
            ['searchTerm' => null, 'status' => 'active'],
            ['searchTerm' => 'a', 'status' => 'active'],
            ['searchTerm' => 'son', 'status' => null],
            ['searchTerm' => 'son', 'status' => 'archived'],
        ];

        foreach ($combinations as $combo) {
            $countResult = $bus->ask(new CountClients(
                clinicId: $clinicId,
                searchTerm: $combo['searchTerm'],
                status: $combo['status'],
            ));
            \assert(\is_int($countResult));

            $searchResult = $bus->ask(new SearchClients(
                clinicId: $clinicId,
                searchTerm: $combo['searchTerm'],
                status: $combo['status'],
                page: 1,
                limit: 100,
            ));
            \assert(\is_array($searchResult));
            \assert(\is_int($searchResult['total']));

            self::assertSame(
                $searchResult['total'],
                $countResult,
                \sprintf('CountClients diverges from SearchClients for criteria %s', json_encode($combo)),
            );
        }
    }
}
