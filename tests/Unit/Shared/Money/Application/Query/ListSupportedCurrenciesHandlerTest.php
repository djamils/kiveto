<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Application\Query;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Application\Query\ListSupportedCurrencies\ListSupportedCurrencies;
use App\Shared\Money\Application\Query\ListSupportedCurrencies\ListSupportedCurrenciesHandler;
use App\Shared\Money\Domain\Service\CurrencyRegistry;
use App\Shared\Money\Domain\ValueObject\Currency;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\CurrencySymbol;
use PHPUnit\Framework\TestCase;

final class ListSupportedCurrenciesHandlerTest extends TestCase
{
    public function testReturnsCurrenciesFromRegistry(): void
    {
        $eur = Currency::of(
            CurrencyCode::fromString('EUR'),
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro',
        );
        $chf = Currency::of(
            CurrencyCode::fromString('CHF'),
            CurrencySymbol::fromString('CHF'),
            CurrencyDecimals::of(2),
            'Swiss Franc',
        );

        $registry = $this->createStub(CurrencyRegistry::class);
        $registry->method('listAll')->willReturn([$eur, $chf]);

        $handler = new ListSupportedCurrenciesHandler($registry);
        $result  = $handler(new ListSupportedCurrencies());

        self::assertCount(2, $result);
        self::assertSame('EUR', $result[0]->code()->toString());
        self::assertSame('CHF', $result[1]->code()->toString());
    }
}
