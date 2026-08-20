<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation\Twig;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Service\CurrencyRegistry;
use App\Shared\Money\Domain\ValueObject\Currency;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\CurrencySymbol;
use App\Shared\Presentation\Twig\MoneyFormatRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyFormatRuntimeTest extends TestCase
{
    private MoneyFormatRuntime $runtime;

    protected function setUp(): void
    {
        $currencies = [
            'EUR' => Currency::of(
                code: CurrencyCode::fromString('EUR'),
                symbol: CurrencySymbol::fromString('€'),
                decimals: CurrencyDecimals::of(2),
                displayName: 'Euro',
            ),
            'CHF' => Currency::of(
                code: CurrencyCode::fromString('CHF'),
                symbol: CurrencySymbol::fromString('CHF'),
                decimals: CurrencyDecimals::of(2),
                displayName: 'Swiss Franc',
            ),
            'JPY' => Currency::of(
                code: CurrencyCode::fromString('JPY'),
                symbol: CurrencySymbol::fromString('¥'),
                decimals: CurrencyDecimals::of(0),
                displayName: 'Yen',
            ),
        ];

        $registry = $this->createStub(CurrencyRegistry::class);
        $registry->method('has')->willReturnCallback(
            static fn (CurrencyCode $code) => isset($currencies[$code->toString()]),
        );
        $registry->method('get')->willReturnCallback(
            static fn (CurrencyCode $code) => $currencies[$code->toString()],
        );

        $this->runtime = new MoneyFormatRuntime($registry);
    }

    #[DataProvider('provideMoneyFormatCases')]
    public function testMoneyFormat(int $minorUnits, string $currency, string $expected): void
    {
        self::assertSame($expected, $this->runtime->moneyFormat($minorUnits, $currency));
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function provideMoneyFormatCases(): iterable
    {
        yield 'simple amount' => [1480, 'EUR', "14,80\u{00A0}€"];
        yield 'zero' => [0, 'EUR', "0,00\u{00A0}€"];
        yield 'single cent' => [5, 'EUR', "0,05\u{00A0}€"];
        yield 'thousands separator' => [123456, 'EUR', "1\u{202F}234,56\u{00A0}€"];
        yield 'negative amount' => [-2550, 'EUR', "-25,50\u{00A0}€"];
        yield 'registry symbol kept as code' => [1000, 'CHF', "10,00\u{00A0}CHF"];
        yield 'zero-decimal currency' => [1234, 'JPY', "1\u{202F}234\u{00A0}¥"];
        yield 'unregistered currency falls back to code' => [1000, 'SEK', "10,00\u{00A0}SEK"];
        yield 'malformed currency code falls back' => [1000, 'nope!', "10,00\u{00A0}nope!"];
    }

    public function testReturnsEmptyStringForNonIntValue(): void
    {
        self::assertSame('', $this->runtime->moneyFormat('not-an-int', 'EUR'));
        self::assertSame('', $this->runtime->moneyFormat(null, 'EUR'));
        self::assertSame('', $this->runtime->moneyFormat(14.80, 'EUR'));
    }
}
