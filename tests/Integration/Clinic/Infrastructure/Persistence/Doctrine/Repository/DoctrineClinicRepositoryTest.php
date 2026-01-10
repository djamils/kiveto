<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Fixtures\Clinic\Factory\ClinicEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineClinicRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFindByIdReconstitutesClinicFromDoctrineEntity(): void
    {
        ClinicEntityFactory::createOne([
            'id'            => Uuid::fromString('018f1b1e-1234-7890-abcd-0123456789ab'),
            'name'          => 'Test Clinic',
            'slug'          => 'test-clinic',
            'timeZone'      => 'Europe/Paris',
            'locale'        => 'fr-FR',
            'clinicGroupId' => null,
        ]);

        /** @var ClinicRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicRepositoryInterface::class);

        $clinic = $repo->findById(ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab'));

        self::assertNotNull($clinic);
        self::assertSame('Test Clinic', $clinic->name());
        self::assertSame('test-clinic', $clinic->slug()->toString());
        self::assertSame('Europe/Paris', $clinic->timeZone()->toString());
        self::assertSame('fr-FR', $clinic->locale()->toString());
        self::assertNull($clinic->clinicGroupId());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        /** @var ClinicRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicRepositoryInterface::class);

        $clinic = $repo->findById(ClinicId::fromString('018f1b1e-9999-7890-abcd-0123456789ab'));

        self::assertNull($clinic);
    }

    public function testFindBySlugReconstitutesClinic(): void
    {
        ClinicEntityFactory::createOne([
            'slug' => 'my-clinic',
            'name' => 'My Clinic',
        ]);

        /** @var ClinicRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicRepositoryInterface::class);

        $clinic = $repo->findBySlug(ClinicSlug::fromString('my-clinic'));

        self::assertNotNull($clinic);
        self::assertSame('My Clinic', $clinic->name());
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        /** @var ClinicRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicRepositoryInterface::class);

        $clinic = $repo->findBySlug(ClinicSlug::fromString('non-existent'));

        self::assertNull($clinic);
    }

    public function testExistsBySlugReturnsTrueWhenExists(): void
    {
        ClinicEntityFactory::createOne([
            'slug' => 'existing-slug',
        ]);

        /** @var ClinicRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicRepositoryInterface::class);

        $exists = $repo->existsBySlug(ClinicSlug::fromString('existing-slug'));

        self::assertTrue($exists);
    }

    public function testExistsBySlugReturnsFalseWhenNotExists(): void
    {
        /** @var ClinicRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClinicRepositoryInterface::class);

        $exists = $repo->existsBySlug(ClinicSlug::fromString('non-existent'));

        self::assertFalse($exists);
    }
}
