<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'client__clients')]
#[ORM\Index(name: 'idx_client_clinic_id', columns: ['clinic_id'])]
#[ORM\Index(name: 'idx_client_status', columns: ['status'])]
#[ORM\Index(name: 'idx_client_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_client_clinic_siren', columns: ['clinic_id', 'siren'])]
#[ORM\Index(name: 'idx_client_tax_checked', columns: ['tax_company_checked_at'])]
class ClientEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $lastName;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'unspecified'])]
    private string $civility = 'unspecified';

    #[ORM\Column(type: 'string', length: 20, enumType: ClientTaxStatus::class, options: ['default' => 'individual'])]
    private ClientTaxStatus $taxStatus = ClientTaxStatus::INDIVIDUAL;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $legalName = null;

    #[ORM\Column(type: 'string', length: 9, nullable: true, options: ['fixed' => true])]
    private ?string $siren = null;

    #[ORM\Column(type: 'string', length: 13, nullable: true)]
    private ?string $vatNumber = null;

    // Tax profile verification; a null outcome means "not checked yet".
    #[ORM\Column(type: 'string', length: 20, nullable: true, enumType: CompanyCheckOutcome::class)]
    private ?CompanyCheckOutcome $taxCompanyCheckOutcome = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $taxCompanyRegistryName = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $taxCompanyCheckedAt = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true, enumType: VatCheckOutcome::class)]
    private ?VatCheckOutcome $taxVatCheckOutcome = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $taxVatCheckedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $taxVerificationRequestedAt = null;

    #[ORM\Embedded(class: PostalAddressEmbeddable::class, columnPrefix: false)]
    private PostalAddressEmbeddable $postalAddress;

    #[ORM\Column(type: 'string', length: 20, enumType: ClientStatus::class)]
    private ClientStatus $status;

    /**
     * @var array{
     *     civility: string,
     *     firstName: string,
     *     lastName: string,
     *     relationship: string,
     *     phoneNumber: string|null,
     *     authorizedAt: string
     * }|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $authorizedPerson = null;

    /**
     * @var array{
     *     preferredChannel: string|null,
     *     consents: list<array{kind: string, channels: list<string>, grantedAt: string}>
     * }|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $communicationPreferences = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->postalAddress = new PostalAddressEmbeddable();
    }

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

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getCivility(): string
    {
        return $this->civility;
    }

    public function setCivility(string $civility): void
    {
        $this->civility = $civility;
    }

    public function getTaxStatus(): ClientTaxStatus
    {
        return $this->taxStatus;
    }

    public function setTaxStatus(ClientTaxStatus $taxStatus): void
    {
        $this->taxStatus = $taxStatus;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function setLegalName(?string $legalName): void
    {
        $this->legalName = $legalName;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): void
    {
        $this->siren = $siren;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $vatNumber;
    }

    public function getTaxCompanyCheckOutcome(): ?CompanyCheckOutcome
    {
        return $this->taxCompanyCheckOutcome;
    }

    public function setTaxCompanyCheckOutcome(?CompanyCheckOutcome $outcome): void
    {
        $this->taxCompanyCheckOutcome = $outcome;
    }

    public function getTaxCompanyRegistryName(): ?string
    {
        return $this->taxCompanyRegistryName;
    }

    public function setTaxCompanyRegistryName(?string $registryName): void
    {
        $this->taxCompanyRegistryName = $registryName;
    }

    public function getTaxCompanyCheckedAt(): ?\DateTimeImmutable
    {
        return $this->taxCompanyCheckedAt;
    }

    public function setTaxCompanyCheckedAt(?\DateTimeImmutable $checkedAt): void
    {
        $this->taxCompanyCheckedAt = $checkedAt;
    }

    public function getTaxVatCheckOutcome(): ?VatCheckOutcome
    {
        return $this->taxVatCheckOutcome;
    }

    public function setTaxVatCheckOutcome(?VatCheckOutcome $outcome): void
    {
        $this->taxVatCheckOutcome = $outcome;
    }

    public function getTaxVatCheckedAt(): ?\DateTimeImmutable
    {
        return $this->taxVatCheckedAt;
    }

    public function setTaxVatCheckedAt(?\DateTimeImmutable $checkedAt): void
    {
        $this->taxVatCheckedAt = $checkedAt;
    }

    public function getTaxVerificationRequestedAt(): ?\DateTimeImmutable
    {
        return $this->taxVerificationRequestedAt;
    }

    public function setTaxVerificationRequestedAt(?\DateTimeImmutable $requestedAt): void
    {
        $this->taxVerificationRequestedAt = $requestedAt;
    }

    public function getPostalAddress(): PostalAddressEmbeddable
    {
        return $this->postalAddress;
    }

    public function setPostalAddress(PostalAddressEmbeddable $postalAddress): void
    {
        $this->postalAddress = $postalAddress;
    }

    public function getStatus(): ClientStatus
    {
        return $this->status;
    }

    public function setStatus(ClientStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * @return array{
     *     civility: string,
     *     firstName: string,
     *     lastName: string,
     *     relationship: string,
     *     phoneNumber: string|null,
     *     authorizedAt: string
     * }|null
     */
    public function getAuthorizedPerson(): ?array
    {
        return $this->authorizedPerson;
    }

    /**
     * @param array{
     *     civility: string,
     *     firstName: string,
     *     lastName: string,
     *     relationship: string,
     *     phoneNumber: string|null,
     *     authorizedAt: string
     * }|null $authorizedPerson
     */
    public function setAuthorizedPerson(?array $authorizedPerson): void
    {
        $this->authorizedPerson = $authorizedPerson;
    }

    /**
     * @return array{
     *     preferredChannel: string|null,
     *     consents: list<array{kind: string, channels: list<string>, grantedAt: string}>
     * }|null
     */
    public function getCommunicationPreferences(): ?array
    {
        return $this->communicationPreferences;
    }

    /**
     * @param array{
     *     preferredChannel: string|null,
     *     consents: list<array{kind: string, channels: list<string>, grantedAt: string}>
     * }|null $communicationPreferences
     */
    public function setCommunicationPreferences(?array $communicationPreferences): void
    {
        $this->communicationPreferences = $communicationPreferences;
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

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
