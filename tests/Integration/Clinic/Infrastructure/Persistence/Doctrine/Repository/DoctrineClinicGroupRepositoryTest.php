<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Fixtures\Clinic\Factory\ClinicGroupEntityFactory;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicGroupRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testFindByIdReconstitutesClinicGroupFromDoctrineEntity(): void
    {
        ClinicGroupEntityFactory::createOne([
            'id'     => Uuid::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            'name'   => 'Test Group',
            'status' => ClinicGroupStatus::ACTIVE,
        ]);

        /** @var ClinicGroupRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupRepositoryInterface::class);

        $group = $repo->findById(ClinicGroupId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'));

        self::assertNotNull($group);
        self::assertSame('Test Group', $group->name());
        self::assertSame(ClinicGroupStatus::ACTIVE, $group->status());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        /** @var ClinicGroupRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupRepositoryInterface::class);

        $group = $repo->findById(ClinicGroupId::fromString('018f1b1e-9999-7890-abcd-0123456789ab'));

        self::assertNull($group);
    }

    public function testSavePersistsNewClinicGroup(): void
    {
        /** @var ClinicGroupRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupRepositoryInterface::class);

        $group = ClinicGroup::create(
            id: ClinicGroupId::fromString('018f1b1e-aaaa-7890-abcd-0123456789ab'),
            name: 'New Group',
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $repo->save($group);

        $persisted = $repo->findById(ClinicGroupId::fromString('018f1b1e-aaaa-7890-abcd-0123456789ab'));

        self::assertNotNull($persisted);
        self::assertSame('New Group', $persisted->name());
        self::assertSame(ClinicGroupStatus::ACTIVE, $persisted->status());
    }

    public function testSaveUpdatesExistingClinicGroup(): void
    {
        ClinicGroupEntityFactory::createOne([
            'id'   => Uuid::fromString('018f1b1e-bbbb-7890-abcd-0123456789ab'),
            'name' => 'Original Name',
        ]);

        /** @var ClinicGroupRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicGroupRepositoryInterface::class);

        $group = $repo->findById(ClinicGroupId::fromString('018f1b1e-bbbb-7890-abcd-0123456789ab'));
        self::assertNotNull($group);

        $group->rename('Updated Name');

        $repo->save($group);

        // Clear the EntityManager to avoid identity map conflicts.
        /** @var Registry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();

        $updated = $repo->findById(ClinicGroupId::fromString('018f1b1e-bbbb-7890-abcd-0123456789ab'));

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->name());
    }
}
