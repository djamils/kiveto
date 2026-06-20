<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\ArchiveSupplier\ArchiveSupplier;
use App\Context\Procurement\Application\Command\ArchiveSupplier\ArchiveSupplierHandler;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierStatus;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ArchiveSupplierHandlerTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItArchivesSupplier(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();

        $repository = $this->createMock(SupplierRepositoryInterface::class);
        $repository->method('findById')->willReturn($supplier);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new ArchiveSupplier(supplierId: self::SUPPLIER_UUID));

        self::assertSame(SupplierStatus::ARCHIVED, $supplier->status());
    }

    public function testItThrowsWhenSupplierNotFound(): void
    {
        $repository = $this->createStub(SupplierRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(SupplierNotFoundException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new ArchiveSupplier(supplierId: self::SUPPLIER_UUID));
    }

    private function makeHandler(
        SupplierRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): ArchiveSupplierHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new ArchiveSupplierHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $clock,
            $em,
        );
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: null,
        );
    }
}
