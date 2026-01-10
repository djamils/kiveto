<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Application\Port\ClinicGroupReadRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Fixtures\Clinic\Factory\ClinicGroupEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicGroupReadRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testFindAllReturnsAllClinicGroups(): void
    {
        ClinicGroupEntityFactory::createOne(['name' => 'Group A']);
        ClinicGroupEntityFactory::createOne(['name' => 'Group B']);
        ClinicGroupEntityFactory::createOne(['name' => 'Group C']);

        /** @var ClinicGroupReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupReadRepositoryInterface::class);

        $result = $repo->findAllFiltered();

        self::assertCount(3, $result->clinicGroups);
        self::assertSame(3, $result->total);
    }

    public function testFindAllFiltersActiveStatus(): void
    {
        ClinicGroupEntityFactory::createOne(['name' => 'Active Group', 'status' => ClinicGroupStatus::ACTIVE]);
        ClinicGroupEntityFactory::createOne(['name' => 'Suspended Group', 'status' => ClinicGroupStatus::SUSPENDED]);

        /** @var ClinicGroupReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(status: ClinicGroupStatus::ACTIVE);

        self::assertCount(1, $result->clinicGroups);
        self::assertSame('Active Group', $result->clinicGroups[0]->name);
    }

    public function testFindAllFiltersSuspendedStatus(): void
    {
        ClinicGroupEntityFactory::createOne(['status' => ClinicGroupStatus::ACTIVE]);
        ClinicGroupEntityFactory::createOne(['name' => 'Suspended Group', 'status' => ClinicGroupStatus::SUSPENDED]);

        /** @var ClinicGroupReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(status: ClinicGroupStatus::SUSPENDED);

        self::assertCount(1, $result->clinicGroups);
        self::assertSame('Suspended Group', $result->clinicGroups[0]->name);
    }

    public function testFindAllReturnsEmptyWhenNoMatches(): void
    {
        ClinicGroupEntityFactory::createOne(['name' => 'Test Group', 'status' => ClinicGroupStatus::ACTIVE]);

        /** @var ClinicGroupReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupReadRepositoryInterface::class);

        $result = $repo->findAllFiltered(status: ClinicGroupStatus::SUSPENDED);

        self::assertCount(0, $result->clinicGroups);
        self::assertSame(0, $result->total);
    }
}
