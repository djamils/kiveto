<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use PHPUnit\Framework\TestCase;

final class ClinicGroupStatusTest extends TestCase
{
    public function testAllCasesAreDefined(): void
    {
        $cases = ClinicGroupStatus::cases();

        self::assertCount(2, $cases);
        self::assertSame(ClinicGroupStatus::ACTIVE, $cases[0]);
        self::assertSame(ClinicGroupStatus::SUSPENDED, $cases[1]);
    }

    public function testValuesAreCorrect(): void
    {
        self::assertSame('active', ClinicGroupStatus::ACTIVE->value);
        self::assertSame('suspended', ClinicGroupStatus::SUSPENDED->value);
    }

    public function testFromStringCaseInsensitive(): void
    {
        self::assertSame(ClinicGroupStatus::ACTIVE, ClinicGroupStatus::from('active'));
        self::assertSame(ClinicGroupStatus::SUSPENDED, ClinicGroupStatus::from('suspended'));
    }

    public function testFromStringInvalidThrowsException(): void
    {
        $this->expectException(\ValueError::class);

        ClinicGroupStatus::from('invalid');
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(ClinicGroupStatus::tryFrom('invalid'));
    }
}
