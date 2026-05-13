<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Money\Infrastructure\Registry;

use App\Fixtures\System\Money\Factory\CurrencyEntityFactory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Exception\UnknownCurrencyException;
use App\System\Money\Domain\Service\CurrencyRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineCurrencyRegistryTest extends KernelTestCase
{
    use Factories;

    public function testGetReturnsCurrency(): void
    {
        CurrencyEntityFactory::createOne([
            'code'        => 'EUR',
            'symbol'      => '€',
            'decimals'    => 2,
            'displayName' => 'Euro',
            'active'      => true,
        ]);

        $registry = $this->getRegistry();
        $currency = $registry->get(CurrencyCode::fromString('EUR'));

        self::assertSame('EUR', $currency->code()->toString());
        self::assertSame('€', $currency->symbol()->toString());
        self::assertSame(2, $currency->decimals()->value());
        self::assertSame('Euro', $currency->displayName());
        self::assertTrue($currency->isActive());
    }

    public function testGetThrowsUnknownCurrencyException(): void
    {
        $registry = $this->getRegistry();

        $this->expectException(UnknownCurrencyException::class);

        $registry->get(CurrencyCode::fromString('XYZ'));
    }

    public function testListActiveReturnsCurrencies(): void
    {
        CurrencyEntityFactory::createOne([
            'code'        => 'EUR',
            'symbol'      => '€',
            'decimals'    => 2,
            'displayName' => 'Euro',
            'active'      => true,
        ]);
        CurrencyEntityFactory::createOne([
            'code'        => 'CHF',
            'symbol'      => 'CHF',
            'decimals'    => 2,
            'displayName' => 'Swiss Franc',
            'active'      => true,
        ]);
        CurrencyEntityFactory::createOne([
            'code'        => 'GBP',
            'symbol'      => '£',
            'decimals'    => 2,
            'displayName' => 'British Pound',
            'active'      => false,
        ]);

        $registry = $this->getRegistry();
        $active   = $registry->listActive();

        self::assertCount(2, $active);
    }

    public function testHasReturnsTrueForKnownCurrency(): void
    {
        CurrencyEntityFactory::createOne([
            'code'        => 'EUR',
            'symbol'      => '€',
            'decimals'    => 2,
            'displayName' => 'Euro',
            'active'      => true,
        ]);

        $registry = $this->getRegistry();

        self::assertTrue($registry->has(CurrencyCode::fromString('EUR')));
    }

    public function testHasReturnsFalseForUnknownCurrency(): void
    {
        $registry = $this->getRegistry();

        self::assertFalse($registry->has(CurrencyCode::fromString('XYZ')));
    }

    private function getRegistry(): CurrencyRegistry
    {
        $registry = static::getContainer()->get(CurrencyRegistry::class);
        \assert($registry instanceof CurrencyRegistry);

        return $registry;
    }
}
