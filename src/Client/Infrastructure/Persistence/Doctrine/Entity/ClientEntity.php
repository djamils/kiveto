<?php

declare(strict_types=1);

namespace App\Client\Infrastructure\Persistence\Doctrine\Entity;

use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_client_clinic_id', columns: ['clinic_id'])]
#[ORM\Index(name: 'idx_client_status', columns: ['status'])]
#[ORM\Index(name: 'idx_client_created_at', columns: ['created_at'])]
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

    #[ORM\Embedded(class: PostalAddressEmbeddable::class, columnPrefix: false)]
    private PostalAddressEmbeddable $postalAddress;

    #[ORM\Column(type: 'string', length: 20, enumType: ClientStatus::class)]
    private ClientStatus $status;

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
