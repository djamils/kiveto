<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierAccount;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountCreated;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountDisabled;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountEnabled;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountUpdated;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use PHPUnit\Framework\TestCase;

final class SupplierAccountTest extends TestCase
{
    public function testCreateRaisesSupplierAccountCreated(): void
    {
        $account = $this->makeAccount();
        $events  = $account->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierAccountCreated::class, $events[0]);
    }

    public function testDisableRaisesSupplierAccountDisabled(): void
    {
        $account = $this->makeAccount();
        $_       = $account->pullDomainEvents();
        $account->disable(new \DateTimeImmutable());
        $events = $account->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierAccountDisabled::class, $events[0]);
        self::assertSame(SupplierAccountStatus::DISABLED, $account->status());
    }

    public function testEnableRaisesSupplierAccountEnabled(): void
    {
        $account = $this->makeAccount();
        $_       = $account->pullDomainEvents();
        $account->disable(new \DateTimeImmutable());
        $_ = $account->pullDomainEvents();
        $account->enable(new \DateTimeImmutable());
        $events = $account->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierAccountEnabled::class, $events[0]);
        self::assertSame(SupplierAccountStatus::ACTIVE, $account->status());
    }

    public function testUpdateRaisesSupplierAccountUpdated(): void
    {
        $account = $this->makeAccount();
        $_       = $account->pullDomainEvents();
        $account->update(
            customerCode: CustomerCode::fromString('NEW-CODE'),
            billingAddress: null,
            defaultDeliveryAddress: null,
            notes: 'Test',
            updatedAt: new \DateTimeImmutable(),
        );
        $events = $account->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierAccountUpdated::class, $events[0]);
    }

    private function makeAccount(): SupplierAccount
    {
        return SupplierAccount::create(
            id: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000002'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            customerCode: CustomerCode::fromString('CVT-12345'),
        );
    }
}
