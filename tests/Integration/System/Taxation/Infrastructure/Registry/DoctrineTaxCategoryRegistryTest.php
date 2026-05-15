<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Registry;

use App\Fixtures\System\Taxation\Factory\TaxCategoryEntityFactory;
use App\System\Taxation\Domain\Exception\UnknownTaxCategoryException;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Infrastructure\Registry\DoctrineTaxCategoryRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineTaxCategoryRegistryTest extends KernelTestCase
{
    use Factories;

    public function testGetReturnsCategory(): void
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

        $registry = $this->getRegistry();
        $category = $registry->get(TaxCategoryCode::fromString('veterinary.act.consultation'));

        self::assertSame('veterinary.act.consultation', $category->code()->toString());
        self::assertSame('Consultation vétérinaire', $category->displayName());
        self::assertNull($category->description());
        self::assertTrue($category->isActive());
    }

    public function testGetThrowsUnknownTaxCategoryException(): void
    {
        $registry = $this->getRegistry();

        $this->expectException(UnknownTaxCategoryException::class);

        $registry->get(TaxCategoryCode::fromString('veterinary.act.unknown'));
    }

    public function testHasReturnsTrueForKnownCode(): void
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

        $registry = $this->getRegistry();

        self::assertTrue($registry->has(TaxCategoryCode::fromString('veterinary.act.consultation')));
    }

    public function testHasReturnsFalseForUnknownCode(): void
    {
        $registry = $this->getRegistry();

        self::assertFalse($registry->has(TaxCategoryCode::fromString('veterinary.act.unknown')));
    }

    public function testListActiveExcludesInactive(): void
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
        TaxCategoryEntityFactory::createOne([
            'code'        => 'veterinary.drug.companion',
            'displayName' => 'Médicaments animaux de compagnie',
            'description' => null,
            'active'      => true,
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ]);

        $registry = $this->getRegistry();
        $active   = $registry->listActive();

        self::assertCount(2, $active);
    }

    private function getRegistry(): DoctrineTaxCategoryRegistry
    {
        $registry = static::getContainer()->get(DoctrineTaxCategoryRegistry::class);
        \assert($registry instanceof DoctrineTaxCategoryRegistry);

        return $registry;
    }
}
