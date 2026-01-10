<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\TimeZone;
use PHPUnit\Framework\TestCase;

final class TimeZoneTest extends TestCase
{
    public function testFromStringWithValidTimezone(): void
    {
        $tz = TimeZone::fromString('Europe/Paris');

        self::assertSame('Europe/Paris', $tz->toString());
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone cannot be empty');

        TimeZone::fromString('');
    }

    public function testFromStringRejectsInvalidTimezone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone');

        TimeZone::fromString('Invalid/Timezone');
    }

    public function testEquals(): void
    {
        $tzA = TimeZone::fromString('Europe/Paris');
        $tzB = TimeZone::fromString('Europe/Paris');
        $tzC = TimeZone::fromString('America/New_York');

        self::assertTrue($tzA->equals($tzB));
        self::assertFalse($tzA->equals($tzC));
    }
}
