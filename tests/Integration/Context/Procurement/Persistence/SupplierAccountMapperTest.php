<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierAccountMapper;
use App\Shared\Domain\ValueObject\CountryCode;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP round-trip test for SupplierAccountMapper.
 * Verifies that billingAddress and defaultDeliveryAddress survive JSON serialisation.
 */
final class SupplierAccountMapperTest extends TestCase
{
    private SupplierAccountMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SupplierAccountMapper();
    }

    public function testRoundTripWithoutAddresses(): void
    {
        $now     = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $account = SupplierAccount::reconstitute(
            id: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000010'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            customerCode: CustomerCode::fromString('CUST-001'),
            status: SupplierAccountStatus::ACTIVE,
            billingAddress: null,
            defaultDeliveryAddress: null,
            notes: 'Some notes',
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($account);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($account->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($account->clinicId()->toString(), $reconstituted->clinicId()->toString());
        self::assertSame($account->supplierId()->toString(), $reconstituted->supplierId()->toString());
        self::assertSame('CUST-001', $reconstituted->customerCode()->toString());
        self::assertSame(SupplierAccountStatus::ACTIVE, $reconstituted->status());
        self::assertNull($reconstituted->billingAddress());
        self::assertNull($reconstituted->defaultDeliveryAddress());
        self::assertSame('Some notes', $reconstituted->notes());
        self::assertSame(1, $reconstituted->version());
    }

    public function testRoundTripWithBillingAndDeliveryAddresses(): void
    {
        $now            = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $billingAddress = Address::create(
            '10 rue de Rivoli',
            'Bâtiment B',
            '75001',
            'Paris',
            CountryCode::fromString('FR'),
        );
        $deliveryAddress = Address::create(
            '5 avenue des Champs',
            null,
            '75008',
            'Paris',
            CountryCode::fromString('FR'),
        );

        $account = SupplierAccount::reconstitute(
            id: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000011'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            customerCode: CustomerCode::fromString('CUST-002'),
            status: SupplierAccountStatus::ACTIVE,
            billingAddress: $billingAddress,
            defaultDeliveryAddress: $deliveryAddress,
            notes: null,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($account);
        $reconstituted = $this->mapper->toDomain($entity);

        // Verify billing address JSON round-trip
        $billing = $reconstituted->billingAddress();
        self::assertNotNull($billing);
        self::assertSame('10 rue de Rivoli', $billing->street);
        self::assertSame('Bâtiment B', $billing->addressLine2);
        self::assertSame('75001', $billing->postalCode);
        self::assertSame('Paris', $billing->city);
        self::assertSame('FR', $billing->countryCode?->toString());

        // Verify delivery address JSON round-trip
        $delivery = $reconstituted->defaultDeliveryAddress();
        self::assertNotNull($delivery);
        self::assertSame('5 avenue des Champs', $delivery->street);
        self::assertNull($delivery->addressLine2);
        self::assertSame('75008', $delivery->postalCode);
        self::assertSame('Paris', $delivery->city);
        self::assertSame('FR', $delivery->countryCode?->toString());

        self::assertNull($reconstituted->notes());
        self::assertSame(1, $reconstituted->version());
    }

    public function testRoundTripWithDisabledStatus(): void
    {
        $now     = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $account = SupplierAccount::reconstitute(
            id: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000012'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            customerCode: CustomerCode::fromString('CUST-003'),
            status: SupplierAccountStatus::DISABLED,
            billingAddress: null,
            defaultDeliveryAddress: null,
            notes: null,
            version: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($account);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(SupplierAccountStatus::DISABLED, $reconstituted->status());
    }
}
