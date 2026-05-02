<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_client_search_entry_name', columns: ['clinic_id', 'search_name'])]
#[ORM\Index(name: 'idx_client_search_entry_phone', columns: ['clinic_id', 'search_phone'])]
class SearchEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(length: 255)]
    private string $firstName;

    #[ORM\Column(length: 255)]
    private string $lastName;

    #[ORM\Column(length: 255)]
    private string $searchName;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $searchPhone;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $primaryEmail;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $id,
        Uuid $clinicId,
        string $firstName,
        string $lastName,
        string $searchName,
        ?string $searchPhone,
        ?string $primaryEmail,
        string $status,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id           = $id;
        $this->clinicId     = $clinicId;
        $this->firstName    = $firstName;
        $this->lastName     = $lastName;
        $this->searchName   = $searchName;
        $this->searchPhone  = $searchPhone;
        $this->primaryEmail = $primaryEmail;
        $this->status       = $status;
        $this->updatedAt    = $updatedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getSearchName(): string
    {
        return $this->searchName;
    }

    public function getSearchPhone(): ?string
    {
        return $this->searchPhone;
    }

    public function getPrimaryEmail(): ?string
    {
        return $this->primaryEmail;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function setSearchName(string $searchName): void
    {
        $this->searchName = $searchName;
    }

    public function setSearchPhone(?string $searchPhone): void
    {
        $this->searchPhone = $searchPhone;
    }

    public function setPrimaryEmail(?string $primaryEmail): void
    {
        $this->primaryEmail = $primaryEmail;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
