<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\ClinicStatus;
use PHPUnit\Framework\TestCase;

final class ClinicStatusTest extends TestCase
{
    public function testAllCasesAreDefined(): void
    {
        $cases = ClinicStatus::cases();

        self::assertCount(3, $cases);
        self::assertSame(ClinicStatus::ACTIVE, $cases[0]);
        self::assertSame(ClinicStatus::SUSPENDED, $cases[1]);
        self::assertSame(ClinicStatus::CLOSED, $cases[2]);
    }

    public function testValuesAreCorrect(): void
    {
        self::assertSame('active', ClinicStatus::ACTIVE->value);
        self::assertSame('suspended', ClinicStatus::SUSPENDED->value);
        self::assertSame('closed', ClinicStatus::CLOSED->value);
    }

    public function testFromStringCaseInsensitive(): void
    {
        self::assertSame(ClinicStatus::ACTIVE, ClinicStatus::from('active'));
        self::assertSame(ClinicStatus::SUSPENDED, ClinicStatus::from('suspended'));
        self::assertSame(ClinicStatus::CLOSED, ClinicStatus::from('closed'));
    }

    public function testFromStringInvalidThrowsException(): void
    {
        $this->expectException(\ValueError::class);

        ClinicStatus::from('invalid');
    }
}
