<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Fixtures\Clinic\Factory\ClinicGroupEntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineClinicGroupRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private ClinicGroupRepositoryInterface $repo;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = static::getContainer()->get(ClinicGroupRepositoryInterface::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    public function testSaveAndFindById(): void
    {
        $group = ClinicGroup::create(
            id: ClinicGroupId::fromString('018f1b1e-3333-7890-abcd-0123456789ab'),
            name: 'Test Group',
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $this->repo->save($group);
        $this->em->clear();

        $found = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-3333-7890-abcd-0123456789ab'));

        self::assertNotNull($found);
        self::assertSame('Test Group', $found->name());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-9999-7890-abcd-0123456789ab'));

        self::assertNull($found);
    }

    public function testUpdateClinicGroup(): void
    {
        $group = ClinicGroup::create(
            id: ClinicGroupId::fromString('018f1b1e-4444-7890-abcd-0123456789ab'),
            name: 'Original Name',
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $this->repo->save($group);
        $this->em->clear();

        $found = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-4444-7890-abcd-0123456789ab'));
        self::assertNotNull($found);

        $found->rename('Updated Name');
        $this->repo->save($found);
        $this->em->clear();

        $updated = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-4444-7890-abcd-0123456789ab'));

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->name());
    }

    public function testClinicGroupStatusChanges(): void
    {
        $group = ClinicGroup::create(
            id: ClinicGroupId::fromString('018f1b1e-5555-7890-abcd-0123456789ab'),
            name: 'Test Group',
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $this->repo->save($group);
        $this->em->clear();

        $found = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-5555-7890-abcd-0123456789ab'));
        self::assertNotNull($found);

        $found->suspend();
        $this->repo->save($found);
        $this->em->clear();

        $suspended = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-5555-7890-abcd-0123456789ab'));

        self::assertNotNull($suspended);
        self::assertTrue($suspended->status()->value === 'suspended');

        $suspended->activate();
        $this->repo->save($suspended);
        $this->em->clear();

        $activated = $this->repo->findById(ClinicGroupId::fromString('018f1b1e-5555-7890-abcd-0123456789ab'));

        self::assertNotNull($activated);
        self::assertTrue($activated->status()->value === 'active');
    }
}
