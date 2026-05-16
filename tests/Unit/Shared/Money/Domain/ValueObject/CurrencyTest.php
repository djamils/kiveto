<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Currency;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\CurrencySymbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testOfExposesItsFields(): void
    {
        $currency = Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro',
        );

        self::assertSame('EUR', $currency->code()->toString());
        self::assertSame('€', $currency->symbol()->toString());
        self::assertSame(2, $currency->decimals()->value());
        self::assertSame('Euro', $currency->displayName());
    }

    public function testEqualsReturnsTrueForIdenticalFields(): void
    {
        $a = Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro',
        );
        $b = Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro',
        );

        self::assertTrue($a->equals($b));
    }

    #[DataProvider('provideEqualsReturnsFalseWhenAnyFieldDiffersCases')]
    public function testEqualsReturnsFalseWhenAnyFieldDiffers(Currency $other): void
    {
        $reference = Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro',
        );

        self::assertFalse($reference->equals($other));
    }

    /** @return iterable<string, array{Currency}> */
    public static function provideEqualsReturnsFalseWhenAnyFieldDiffersCases(): iterable
    {
        yield 'different code' => [Currency::of(
            CurrencyCode::fromString('USD'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro',
        )];
        yield 'different symbol' => [Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('EUR'),
            CurrencyDecimals::of(2),
            'Euro',
        )];
        yield 'different decimals' => [Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(3),
            'Euro',
        )];
        yield 'different displayName' => [Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Other',
        )];
    }
}
