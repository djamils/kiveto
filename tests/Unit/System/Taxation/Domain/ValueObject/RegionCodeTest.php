<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\ValueObject\RegionCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegionCodeTest extends TestCase
{
    #[DataProvider('provideFromStringValidCases')]
    public function testFromStringValid(string $raw): void
    {
        $code = RegionCode::fromString($raw);

        self::assertSame($raw, $code->toString());
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringValidCases(): iterable
    {
        yield 'DOM' => ['DOM'];
        yield 'IDF' => ['IDF'];
    }

    #[DataProvider('provideFromStringInvalidThrowsCases')]
    public function testFromStringInvalidThrows(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RegionCode::fromString($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringInvalidThrowsCases(): iterable
    {
        yield 'empty string' => [''];
        yield 'lowercase' => ['dom'];
        yield 'too long (>16)' => ['TOOLONGREGIONCODE123'];
    }

    public function testEqualsReturnsTrueForSameCode(): void
    {
        $a = RegionCode::fromString('DOM');
        $b = RegionCode::fromString('DOM');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCode(): void
    {
        $a = RegionCode::fromString('DOM');
        $b = RegionCode::fromString('IDF');

        self::assertFalse($a->equals($b));
    }
}
