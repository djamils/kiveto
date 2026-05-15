<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxRegimeIdTest extends TestCase
{
    #[DataProvider('provideFromStringValidCases')]
    public function testFromStringValid(string $raw): void
    {
        $id = TaxRegimeId::fromString($raw);

        self::assertSame($raw, $id->toString());
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringValidCases(): iterable
    {
        yield 'FR' => ['FR'];
        yield 'FR-DOM' => ['FR-DOM'];
        yield 'GB' => ['GB'];
    }

    #[DataProvider('provideFromStringInvalidThrowsCases')]
    public function testFromStringInvalidThrows(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TaxRegimeId::fromString($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringInvalidThrowsCases(): iterable
    {
        yield 'lowercase' => ['fr'];
        yield 'too long' => ['FRANCE'];
        yield 'single char' => ['F'];
        yield 'empty string' => [''];
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $a = TaxRegimeId::fromString('FR');
        $b = TaxRegimeId::fromString('FR');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $a = TaxRegimeId::fromString('FR');
        $b = TaxRegimeId::fromString('CH');

        self::assertFalse($a->equals($b));
    }
}
