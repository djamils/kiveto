<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\ValueObject\TaxRateId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxRateIdTest extends TestCase
{
    #[DataProvider('provideFromStringValidCases')]
    public function testFromStringValid(string $raw): void
    {
        $id = TaxRateId::fromString($raw);

        self::assertSame($raw, $id->toString());
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringValidCases(): iterable
    {
        yield 'fr.standard' => ['fr.standard'];
        yield 'fr.standard.2014' => ['fr.standard.2014'];
        yield 'ch.reduced' => ['ch.reduced'];
    }

    #[DataProvider('provideFromStringInvalidThrowsCases')]
    public function testFromStringInvalidThrows(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TaxRateId::fromString($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringInvalidThrowsCases(): iterable
    {
        yield 'uppercase prefix' => ['FR.standard'];
        yield 'single char prefix' => ['f.standard'];
        yield 'empty string' => [''];
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $a = TaxRateId::fromString('fr.standard');
        $b = TaxRateId::fromString('fr.standard');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $a = TaxRateId::fromString('fr.standard');
        $b = TaxRateId::fromString('fr.reduced');

        self::assertFalse($a->equals($b));
    }
}
