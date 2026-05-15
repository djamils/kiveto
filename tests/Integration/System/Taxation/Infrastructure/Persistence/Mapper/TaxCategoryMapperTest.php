<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence\Mapper;

use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\TaxCategoryMapper;
use PHPUnit\Framework\TestCase;

final class TaxCategoryMapperTest extends TestCase
{
    private TaxCategoryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TaxCategoryMapper();
    }

    public function testRoundTrip(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $category = TaxCategory::reconstitute(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Consultation vétérinaire',
            null,
            true,
            $now,
            $now,
        );

        $entity        = $this->mapper->toEntity($category);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame('veterinary.act.consultation', $reconstituted->code()->toString());
        self::assertSame('Consultation vétérinaire', $reconstituted->displayName());
        self::assertNull($reconstituted->description());
        self::assertTrue($reconstituted->isActive());
        self::assertSame($now->getTimestamp(), $reconstituted->createdAt()->getTimestamp());
        self::assertSame($now->getTimestamp(), $reconstituted->updatedAt()->getTimestamp());
    }

    public function testRoundTripWithDescription(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $category = TaxCategory::reconstitute(
            TaxCategoryCode::fromString('veterinary.drug.companion'),
            'Médicaments animaux de compagnie',
            'Médicaments vétérinaires pour animaux de compagnie (taux réduit)',
            true,
            $now,
            $now,
        );

        $entity        = $this->mapper->toEntity($category);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame('veterinary.drug.companion', $reconstituted->code()->toString());
        self::assertSame('Médicaments animaux de compagnie', $reconstituted->displayName());
        self::assertSame('Médicaments vétérinaires pour animaux de compagnie (taux réduit)', $reconstituted->description());
    }

    public function testRoundTripWithInactiveCategory(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $category = TaxCategory::reconstitute(
            TaxCategoryCode::fromString('veterinary.act.surgery'),
            'Acte chirurgical',
            null,
            false,
            $now,
            $now,
        );

        $entity        = $this->mapper->toEntity($category);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertFalse($reconstituted->isActive());
    }

    public function testToEntityPreservesCode(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $category = TaxCategory::reconstitute(
            TaxCategoryCode::fromString('veterinary.food.therapeutic'),
            'Aliments thérapeutiques',
            'Aliments diététiques à visée thérapeutique',
            true,
            $now,
            $now,
        );

        $entity = $this->mapper->toEntity($category);

        self::assertSame('veterinary.food.therapeutic', $entity->getCode());
        self::assertSame('Aliments thérapeutiques', $entity->getDisplayName());
        self::assertSame('Aliments diététiques à visée thérapeutique', $entity->getDescription());
        self::assertTrue($entity->isActive());
    }
}
