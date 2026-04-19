<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Exception\UnsupportedRecurrencePattern;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use PHPUnit\Framework\TestCase;

final class RecurrenceRuleTest extends TestCase
{
    public function testNoneFactory(): void
    {
        $rule = RecurrenceRule::none();
        self::assertSame(RecurrenceRule::NONE, $rule->freq());
        self::assertNull($rule->until());
        self::assertFalse($rule->isRecurring());
    }

    public function testDailyFactory(): void
    {
        $rule = RecurrenceRule::daily('2026-12-31');
        self::assertSame(RecurrenceRule::DAILY, $rule->freq());
        self::assertSame('2026-12-31', $rule->until());
        self::assertTrue($rule->isRecurring());
    }

    public function testWeeklyFactory(): void
    {
        $rule = RecurrenceRule::weekly();
        self::assertSame(RecurrenceRule::WEEKLY, $rule->freq());
        self::assertNull($rule->until());
    }

    public function testWeekdaysFactory(): void
    {
        $rule = RecurrenceRule::weekdays('2026-06-30');
        self::assertSame(RecurrenceRule::WEEKDAYS, $rule->freq());
        self::assertSame('2026-06-30', $rule->until());
    }

    public function testJsonRoundtrip(): void
    {
        $rule     = RecurrenceRule::weekly('2026-12-31');
        $json     = $rule->toJson();
        $restored = RecurrenceRule::fromJson($json);

        self::assertSame($rule->freq(), $restored->freq());
        self::assertSame($rule->until(), $restored->until());
    }

    public function testNullUntilRoundtrip(): void
    {
        $rule     = RecurrenceRule::none();
        $restored = RecurrenceRule::fromJson($rule->toJson());

        self::assertNull($restored->until());
    }

    public function testInvalidFreqThrows(): void
    {
        $this->expectException(UnsupportedRecurrencePattern::class);
        RecurrenceRule::fromJson('{"freq":"MONTHLY","until":null}');
    }

    public function testMalformedJsonThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        RecurrenceRule::fromJson('{not json}');
    }

    public function testMissingFreqKeyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        RecurrenceRule::fromJson('{"until":null}');
    }
}
