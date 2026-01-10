<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Fixtures\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Clinic\Factory\ClinicGroupEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicReadRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testFindAllReturnsAllClinics(): void
    {
        ClinicEntityFactory::createOne(['name' => 'Clinic A', 'slug' => 'clinic-a']);
        ClinicEntityFactory::createOne(['name' => 'Clinic B', 'slug' => 'clinic-b']);
        ClinicEntityFactory::createOne(['name' => 'Clinic C', 'slug' => 'clinic-c']);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered();

        self::assertCount(3, $result->clinics);
        self::assertSame(3, $result->total);
    }

    public function testFindAllFiltersActiveStatus(): void
    {
        ClinicEntityFactory::createOne(['name' => 'Active Clinic', 'status' => ClinicStatus::ACTIVE]);
        ClinicEntityFactory::createOne(['name' => 'Suspended Clinic', 'status' => ClinicStatus::SUSPENDED]);
        ClinicEntityFactory::createOne(['name' => 'Closed Clinic', 'status' => ClinicStatus::CLOSED]);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(status: ClinicStatus::ACTIVE);

        self::assertCount(1, $result->clinics);
        self::assertSame('Active Clinic', $result->clinics[0]->name);
    }

    public function testFindAllFiltersSuspendedStatus(): void
    {
        ClinicEntityFactory::createOne(['status' => ClinicStatus::ACTIVE]);
        ClinicEntityFactory::createOne(['name' => 'Suspended Clinic', 'status' => ClinicStatus::SUSPENDED]);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(status: ClinicStatus::SUSPENDED);

        self::assertCount(1, $result->clinics);
        self::assertSame('Suspended Clinic', $result->clinics[0]->name);
    }

    public function testFindAllFiltersByClinicGroup(): void
    {
        $groupId = Uuid::v7();
        ClinicGroupEntityFactory::createOne(['id' => $groupId, 'name' => 'Test Group']);

        ClinicEntityFactory::createOne(['name' => 'In Group', 'clinicGroupId' => $groupId]);
        ClinicEntityFactory::createOne(['name' => 'Not In Group', 'clinicGroupId' => null]);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(clinicGroupId: $groupId->toString());

        self::assertCount(1, $result->clinics);
        self::assertSame('In Group', $result->clinics[0]->name);
        self::assertSame($groupId->toString(), $result->clinics[0]->clinicGroupId);
    }

    public function testFindAllSearchesByName(): void
    {
        ClinicEntityFactory::createOne(['name' => 'Veterinary Clinic Paris']);
        ClinicEntityFactory::createOne(['name' => 'Animal Hospital London']);
        ClinicEntityFactory::createOne(['name' => 'Vet Clinic Berlin']);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(search: 'Vet');

        self::assertCount(2, $result->clinics);
    }

    public function testFindAllCombinesMultipleFilters(): void
    {
        $groupId = Uuid::v7();
        ClinicGroupEntityFactory::createOne(['id' => $groupId]);

        ClinicEntityFactory::createOne([
            'name'          => 'Active Clinic In Group',
            'status'        => ClinicStatus::ACTIVE,
            'clinicGroupId' => $groupId,
        ]);
        ClinicEntityFactory::createOne([
            'name'          => 'Suspended Clinic In Group',
            'status'        => ClinicStatus::SUSPENDED,
            'clinicGroupId' => $groupId,
        ]);
        ClinicEntityFactory::createOne([
            'name'   => 'Active Clinic No Group',
            'status' => ClinicStatus::ACTIVE,
        ]);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(status: ClinicStatus::ACTIVE, clinicGroupId: $groupId->toString());

        self::assertCount(1, $result->clinics);
        self::assertSame('Active Clinic In Group', $result->clinics[0]->name);
    }

    public function testFindAllReturnsEmptyWhenNoMatches(): void
    {
        ClinicEntityFactory::createOne(['name' => 'Test Clinic']);

        /** @var ClinicReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(search: 'NonExistent');

        self::assertCount(0, $result->clinics);
        self::assertSame(0, $result->total);
    }
}
