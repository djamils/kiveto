<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Tests\Support\Database\QueryCounter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/** Several animals of a clinic read at once, with their ownerships, in one query. */
final class DoctrineAnimalReadRepositoryByIdsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER     = '88888888-8888-7888-8888-888888888888';
    private const string REX       = '0192b000-0000-7000-8000-0000000000a1';
    private const string LUNA      = '0192b000-0000-7000-8000-0000000000a2';
    private const string ELSEWHERE = '0192b000-0000-7000-8000-0000000000a3';
    private const string UNKNOWN   = '0192b000-0000-7000-8000-0000000000a4';
    private const string OWNER     = '0192d000-0000-7000-8000-0000000000c1';
    private const string KEEPER    = '0192d000-0000-7000-8000-0000000000c2';

    public function testTheClinicsAnimalsAreReadWithTheirOwnershipsInOneQuery(): void
    {
        $this->pet(self::REX, 'Rex', self::CLINIC, [self::OWNER => OwnershipRole::PRIMARY, self::KEEPER => OwnershipRole::SECONDARY]);
        $this->pet(self::LUNA, 'Luna', self::CLINIC, []);
        $this->pet(self::ELSEWHERE, 'Ailleurs', self::OTHER, [self::OWNER => OwnershipRole::PRIMARY]);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();

        QueryCounter::reset();
        $animals = $this->repository()->findByIds(ClinicId::fromString(self::CLINIC), [self::REX, self::LUNA, self::ELSEWHERE, self::UNKNOWN]);
        $queries = QueryCounter::count();

        $byId = [];
        foreach ($animals as $animal) {
            $byId[$animal->id] = $animal;
        }
        ksort($byId);

        // Another clinic's animal and an unknown id are left out.
        self::assertSame([self::REX, self::LUNA], array_keys($byId));
        self::assertSame(['Rex', 'Luna'], [$byId[self::REX]->name, $byId[self::LUNA]->name]);
        self::assertSame(self::OWNER, $byId[self::REX]->primaryOwnerClientId());

        $ownerships = array_map(static fn (OwnershipDto $o): array => [$o->clientId, $o->role], $byId[self::REX]->ownerships);
        sort($ownerships);
        self::assertSame([[self::OWNER, 'primary'], [self::KEEPER, 'secondary']], $ownerships);
        self::assertSame([], $byId[self::LUNA]->ownerships);

        self::assertSame(1, $queries, 'the ownerships come with the animals');
    }

    /**
     * @param array<string, OwnershipRole> $owners client id => role
     */
    private function pet(string $id, string $name, string $clinicId, array $owners): void
    {
        AnimalEntityFactory::new()->withClinicId($clinicId)->withName($name)->dog()->active()->alive()->create(['id' => Uuid::fromString($id)]);

        foreach ($owners as $clientId => $role) {
            OwnershipEntityFactory::createOne([
                'animal'   => AnimalEntityFactory::find(['id' => Uuid::fromString($id)]),
                'clientId' => Uuid::fromString($clientId),
                'role'     => $role,
            ]);
        }
    }

    private function repository(): AnimalReadRepositoryInterface
    {
        $repository = self::getContainer()->get(AnimalReadRepositoryInterface::class);
        \assert($repository instanceof AnimalReadRepositoryInterface);

        return $repository;
    }
}
