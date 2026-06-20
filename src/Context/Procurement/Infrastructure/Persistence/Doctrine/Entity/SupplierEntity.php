<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__suppliers.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_supplier_code', columns: ['code'])]
#[ORM\Index(name: 'idx_supplier_status', columns: ['status'])]
#[ORM\Index(name: 'idx_supplier_name_fulltext', columns: ['name'], flags: ['fulltext'])]
class SupplierEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 32)]
    private string $code;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    #[ORM\Column(name: 'country_code', type: 'string', length: 3)]
    private string $countryCode;

    #[ORM\Column(name: 'default_currency', type: 'string', length: 3)]
    private string $defaultCurrency;

    #[ORM\Column(name: 'integration_mode', type: 'string', length: 32)]
    private string $integrationMode;

    #[ORM\Column(name: 'adapter_identifier', type: 'string', length: 64, nullable: true)]
    private ?string $adapterIdentifier;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'contact_email', type: 'string', length: 255, nullable: true)]
    private ?string $contactEmail;

    #[ORM\Column(name: 'contact_phone', type: 'string', length: 64, nullable: true)]
    private ?string $contactPhone;

    #[ORM\Column(name: 'contact_person', type: 'string', length: 255, nullable: true)]
    private ?string $contactPerson;

    #[ORM\Column(name: 'address_street', type: 'string', length: 255, nullable: true)]
    private ?string $contactAddressStreet;

    #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
    private ?string $contactAddressLine2;

    #[ORM\Column(name: 'postal_code', type: 'string', length: 16, nullable: true)]
    private ?string $contactPostalCode;

    #[ORM\Column(name: 'contact_city', type: 'string', length: 128, nullable: true)]
    private ?string $contactCity;

    #[ORM\Column(name: 'address_country_code', type: 'string', length: 3, nullable: true)]
    private ?string $contactAddressCountryCode;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function setDefaultCurrency(string $defaultCurrency): void
    {
        $this->defaultCurrency = $defaultCurrency;
    }

    public function getIntegrationMode(): string
    {
        return $this->integrationMode;
    }

    public function setIntegrationMode(string $integrationMode): void
    {
        $this->integrationMode = $integrationMode;
    }

    public function getAdapterIdentifier(): ?string
    {
        return $this->adapterIdentifier;
    }

    public function setAdapterIdentifier(?string $adapterIdentifier): void
    {
        $this->adapterIdentifier = $adapterIdentifier;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): void
    {
        $this->contactEmail = $contactEmail;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): void
    {
        $this->contactPhone = $contactPhone;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): void
    {
        $this->contactPerson = $contactPerson;
    }

    public function getContactAddressStreet(): ?string
    {
        return $this->contactAddressStreet;
    }

    public function setContactAddressStreet(?string $contactAddressStreet): void
    {
        $this->contactAddressStreet = $contactAddressStreet;
    }

    public function getContactAddressLine2(): ?string
    {
        return $this->contactAddressLine2;
    }

    public function setContactAddressLine2(?string $contactAddressLine2): void
    {
        $this->contactAddressLine2 = $contactAddressLine2;
    }

    public function getContactPostalCode(): ?string
    {
        return $this->contactPostalCode;
    }

    public function setContactPostalCode(?string $contactPostalCode): void
    {
        $this->contactPostalCode = $contactPostalCode;
    }

    public function getContactCity(): ?string
    {
        return $this->contactCity;
    }

    public function setContactCity(?string $contactCity): void
    {
        $this->contactCity = $contactCity;
    }

    public function getContactAddressCountryCode(): ?string
    {
        return $this->contactAddressCountryCode;
    }

    public function setContactAddressCountryCode(?string $contactAddressCountryCode): void
    {
        $this->contactAddressCountryCode = $contactAddressCountryCode;
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
