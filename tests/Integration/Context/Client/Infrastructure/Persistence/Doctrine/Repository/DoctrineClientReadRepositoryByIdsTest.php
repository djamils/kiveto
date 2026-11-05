<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Tests\Support\Database\QueryCounter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/** Several files read at once: the views of GetClientById, in two queries whatever their number. */
final class DoctrineClientReadRepositoryByIdsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER     = '88888888-8888-7888-8888-888888888888';
    private const string SOPHIE    = '0192d000-0000-7000-8000-0000000000d1';
    private const string PAUL      = '0192d000-0000-7000-8000-0000000000d2';
    private const string ELSEWHERE = '0192d000-0000-7000-8000-0000000000d3';
    private const string UNKNOWN   = '0192d000-0000-7000-8000-0000000000d4';

    public function testTheClinicsFilesWithTheirContactMethodsInTwoQueries(): void
    {
        ClientEntityFactory::new()->withId(self::SOPHIE)->withClinicId(self::CLINIC)->withName('Sophie', 'Martin')->withCivility('madam')->active()->create();
        ContactMethodEntityFactory::new()->forClient(self::SOPHIE)->phone('+33612345678')->mobile()->primary()->create();
        ContactMethodEntityFactory::new()->forClient(self::SOPHIE)->email('sophie@example.com')->create();
        ClientEntityFactory::new()->withId(self::PAUL)->withClinicId(self::CLINIC)->withName('Paul', 'Durand')->active()->create();
        ClientEntityFactory::new()->withId(self::ELSEWHERE)->withClinicId(self::OTHER)->withName('Ailleurs', 'Autre')->active()->create();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();

        QueryCounter::reset();
        $clients = $this->repository()->findByIds(ClinicId::fromString(self::CLINIC), [self::SOPHIE, self::PAUL, self::ELSEWHERE, self::UNKNOWN]);
        $queries = QueryCounter::count();

        $byId = [];
        foreach ($clients as $client) {
            $byId[$client->id] = $client;
        }
        ksort($byId);

        self::assertSame([self::SOPHIE, self::PAUL], array_keys($byId));
        self::assertSame(2, $queries, 'the files, then all their contact methods');

        // The same views as one file read on its own.
        foreach ([self::SOPHIE, self::PAUL] as $id) {
            self::assertSame(
                json_encode($this->repository()->findById(ClinicId::fromString(self::CLINIC), ClientId::fromString($id)), \JSON_THROW_ON_ERROR),
                json_encode($byId[$id], \JSON_THROW_ON_ERROR),
            );
        }
        self::assertCount(2, $byId[self::SOPHIE]->contactMethods);
        self::assertSame([], $byId[self::PAUL]->contactMethods);

        self::assertSame([], $this->repository()->findByIds(ClinicId::fromString(self::CLINIC), [self::UNKNOWN]));
    }

    private function repository(): ClientReadRepositoryInterface
    {
        $repository = self::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($repository instanceof ClientReadRepositoryInterface);

        return $repository;
    }
}
