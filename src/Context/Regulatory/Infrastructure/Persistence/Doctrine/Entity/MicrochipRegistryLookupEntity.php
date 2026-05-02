<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
class MicrochipRegistryLookupEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'chip_number', type: 'string', length: 64)]
    private string $chipNumber;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 16, enumType: MicrochipRegistryLookupStatus::class)]
    private MicrochipRegistryLookupStatus $status;

    #[ORM\Column(name: 'icad_animal_data', type: 'text', nullable: true)]
    private ?string $icadAnimalData = null;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'initiated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $initiatedAt;

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

    public function getChipNumber(): string
    {
        return $this->chipNumber;
    }

    public function setChipNumber(string $chipNumber): void
    {
        $this->chipNumber = $chipNumber;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getStatus(): MicrochipRegistryLookupStatus
    {
        return $this->status;
    }

    public function setStatus(MicrochipRegistryLookupStatus $status): void
    {
        $this->status = $status;
    }

    public function getIcadAnimalData(): ?string
    {
        return $this->icadAnimalData;
    }

    public function setIcadAnimalData(?string $icadAnimalData): void
    {
        $this->icadAnimalData = $icadAnimalData;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getInitiatedAt(): \DateTimeImmutable
    {
        return $this->initiatedAt;
    }

    public function setInitiatedAt(\DateTimeImmutable $initiatedAt): void
    {
        $this->initiatedAt = $initiatedAt;
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
