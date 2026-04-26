<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Search\Index;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'animal_search_index')]
#[ORM\Index(name: 'idx_animal_srch_name', columns: ['clinic_id', 'search_name'])]
#[ORM\Index(name: 'idx_animal_srch_chip', columns: ['clinic_id', 'search_chip'])]
class AnimalSearchIndex
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(length: 255)]
    private string $animalName;

    #[ORM\Column(length: 255)]
    private string $searchName;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $searchChip;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $searchPhone;

    #[ORM\Column(length: 50)]
    private string $species;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $breedName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchOwnerName;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $primaryOwnerClientId;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $id,
        Uuid $clinicId,
        string $animalName,
        string $searchName,
        ?string $searchChip,
        ?string $searchPhone,
        string $species,
        ?string $breedName,
        ?string $searchOwnerName,
        ?Uuid $primaryOwnerClientId,
        string $status,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id                   = $id;
        $this->clinicId             = $clinicId;
        $this->animalName           = $animalName;
        $this->searchName           = $searchName;
        $this->searchChip           = $searchChip;
        $this->searchPhone          = $searchPhone;
        $this->species              = $species;
        $this->breedName            = $breedName;
        $this->searchOwnerName      = $searchOwnerName;
        $this->primaryOwnerClientId = $primaryOwnerClientId;
        $this->status               = $status;
        $this->updatedAt            = $updatedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function getAnimalName(): string
    {
        return $this->animalName;
    }

    public function getSearchName(): string
    {
        return $this->searchName;
    }

    public function getSearchChip(): ?string
    {
        return $this->searchChip;
    }

    public function getSearchPhone(): ?string
    {
        return $this->searchPhone;
    }

    public function getSpecies(): string
    {
        return $this->species;
    }

    public function getBreedName(): ?string
    {
        return $this->breedName;
    }

    public function getSearchOwnerName(): ?string
    {
        return $this->searchOwnerName;
    }

    public function getPrimaryOwnerClientId(): ?Uuid
    {
        return $this->primaryOwnerClientId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setAnimalName(string $animalName): void
    {
        $this->animalName = $animalName;
    }

    public function setSearchName(string $searchName): void
    {
        $this->searchName = $searchName;
    }

    public function setSearchChip(?string $searchChip): void
    {
        $this->searchChip = $searchChip;
    }

    public function setSearchPhone(?string $searchPhone): void
    {
        $this->searchPhone = $searchPhone;
    }

    public function setSearchOwnerName(?string $searchOwnerName): void
    {
        $this->searchOwnerName = $searchOwnerName;
    }

    public function setPrimaryOwnerClientId(?Uuid $primaryOwnerClientId): void
    {
        $this->primaryOwnerClientId = $primaryOwnerClientId;
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
