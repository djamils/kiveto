<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\Exception\InvalidTaxCategoryFormatException;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxCategoryCodeTest extends TestCase
{
    public function testFromStringValid(): void
    {
        $code = TaxCategoryCode::fromString('veterinary.act.consultation');

        self::assertSame('veterinary.act.consultation', $code->toString());
    }

    #[DataProvider('provideFromStringInvalidThrowsCases')]
    public function testFromStringInvalidThrows(string $raw): void
    {
        $this->expectException(InvalidTaxCategoryFormatException::class);

        TaxCategoryCode::fromString($raw);
    }

    /** @return iterable<string, array{string}> */
    public static function provideFromStringInvalidThrowsCases(): iterable
    {
        yield 'uppercase start' => ['Foo'];
        yield 'single segment' => ['foo'];
        yield 'uppercase second' => ['foo.BAR'];
        yield 'empty string' => [''];
        yield 'trailing dot' => ['foo.'];
    }

    public function testEqualsSameCode(): void
    {
        $a = TaxCategoryCode::fromString('veterinary.act.consultation');
        $b = TaxCategoryCode::fromString('veterinary.act.consultation');

        self::assertTrue($a->equals($b));
    }

    public function testEqualsDifferentCode(): void
    {
        $a = TaxCategoryCode::fromString('veterinary.act.consultation');
        $b = TaxCategoryCode::fromString('veterinary.drug.companion');

        self::assertFalse($a->equals($b));
    }
}
