<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\UnitOfMeasure;

use App\Shared\Domain\UnitOfMeasure\Exception\UnknownUnitOfMeasureException;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitCategory;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Infrastructure\UnitOfMeasure\Registry\YamlUnitOfMeasureRegistry;
use PHPUnit\Framework\TestCase;

final class YamlUnitOfMeasureRegistryTest extends TestCase
{
    private YamlUnitOfMeasureRegistry $registry;

    protected function setUp(): void
    {
        $catalogPath    = __DIR__ . '/../../../../../src/Shared/Infrastructure/UnitOfMeasure/Resources/units.yaml';
        $this->registry = new YamlUnitOfMeasureRegistry($catalogPath);
    }

    public function testResolvesKnownUnit(): void
    {
        $unit       = UnitOfMeasure::fromString('TABLET');
        $definition = $this->registry->resolve($unit);

        self::assertSame('TABLET', $definition->code->toString());
        self::assertSame(UnitCategory::COUNT, $definition->category);
        self::assertNull($definition->baseFactor);
    }

    public function testResolvesVolumeUnitWithBaseFactor(): void
    {
        $unit       = UnitOfMeasure::fromString('ML');
        $definition = $this->registry->resolve($unit);

        self::assertSame(UnitCategory::VOLUME, $definition->category);
        self::assertSame('0.001', $definition->baseFactor);
    }

    public function testResolvesMassUnit(): void
    {
        $unit       = UnitOfMeasure::fromString('KG');
        $definition = $this->registry->resolve($unit);

        self::assertSame(UnitCategory::MASS, $definition->category);
        self::assertSame('1', $definition->baseFactor);
    }

    public function testThrowsForUnknownUnit(): void
    {
        $this->expectException(UnknownUnitOfMeasureException::class);
        $this->registry->resolve(UnitOfMeasure::fromString('UNKNOWN_UNIT'));
    }

    public function testHasReturnsTrueForKnownUnit(): void
    {
        self::assertTrue($this->registry->has(UnitOfMeasure::fromString('TABLET')));
        self::assertTrue($this->registry->has(UnitOfMeasure::fromString('KG')));
        self::assertTrue($this->registry->has(UnitOfMeasure::fromString('ML')));
    }

    public function testHasReturnsFalseForUnknownUnit(): void
    {
        self::assertFalse($this->registry->has(UnitOfMeasure::fromString('UNKNOWN_X')));
    }

    public function testAllReturnsAllDefinedUnits(): void
    {
        $all = $this->registry->all();

        self::assertCount(12, $all);

        $codes = array_map(static fn ($def) => $def->code->toString(), $all);
        self::assertContains('BOX', $codes);
        self::assertContains('TABLET', $codes);
        self::assertContains('CAPSULE', $codes);
        self::assertContains('VIAL', $codes);
        self::assertContains('SACHET', $codes);
        self::assertContains('AMPULE', $codes);
        self::assertContains('UNIT', $codes);
        self::assertContains('DOSE', $codes);
        self::assertContains('ML', $codes);
        self::assertContains('L', $codes);
        self::assertContains('G', $codes);
        self::assertContains('KG', $codes);
    }

    public function testThrowsOnMissingUnitsKey(): void
    {
        $this->expectException(\RuntimeException::class);

        $tmpFile = tempnam(sys_get_temp_dir(), 'units_test_');
        \assert(\is_string($tmpFile));
        file_put_contents($tmpFile, 'invalid: true');

        try {
            new YamlUnitOfMeasureRegistry($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testThrowsOnInvalidUnitEntry(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid unit entry "BROKEN"/');

        $yaml    = "units:\n  BROKEN:\n    base_factor: ~\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'units_test_');
        \assert(\is_string($tmpFile));
        file_put_contents($tmpFile, $yaml);

        try {
            new YamlUnitOfMeasureRegistry($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }
}
