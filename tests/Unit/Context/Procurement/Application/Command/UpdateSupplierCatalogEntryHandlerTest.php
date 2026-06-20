<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\UpdateSupplierCatalogEntry\UpdateSupplierCatalogEntry;
use App\Context\Procurement\Application\Command\UpdateSupplierCatalogEntry\UpdateSupplierCatalogEntryHandler;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Exception\SupplierCatalogEntryNotFoundException;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class UpdateSupplierCatalogEntryHandlerTest extends TestCase
{
    private const string ENTRY_UUID    = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItUpdatesEntry(): void
    {
        $entry = $this->makeEntry();
        $_     = $entry->pullDomainEvents();

        $repository = $this->createMock(SupplierCatalogEntryRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new UpdateSupplierCatalogEntry(
            entryId: self::ENTRY_UUID,
            name: 'Updated name',
            gtin: null,
            packagingUnit: null,
            packagingAmount: null,
        ));

        self::assertSame('Updated name', $entry->name()->toString());
    }

    public function testItThrowsWhenEntryNotFound(): void
    {
        $repository = $this->createStub(SupplierCatalogEntryRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(SupplierCatalogEntryNotFoundException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new UpdateSupplierCatalogEntry(
            entryId: self::ENTRY_UUID,
            name: 'Updated',
            gtin: null,
            packagingUnit: null,
            packagingAmount: null,
        ));
    }

    private function makeHandler(
        SupplierCatalogEntryRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): UpdateSupplierCatalogEntryHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new UpdateSupplierCatalogEntryHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $clock,
            $em,
        );
    }

    private function makeEntry(): SupplierCatalogEntry
    {
        return SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString(self::ENTRY_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            productCode: SupplierProductCode::fromString('PROD-001'),
            name: SupplierProductName::fromString('Original name'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2024-01-01'),
            ),
        );
    }
}
