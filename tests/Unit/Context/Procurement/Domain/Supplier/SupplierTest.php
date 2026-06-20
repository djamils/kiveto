<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\Supplier;

use App\Context\Procurement\Domain\Supplier\Event\SupplierArchived;
use App\Context\Procurement\Domain\Supplier\Event\SupplierIntegrationModeChanged;
use App\Context\Procurement\Domain\Supplier\Event\SupplierRegistered;
use App\Context\Procurement\Domain\Supplier\Event\SupplierRenamed;
use App\Context\Procurement\Domain\Supplier\Exception\ArchivedSupplierException;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierContact;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class SupplierTest extends TestCase
{
    public function testRegisterRaisesSupplierRegistered(): void
    {
        $supplier = $this->makeSupplier();
        $events   = $supplier->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierRegistered::class, $events[0]);
        self::assertSame('01932b00-0000-7000-8000-000000000001', $events[0]->aggregateId());
    }

    public function testRenameOnArchivedThrows(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();
        $now      = new \DateTimeImmutable();
        $supplier->archive($now);
        $_ = $supplier->pullDomainEvents();

        $this->expectException(ArchivedSupplierException::class);
        $supplier->rename(SupplierName::fromString('NewName'), $now);
    }

    public function testChangeIntegrationModeAutomaticWithNullAdapterThrows(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();

        $this->expectException(\InvalidArgumentException::class);
        $supplier->changeIntegrationMode(SupplierIntegrationMode::AUTOMATIC, null, new \DateTimeImmutable());
    }

    public function testChangeIntegrationModeAutomaticWithAdapterSucceeds(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();

        $supplier->changeIntegrationMode(SupplierIntegrationMode::AUTOMATIC, 'centravet_ftp', new \DateTimeImmutable());

        $events = $supplier->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierIntegrationModeChanged::class, $events[0]);
        self::assertSame(SupplierIntegrationMode::AUTOMATIC, $supplier->integrationMode());
        self::assertSame('centravet_ftp', $supplier->adapterIdentifier());
    }

    public function testArchiveTransitionsToArchived(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();
        $now      = new \DateTimeImmutable();
        $supplier->archive($now);
        $events = $supplier->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierArchived::class, $events[0]);
        self::assertSame('01932b00-0000-7000-8000-000000000001', $supplier->id()->toString());
    }

    public function testArchiveTwiceThrows(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();
        $now      = new \DateTimeImmutable();
        $supplier->archive($now);
        $_ = $supplier->pullDomainEvents();

        $this->expectException(ArchivedSupplierException::class);
        $supplier->archive($now);
    }

    public function testSupplierCodeRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierCode::fromString('a'); // too short
    }

    public function testSupplierCodeRejectsLowercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierCode::fromString('lowercase');
    }

    public function testSupplierCodeRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierCode::fromString('ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789'); // > 32
    }

    public function testRenameRaisesSupplierRenamed(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();
        $supplier->rename(SupplierName::fromString('NewName'), new \DateTimeImmutable());
        $events = $supplier->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierRenamed::class, $events[0]);
    }

    public function testUpdateContactStoresContact(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();

        $contact = SupplierContact::create('hello@example.com', '+33 1 23 45 67 89', 'Alice', null);
        $now     = new \DateTimeImmutable();
        $supplier->updateContact($contact, $now);

        self::assertSame($contact, $supplier->contact());
    }

    public function testUpdateContactWithNullClearsContact(): void
    {
        $supplier = $this->makeSupplier();
        $_        = $supplier->pullDomainEvents();

        $supplier->updateContact(null, new \DateTimeImmutable());

        self::assertNull($supplier->contact());
    }

    private function makeId(): SupplierId
    {
        return SupplierId::fromString('01932b00-0000-7000-8000-000000000001');
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: $this->makeId(),
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
