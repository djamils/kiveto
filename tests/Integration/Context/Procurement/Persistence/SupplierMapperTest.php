<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierContact;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierStatus;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierMapper;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP round-trip test for SupplierMapper — no database required.
 */
final class SupplierMapperTest extends TestCase
{
    private SupplierMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SupplierMapper();
    }

    public function testRoundTripWithMinimalFields(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $supplier = Supplier::register(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: 'simulated',
            createdAt: $now,
        );
        $_ = $supplier->pullDomainEvents();

        $entity        = $this->mapper->toEntity($supplier);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($supplier->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($supplier->name()->toString(), $reconstituted->name()->toString());
        self::assertSame($supplier->code()->toString(), $reconstituted->code()->toString());
        self::assertSame($supplier->type(), $reconstituted->type());
        self::assertSame($supplier->countryCode()->toString(), $reconstituted->countryCode()->toString());
        self::assertSame($supplier->defaultCurrency()->toString(), $reconstituted->defaultCurrency()->toString());
        self::assertSame($supplier->integrationMode(), $reconstituted->integrationMode());
        self::assertSame($supplier->adapterIdentifier(), $reconstituted->adapterIdentifier());
        self::assertSame($supplier->status(), $reconstituted->status());
        self::assertNull($reconstituted->contact());
        self::assertSame($now->getTimestamp(), $reconstituted->createdAt()->getTimestamp());
        self::assertSame($now->getTimestamp(), $reconstituted->updatedAt()->getTimestamp());
    }

    public function testRoundTripWithContact(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $supplier = Supplier::reconstitute(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000002'),
            name: SupplierName::fromString('Alcyon'),
            code: SupplierCode::fromString('ALCYON'),
            type: SupplierType::LABORATORY,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
            contact: SupplierContact::create(
                'alcyon@example.com',
                '+33123456789',
                'Jean Dupont',
                Address::create('1 rue de la Paix', null, '75001', 'Paris', CountryCode::fromString('FR')),
            ),
            status: SupplierStatus::ACTIVE,
            version: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($supplier);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($supplier->id()->toString(), $reconstituted->id()->toString());

        $contact = $reconstituted->contact();
        self::assertNotNull($contact);
        self::assertSame('alcyon@example.com', $contact->email);
        self::assertSame('+33123456789', $contact->phone);
        self::assertSame('Jean Dupont', $contact->contactPerson);

        $contactAddress = $contact->address;
        self::assertNotNull($contactAddress);
        self::assertSame('1 rue de la Paix', $contactAddress->street);
        self::assertSame('75001', $contactAddress->postalCode);
        self::assertSame('Paris', $contactAddress->city);

        $addressCountryCode = $contactAddress->countryCode;
        self::assertNotNull($addressCountryCode);
        self::assertSame('FR', $addressCountryCode->toString());

        self::assertNull($reconstituted->adapterIdentifier());
    }

    public function testRoundTripWithContactAddressWithoutCountryCode(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $supplier = Supplier::reconstitute(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000004'),
            name: SupplierName::fromString('NoCountry'),
            code: SupplierCode::fromString('NOCO'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
            contact: SupplierContact::create(
                'a@b.com',
                null,
                null,
                // Address with a street but no country code
                Address::create('Some Street', null, null, null, null),
            ),
            status: SupplierStatus::ACTIVE,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($supplier);
        $reconstituted = $this->mapper->toDomain($entity);

        $contact = $reconstituted->contact();
        self::assertNotNull($contact);
        self::assertNotNull($contact->address);
        self::assertNull($contact->address->countryCode);
    }

    public function testRoundTripWithArchivedStatus(): void
    {
        $now      = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $supplier = Supplier::reconstitute(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000003'),
            name: SupplierName::fromString('OldSupplier'),
            code: SupplierCode::fromString('OLD'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
            contact: null,
            status: SupplierStatus::ARCHIVED,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($supplier);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(SupplierStatus::ARCHIVED, $reconstituted->status());
        self::assertSame(1, $reconstituted->version());
    }
}
