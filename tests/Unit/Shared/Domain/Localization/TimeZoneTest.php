<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Localization;

use App\Shared\Domain\Localization\TimeZone;
use PHPUnit\Framework\TestCase;

final class TimeZoneTest extends TestCase
{
    public function testFromStringWithValidIanaTimezone(): void
    {
        $timezone = TimeZone::fromString('Europe/Paris');

        $this->assertSame('Europe/Paris', $timezone->toString());
        $this->assertSame('Europe/Paris', (string) $timezone);
    }

    public function testFromStringWithUtc(): void
    {
        $timezone = TimeZone::fromString('UTC');

        $this->assertSame('UTC', $timezone->toString());
    }

    public function testFromStringWithAmericaNewYork(): void
    {
        $timezone = TimeZone::fromString('America/New_York');

        $this->assertSame('America/New_York', $timezone->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $timezone = TimeZone::fromString('  Europe/Paris  ');

        $this->assertSame('Europe/Paris', $timezone->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone cannot be empty.');

        TimeZone::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone cannot be empty.');

        TimeZone::fromString('   ');
    }

    public function testFromStringRejectsInvalidTimezone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone: "Europe/Unknown".');

        TimeZone::fromString('Europe/Unknown');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone: "Invalid/Format/Here".');

        TimeZone::fromString('Invalid/Format/Here');
    }

    public function testToNative(): void
    {
        $timezone = TimeZone::fromString('Europe/Paris');
        $native   = $timezone->toNative();

        $this->assertInstanceOf(\DateTimeZone::class, $native);
        $this->assertSame('Europe/Paris', $native->getName());
    }

    public function testEquals(): void
    {
        $timezone1 = TimeZone::fromString('Europe/Paris');
        $timezone2 = TimeZone::fromString('Europe/Paris');
        $timezone3 = TimeZone::fromString('UTC');

        $this->assertTrue($timezone1->equals($timezone2));
        $this->assertFalse($timezone1->equals($timezone3));
    }
}
