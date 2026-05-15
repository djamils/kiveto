<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence;

use App\Fixtures\System\Taxation\Factory\TaxCategoryEntityFactory;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository\DoctrineTaxCategoryRepository;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineTaxCategoryRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveThenFindByCode(): void
    {
        $clock    = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Consultation vétérinaire',
            null,
            $clock,
        );

        $repo = $this->getRepository();
        $repo->save($category);

        $found = $repo->findByCode(TaxCategoryCode::fromString('veterinary.act.consultation'));

        self::assertNotNull($found);
        self::assertSame('veterinary.act.consultation', $found->code()->toString());
        self::assertSame('Consultation vétérinaire', $found->displayName());
        self::assertNull($found->description());
        self::assertTrue($found->isActive());
    }

    public function testSaveThenFindByCodeWithDescription(): void
    {
        $clock    = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.drug.companion'),
            'Médicaments animaux de compagnie',
            'Médicaments vétérinaires pour animaux de compagnie (taux réduit)',
            $clock,
        );

        $repo = $this->getRepository();
        $repo->save($category);

        $found = $repo->findByCode(TaxCategoryCode::fromString('veterinary.drug.companion'));

        self::assertNotNull($found);
        self::assertSame('veterinary.drug.companion', $found->code()->toString());
        self::assertSame('Médicaments animaux de compagnie', $found->displayName());
        self::assertSame('Médicaments vétérinaires pour animaux de compagnie (taux réduit)', $found->description());
    }

    public function testSaveUpdatesExistingCategory(): void
    {
        $clock    = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $category = TaxCategory::create(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Consultation vétérinaire',
            null,
            $clock,
        );

        $repo = $this->getRepository();
        $repo->save($category);

        $category->updateDisplay('Consultation vétérinaire (updated)', 'Une description', $clock);
        $repo->save($category);

        $found = $repo->findByCode(TaxCategoryCode::fromString('veterinary.act.consultation'));

        self::assertNotNull($found);
        self::assertSame('Consultation vétérinaire (updated)', $found->displayName());
        self::assertSame('Une description', $found->description());
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $repo  = $this->getRepository();
        $found = $repo->findByCode(TaxCategoryCode::fromString('veterinary.act.unknown'));

        self::assertNull($found);
    }

    public function testFindAllActiveExcludesInactive(): void
    {
        $now = new \DateTimeImmutable();

        TaxCategoryEntityFactory::createOne([
            'code'        => 'veterinary.act.consultation',
            'displayName' => 'Consultation vétérinaire',
            'description' => null,
            'active'      => true,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ]);
        TaxCategoryEntityFactory::createOne([
            'code'        => 'veterinary.act.surgery',
            'displayName' => 'Acte chirurgical',
            'description' => null,
            'active'      => false,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ]);

        $repo   = $this->getRepository();
        $active = $repo->findAllActive();

        self::assertCount(1, $active);
        self::assertSame('veterinary.act.consultation', $active[0]->code()->toString());
    }

    public function testFindAllReturnsAllCategories(): void
    {
        $now = new \DateTimeImmutable();

        TaxCategoryEntityFactory::createOne([
            'code'        => 'veterinary.act.consultation',
            'displayName' => 'Consultation vétérinaire',
            'description' => null,
            'active'      => true,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ]);
        TaxCategoryEntityFactory::createOne([
            'code'        => 'veterinary.act.surgery',
            'displayName' => 'Acte chirurgical',
            'description' => null,
            'active'      => false,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ]);

        $repo = $this->getRepository();
        $all  = $repo->findAll();

        self::assertCount(2, $all);
    }

    private function getRepository(): DoctrineTaxCategoryRepository
    {
        $repo = static::getContainer()->get(DoctrineTaxCategoryRepository::class);
        \assert($repo instanceof DoctrineTaxCategoryRepository);

        return $repo;
    }
}
