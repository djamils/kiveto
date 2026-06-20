<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier;

use App\Context\Procurement\Domain\Supplier\Event\SupplierArchived;
use App\Context\Procurement\Domain\Supplier\Event\SupplierIntegrationModeChanged;
use App\Context\Procurement\Domain\Supplier\Event\SupplierRegistered;
use App\Context\Procurement\Domain\Supplier\Event\SupplierRenamed;
use App\Context\Procurement\Domain\Supplier\Exception\ArchivedSupplierException;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierContact;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierStatus;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;

final class Supplier extends AggregateRoot
{
    private function __construct(
        private SupplierId $id,
        private SupplierName $name,
        private SupplierCode $code,
        private SupplierType $type,
        private CountryCode $countryCode,
        private CurrencyCode $defaultCurrency,
        private SupplierIntegrationMode $integrationMode,
        private ?string $adapterIdentifier,
        private ?SupplierContact $contact,
        private SupplierStatus $status,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function register(
        SupplierId $id,
        SupplierName $name,
        SupplierCode $code,
        SupplierType $type,
        CountryCode $countryCode,
        CurrencyCode $defaultCurrency,
        SupplierIntegrationMode $integrationMode,
        ?string $adapterIdentifier,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $now = $createdAt ?? new \DateTimeImmutable();

        $supplier = new self(
            id: $id,
            name: $name,
            code: $code,
            type: $type,
            countryCode: $countryCode,
            defaultCurrency: $defaultCurrency,
            integrationMode: $integrationMode,
            adapterIdentifier: $adapterIdentifier,
            contact: null,
            status: SupplierStatus::ACTIVE,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $supplier->recordDomainEvent(new SupplierRegistered(
            supplierId: $id->toString(),
            name: $name->toString(),
            code: $code->toString(),
            type: $type->value,
            countryCode: $countryCode->toString(),
            integrationMode: $integrationMode->value,
            adapterIdentifier: $adapterIdentifier,
        ));

        return $supplier;
    }

    public static function reconstitute(
        SupplierId $id,
        SupplierName $name,
        SupplierCode $code,
        SupplierType $type,
        CountryCode $countryCode,
        CurrencyCode $defaultCurrency,
        SupplierIntegrationMode $integrationMode,
        ?string $adapterIdentifier,
        ?SupplierContact $contact,
        SupplierStatus $status,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            code: $code,
            type: $type,
            countryCode: $countryCode,
            defaultCurrency: $defaultCurrency,
            integrationMode: $integrationMode,
            adapterIdentifier: $adapterIdentifier,
            contact: $contact,
            status: $status,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function rename(SupplierName $newName, \DateTimeImmutable $updatedAt): void
    {
        if (SupplierStatus::ARCHIVED === $this->status) {
            throw new ArchivedSupplierException($this->id->toString());
        }

        $oldName         = $this->name;
        $this->name      = $newName;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new SupplierRenamed(
            supplierId: $this->id->toString(),
            oldName: $oldName->toString(),
            newName: $newName->toString(),
        ));
    }

    public function changeIntegrationMode(SupplierIntegrationMode $newMode, ?string $adapterIdentifier, \DateTimeImmutable $updatedAt): void
    {
        if (SupplierIntegrationMode::AUTOMATIC === $newMode && null === $adapterIdentifier) {
            throw new \InvalidArgumentException('adapterIdentifier is required for AUTOMATIC integration mode.');
        }

        $this->integrationMode   = $newMode;
        $this->adapterIdentifier = $adapterIdentifier;
        $this->updatedAt         = $updatedAt;

        $this->recordDomainEvent(new SupplierIntegrationModeChanged(
            supplierId: $this->id->toString(),
            newMode: $newMode->value,
            newAdapterIdentifier: $adapterIdentifier,
        ));
    }

    public function updateContact(?SupplierContact $contact, \DateTimeImmutable $updatedAt): void
    {
        $this->contact   = $contact;
        $this->updatedAt = $updatedAt;
    }

    public function archive(\DateTimeImmutable $updatedAt): void
    {
        if (SupplierStatus::ARCHIVED === $this->status) {
            throw new ArchivedSupplierException($this->id->toString());
        }

        $this->status    = SupplierStatus::ARCHIVED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new SupplierArchived(supplierId: $this->id->toString()));
    }

    public function id(): SupplierId
    {
        return $this->id;
    }

    public function name(): SupplierName
    {
        return $this->name;
    }

    public function code(): SupplierCode
    {
        return $this->code;
    }

    public function type(): SupplierType
    {
        return $this->type;
    }

    public function countryCode(): CountryCode
    {
        return $this->countryCode;
    }

    public function defaultCurrency(): CurrencyCode
    {
        return $this->defaultCurrency;
    }

    public function integrationMode(): SupplierIntegrationMode
    {
        return $this->integrationMode;
    }

    public function adapterIdentifier(): ?string
    {
        return $this->adapterIdentifier;
    }

    public function contact(): ?SupplierContact
    {
        return $this->contact;
    }

    public function status(): SupplierStatus
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
