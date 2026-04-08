<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Domain\ValueObject;

use App\ClinicalCare\Domain\ValueObject\Vitals;
use PHPUnit\Framework\TestCase;

final class VitalsTest extends TestCase
{
    public function testCreateWithBothFields(): void
    {
        $vitals = Vitals::create(weightKg: 12.5, temperatureC: 38.5);

        self::assertSame(12.5, $vitals->getWeightKg());
        self::assertSame(38.5, $vitals->getTemperatureC());
        self::assertTrue($vitals->hasWeight());
        self::assertTrue($vitals->hasTemperature());
    }

    public function testCreateWithWeightOnly(): void
    {
        $vitals = Vitals::create(weightKg: 12.5);

        self::assertSame(12.5, $vitals->getWeightKg());
        self::assertNull($vitals->getTemperatureC());
        self::assertTrue($vitals->hasWeight());
        self::assertFalse($vitals->hasTemperature());
    }

    public function testCreateWithTemperatureOnly(): void
    {
        $vitals = Vitals::create(weightKg: null, temperatureC: 38.5);

        self::assertNull($vitals->getWeightKg());
        self::assertSame(38.5, $vitals->getTemperatureC());
        self::assertFalse($vitals->hasWeight());
        self::assertTrue($vitals->hasTemperature());
    }

    public function testCreateRejectsBothNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one vital sign must be provided');

        Vitals::create(null, null);
    }

    public function testCreateRejectsZeroWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be positive');

        Vitals::create(0.0, 38.0);
    }

    public function testCreateRejectsNegativeWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be positive');

        Vitals::create(-1.0, 38.0);
    }

    public function testCreateRejectsTemperatureBelow30(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 30 and 45');

        Vitals::create(10.0, 29.0);
    }

    public function testCreateRejectsTemperatureAbove45(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 30 and 45');

        Vitals::create(10.0, 46.0);
    }

    public function testCreateAcceptsTemperatureAtBoundary(): void
    {
        $low  = Vitals::create(10.0, 30.0);
        $high = Vitals::create(10.0, 45.0);

        self::assertSame(30.0, $low->getTemperatureC());
        self::assertSame(45.0, $high->getTemperatureC());
    }
}
