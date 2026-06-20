<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Service;

use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class SupplierIntegrationDispatcherTest extends TestCase
{
    public function testDispatchByIdentifier(): void
    {
        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('centravet_ftp');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::AUTOMATIC);

        $dispatcher = new SupplierIntegrationDispatcher([$adapter]);
        $supplier   = $this->makeSupplier(SupplierIntegrationMode::AUTOMATIC, 'centravet_ftp');

        self::assertSame($adapter, $dispatcher->dispatch($supplier));
    }

    public function testDispatchByModeSimulation(): void
    {
        $simAdapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $simAdapter->method('identifier')->willReturn('simulated');
        $simAdapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);

        $dispatcher = new SupplierIntegrationDispatcher([$simAdapter]);
        $supplier   = $this->makeSupplier(SupplierIntegrationMode::SIMULATION, null);

        self::assertSame($simAdapter, $dispatcher->dispatch($supplier));
    }

    public function testDispatchThrowsWhenNoAdapterFound(): void
    {
        $dispatcher = new SupplierIntegrationDispatcher([]);
        $supplier   = $this->makeSupplier(SupplierIntegrationMode::MANUAL_EXPORT, null);

        $this->expectException(\RuntimeException::class);
        $dispatcher->dispatch($supplier);
    }

    private function makeSupplier(SupplierIntegrationMode $mode, ?string $identifier): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: $mode,
            adapterIdentifier: $identifier,
        );
    }
}
