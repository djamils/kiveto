<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Fixtures\Clinic\Factory\ClinicGroupEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineClinicGroupRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

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
}
