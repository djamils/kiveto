<?php

declare(strict_types=1);

namespace App\Tests\Integration\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Fixtures\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineClinicRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private ClinicRepositoryInterface $repo;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = static::getContainer()->get(ClinicRepositoryInterface::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    public function testSaveAndFindById(): void
    {
        $clinic = Clinic::create(
            id: ClinicId::fromString('018f1b1e-1111-7890-abcd-0123456789ab'),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            clinicGroupId: null,
        );

        $this->repo->save($clinic);
        $this->em->clear();

        $found = $this->repo->findById(ClinicId::fromString('018f1b1e-1111-7890-abcd-0123456789ab'));

        self::assertNotNull($found);
        self::assertSame('Test Clinic', $found->name());
        self::assertSame('test-clinic', $found->slug()->toString());
        self::assertSame('Europe/Paris', $found->timeZone()->toString());
        self::assertSame('fr-FR', $found->locale()->toString());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findById(ClinicId::fromString('018f1b1e-9999-7890-abcd-0123456789ab'));

        self::assertNull($found);
    }

    public function testFindBySlug(): void
    {
        ClinicEntityFactory::createOne([
            'slug' => 'existing-clinic',
            'name' => 'Existing Clinic',
        ]);

        $found = $this->repo->findBySlug(ClinicSlug::fromString('existing-clinic'));

        self::assertNotNull($found);
        self::assertSame('Existing Clinic', $found->name());
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findBySlug(ClinicSlug::fromString('non-existent'));

        self::assertNull($found);
    }

    public function testExistsBySlugReturnsTrueWhenExists(): void
    {
        ClinicEntityFactory::createOne([
            'slug' => 'existing-clinic-2',
        ]);

        $exists = $this->repo->existsBySlug(ClinicSlug::fromString('existing-clinic-2'));

        self::assertTrue($exists);
    }

    public function testExistsBySlugReturnsFalseWhenNotExists(): void
    {
        $exists = $this->repo->existsBySlug(ClinicSlug::fromString('non-existent'));

        self::assertFalse($exists);
    }

    public function testUpdateClinic(): void
    {
        $clinic = Clinic::create(
            id: ClinicId::fromString('018f1b1e-2222-7890-abcd-0123456789ab'),
            name: 'Original Name',
            slug: ClinicSlug::fromString('original-slug'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            createdAt: new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            clinicGroupId: null,
        );

        $this->repo->save($clinic);
        $this->em->clear();

        $found = $this->repo->findById(ClinicId::fromString('018f1b1e-2222-7890-abcd-0123456789ab'));
        self::assertNotNull($found);

        $found->rename('Updated Name', new \DateTimeImmutable('2024-01-02T10:00:00Z'));
        $this->repo->save($found);
        $this->em->clear();

        $updated = $this->repo->findById(ClinicId::fromString('018f1b1e-2222-7890-abcd-0123456789ab'));

        self::assertNotNull($updated);
        self::assertSame('Updated Name', $updated->name());
    }
}
