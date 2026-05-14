<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Application\Query;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Query\ListSupportedCurrencies\ListSupportedCurrencies;
use App\System\Money\Application\Query\ListSupportedCurrencies\ListSupportedCurrenciesHandler;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use PHPUnit\Framework\TestCase;

final class ListSupportedCurrenciesHandlerTest extends TestCase
{
    public function testReturnsCurrenciesFromRegistry(): void
    {
        $now = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');

        $eur = Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );
        $chf = Currency::reconstitute(
            code: CurrencyCode::fromString('CHF'),
            symbol: CurrencySymbol::fromString('CHF'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Swiss Franc',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $registry = $this->createStub(CurrencyRegistry::class);
        $registry->method('listActive')->willReturn([$eur, $chf]);

        $handler = new ListSupportedCurrenciesHandler($registry);
        $result  = $handler(new ListSupportedCurrencies());

        self::assertCount(2, $result);
        self::assertSame('EUR', $result[0]->code()->toString());
        self::assertSame('CHF', $result[1]->code()->toString());
    }
}
