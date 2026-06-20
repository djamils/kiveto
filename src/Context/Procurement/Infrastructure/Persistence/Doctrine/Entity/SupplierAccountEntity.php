<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__supplier_accounts.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_sa_clinic_supplier', columns: ['clinic_id', 'supplier_id'])]
class SupplierAccountEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'supplier_id', type: UuidType::NAME)]
    private Uuid $supplierId;

    #[ORM\Column(name: 'customer_code', type: 'string', length: 64)]
    private string $customerCode;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status;

    #[ORM\Column(name: 'billing_address_json', type: 'text', nullable: true)]
    private ?string $billingAddressJson;

    #[ORM\Column(name: 'delivery_address_json', type: 'text', nullable: true)]
    private ?string $deliveryAddressJson;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getSupplierId(): Uuid
    {
        return $this->supplierId;
    }

    public function setSupplierId(Uuid $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getCustomerCode(): string
    {
        return $this->customerCode;
    }

    public function setCustomerCode(string $customerCode): void
    {
        $this->customerCode = $customerCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getBillingAddressJson(): ?string
    {
        return $this->billingAddressJson;
    }

    public function setBillingAddressJson(?string $billingAddressJson): void
    {
        $this->billingAddressJson = $billingAddressJson;
    }

    public function getDeliveryAddressJson(): ?string
    {
        return $this->deliveryAddressJson;
    }

    public function setDeliveryAddressJson(?string $deliveryAddressJson): void
    {
        $this->deliveryAddressJson = $deliveryAddressJson;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
