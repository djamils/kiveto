<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Infrastructure\Registry;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Exception\UnknownCurrencyException;
use App\Shared\Money\Infrastructure\Registry\YamlCurrencyRegistry;
use PHPUnit\Framework\TestCase;

final class YamlCurrencyRegistryTest extends TestCase
{
    private string $catalogPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'currencies_');
        \assert(false !== $path);
        $this->catalogPath = $path;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->catalogPath)) {
            unlink($this->catalogPath);
        }
    }

    public function testGetReturnsCurrency(): void
    {
        $this->writeCatalog(<<<'YAML'
            currencies:
              EUR:
                symbol: '€'
                decimals: 2
                displayName: 'Euro'
              CHF:
                symbol: 'CHF'
                decimals: 2
                displayName: 'Franc suisse'
            YAML);

        $registry = new YamlCurrencyRegistry($this->catalogPath);
        $currency = $registry->get(CurrencyCode::fromString('EUR'));

        self::assertSame('EUR', $currency->code()->toString());
        self::assertSame('€', $currency->symbol()->toString());
        self::assertSame(2, $currency->decimals()->value());
        self::assertSame('Euro', $currency->displayName());
    }

    public function testGetThrowsUnknownCurrencyException(): void
    {
        $this->writeCatalog(<<<'YAML'
            currencies:
              EUR:
                symbol: '€'
                decimals: 2
                displayName: 'Euro'
            YAML);

        $registry = new YamlCurrencyRegistry($this->catalogPath);

        $this->expectException(UnknownCurrencyException::class);

        $registry->get(CurrencyCode::fromString('XYZ'));
    }

    public function testListAllReturnsAllCurrenciesInOrder(): void
    {
        $this->writeCatalog(<<<'YAML'
            currencies:
              EUR:
                symbol: '€'
                decimals: 2
                displayName: 'Euro'
              CHF:
                symbol: 'CHF'
                decimals: 2
                displayName: 'Franc suisse'
              GBP:
                symbol: '£'
                decimals: 2
                displayName: 'Livre sterling'
            YAML);

        $registry = new YamlCurrencyRegistry($this->catalogPath);
        $all      = $registry->listAll();

        self::assertCount(3, $all);
        self::assertSame('EUR', $all[0]->code()->toString());
        self::assertSame('CHF', $all[1]->code()->toString());
        self::assertSame('GBP', $all[2]->code()->toString());
    }

    public function testHasReturnsTrueForKnownCurrency(): void
    {
        $this->writeCatalog(<<<'YAML'
            currencies:
              EUR:
                symbol: '€'
                decimals: 2
                displayName: 'Euro'
            YAML);

        $registry = new YamlCurrencyRegistry($this->catalogPath);

        self::assertTrue($registry->has(CurrencyCode::fromString('EUR')));
    }

    public function testHasReturnsFalseForUnknownCurrency(): void
    {
        $this->writeCatalog(<<<'YAML'
            currencies:
              EUR:
                symbol: '€'
                decimals: 2
                displayName: 'Euro'
            YAML);

        $registry = new YamlCurrencyRegistry($this->catalogPath);

        self::assertFalse($registry->has(CurrencyCode::fromString('XYZ')));
    }

    public function testRejectsCatalogMissingCurrenciesKey(): void
    {
        $this->writeCatalog("foo: bar\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing "currencies" key');

        new YamlCurrencyRegistry($this->catalogPath);
    }

    public function testRejectsInvalidCurrencyEntry(): void
    {
        $this->writeCatalog(<<<'YAML'
            currencies:
              EUR:
                symbol: '€'
                decimals: 'two'
                displayName: 'Euro'
            YAML);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid currency entry "EUR"');

        new YamlCurrencyRegistry($this->catalogPath);
    }

    private function writeCatalog(string $yaml): void
    {
        file_put_contents($this->catalogPath, $yaml);
    }
}
