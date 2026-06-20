<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountCreated;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountDisabled;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountEnabled;
use App\Context\Procurement\Domain\SupplierAccount\Event\SupplierAccountUpdated;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class SupplierAccount extends AggregateRoot
{
    private function __construct(
        private SupplierAccountId $id,
        private ClinicId $clinicId,
        private SupplierId $supplierId,
        private CustomerCode $customerCode,
        private SupplierAccountStatus $status,
        private ?Address $billingAddress,
        private ?Address $defaultDeliveryAddress,
        private ?string $notes,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        SupplierAccountId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        CustomerCode $customerCode,
        ?Address $billingAddress = null,
        ?Address $defaultDeliveryAddress = null,
        ?string $notes = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $now = $createdAt ?? new \DateTimeImmutable();

        $account = new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            customerCode: $customerCode,
            status: SupplierAccountStatus::ACTIVE,
            billingAddress: $billingAddress,
            defaultDeliveryAddress: $defaultDeliveryAddress,
            notes: $notes,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $account->recordDomainEvent(new SupplierAccountCreated(
            accountId: $id->toString(),
            clinicId: $clinicId->toString(),
            supplierId: $supplierId->toString(),
            customerCode: $customerCode->toString(),
        ));

        return $account;
    }

    public static function reconstitute(
        SupplierAccountId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        CustomerCode $customerCode,
        SupplierAccountStatus $status,
        ?Address $billingAddress,
        ?Address $defaultDeliveryAddress,
        ?string $notes,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            customerCode: $customerCode,
            status: $status,
            billingAddress: $billingAddress,
            defaultDeliveryAddress: $defaultDeliveryAddress,
            notes: $notes,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(
        CustomerCode $customerCode,
        ?Address $billingAddress,
        ?Address $defaultDeliveryAddress,
        ?string $notes,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->customerCode           = $customerCode;
        $this->billingAddress         = $billingAddress;
        $this->defaultDeliveryAddress = $defaultDeliveryAddress;
        $this->notes                  = $notes;
        $this->updatedAt              = $updatedAt;

        $this->recordDomainEvent(new SupplierAccountUpdated(
            accountId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            supplierId: $this->supplierId->toString(),
        ));
    }

    public function disable(\DateTimeImmutable $updatedAt): void
    {
        $this->status    = SupplierAccountStatus::DISABLED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new SupplierAccountDisabled(
            accountId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            supplierId: $this->supplierId->toString(),
        ));
    }

    public function enable(\DateTimeImmutable $updatedAt): void
    {
        $this->status    = SupplierAccountStatus::ACTIVE;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new SupplierAccountEnabled(
            accountId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            supplierId: $this->supplierId->toString(),
        ));
    }

    public function id(): SupplierAccountId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function supplierId(): SupplierId
    {
        return $this->supplierId;
    }

    public function customerCode(): CustomerCode
    {
        return $this->customerCode;
    }

    public function status(): SupplierAccountStatus
    {
        return $this->status;
    }

    public function billingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function defaultDeliveryAddress(): ?Address
    {
        return $this->defaultDeliveryAddress;
    }

    public function notes(): ?string
    {
        return $this->notes;
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
