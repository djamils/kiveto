<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\LifeStatus;
use App\Animal\Domain\ValueObject\RegistryType;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\TransferStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_animal_clinic', columns: ['clinic_id'])]
#[ORM\Index(name: 'idx_animal_status', columns: ['status'])]
#[ORM\Index(name: 'idx_animal_microchip', columns: ['microchip_number'])]
#[ORM\UniqueConstraint(name: 'uniq_animal_microchip_clinic', columns: ['clinic_id', 'microchip_number'])]
class AnimalEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 50, enumType: Species::class)]
    private Species $species;

    #[ORM\Column(type: 'string', length: 50, enumType: Sex::class)]
    private Sex $sex;

    #[ORM\Column(type: 'string', length: 50, enumType: ReproductiveStatus::class)]
    private ReproductiveStatus $reproductiveStatus;

    #[ORM\Column(type: 'boolean')]
    private bool $isMixedBreed;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $breedName = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $microchipNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $tattooNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $passportNumber = null;

    #[ORM\Column(type: 'string', length: 50, enumType: RegistryType::class)]
    private RegistryType $registryType;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $registryNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $sireNumber = null;

    #[ORM\Column(type: 'string', length: 50, enumType: LifeStatus::class)]
    private LifeStatus $lifeStatus;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deceasedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $missingSince = null;

    #[ORM\Column(type: 'string', length: 50, enumType: TransferStatus::class)]
    private TransferStatus $transferStatus;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $soldAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $givenAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $auxiliaryContactFirstName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $auxiliaryContactLastName = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $auxiliaryContactPhoneNumber = null;

    #[ORM\Column(type: 'string', length: 20, enumType: AnimalStatus::class)]
    private AnimalStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, OwnershipEntity> */
    #[ORM\OneToMany(
        targetEntity: OwnershipEntity::class,
        mappedBy: 'animal',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $ownerships;

    public function __construct()
    {
        $this->ownerships = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSpecies(): Species
    {
        return $this->species;
    }

    public function setSpecies(Species $species): void
    {
        $this->species = $species;
    }

    public function getSex(): Sex
    {
        return $this->sex;
    }

    public function setSex(Sex $sex): void
    {
        $this->sex = $sex;
    }

    public function getReproductiveStatus(): ReproductiveStatus
    {
        return $this->reproductiveStatus;
    }

    public function setReproductiveStatus(ReproductiveStatus $reproductiveStatus): void
    {
        $this->reproductiveStatus = $reproductiveStatus;
    }

    public function isMixedBreed(): bool
    {
        return $this->isMixedBreed;
    }

    public function setIsMixedBreed(bool $isMixedBreed): void
    {
        $this->isMixedBreed = $isMixedBreed;
    }

    public function getBreedName(): ?string
    {
        return $this->breedName;
    }

    public function setBreedName(?string $breedName): void
    {
        $this->breedName = $breedName;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): void
    {
        $this->birthDate = $birthDate;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): void
    {
        $this->photoUrl = $photoUrl;
    }

    public function getMicrochipNumber(): ?string
    {
        return $this->microchipNumber;
    }

    public function setMicrochipNumber(?string $microchipNumber): void
    {
        $this->microchipNumber = $microchipNumber;
    }

    public function getTattooNumber(): ?string
    {
        return $this->tattooNumber;
    }

    public function setTattooNumber(?string $tattooNumber): void
    {
        $this->tattooNumber = $tattooNumber;
    }

    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    public function setPassportNumber(?string $passportNumber): void
    {
        $this->passportNumber = $passportNumber;
    }

    public function getRegistryType(): RegistryType
    {
        return $this->registryType;
    }

    public function setRegistryType(RegistryType $registryType): void
    {
        $this->registryType = $registryType;
    }

    public function getRegistryNumber(): ?string
    {
        return $this->registryNumber;
    }

    public function setRegistryNumber(?string $registryNumber): void
    {
        $this->registryNumber = $registryNumber;
    }

    public function getSireNumber(): ?string
    {
        return $this->sireNumber;
    }

    public function setSireNumber(?string $sireNumber): void
    {
        $this->sireNumber = $sireNumber;
    }

    public function getLifeStatus(): LifeStatus
    {
        return $this->lifeStatus;
    }

    public function setLifeStatus(LifeStatus $lifeStatus): void
    {
        $this->lifeStatus = $lifeStatus;
    }

    public function getDeceasedAt(): ?\DateTimeImmutable
    {
        return $this->deceasedAt;
    }

    public function setDeceasedAt(?\DateTimeImmutable $deceasedAt): void
    {
        $this->deceasedAt = $deceasedAt;
    }

    public function getMissingSince(): ?\DateTimeImmutable
    {
        return $this->missingSince;
    }

    public function setMissingSince(?\DateTimeImmutable $missingSince): void
    {
        $this->missingSince = $missingSince;
    }

    public function getTransferStatus(): TransferStatus
    {
        return $this->transferStatus;
    }

    public function setTransferStatus(TransferStatus $transferStatus): void
    {
        $this->transferStatus = $transferStatus;
    }

    public function getSoldAt(): ?\DateTimeImmutable
    {
        return $this->soldAt;
    }

    public function setSoldAt(?\DateTimeImmutable $soldAt): void
    {
        $this->soldAt = $soldAt;
    }

    public function getGivenAt(): ?\DateTimeImmutable
    {
        return $this->givenAt;
    }

    public function setGivenAt(?\DateTimeImmutable $givenAt): void
    {
        $this->givenAt = $givenAt;
    }

    public function getAuxiliaryContactFirstName(): ?string
    {
        return $this->auxiliaryContactFirstName;
    }

    public function setAuxiliaryContactFirstName(?string $auxiliaryContactFirstName): void
    {
        $this->auxiliaryContactFirstName = $auxiliaryContactFirstName;
    }

    public function getAuxiliaryContactLastName(): ?string
    {
        return $this->auxiliaryContactLastName;
    }

    public function setAuxiliaryContactLastName(?string $auxiliaryContactLastName): void
    {
        $this->auxiliaryContactLastName = $auxiliaryContactLastName;
    }

    public function getAuxiliaryContactPhoneNumber(): ?string
    {
        return $this->auxiliaryContactPhoneNumber;
    }

    public function setAuxiliaryContactPhoneNumber(?string $auxiliaryContactPhoneNumber): void
    {
        $this->auxiliaryContactPhoneNumber = $auxiliaryContactPhoneNumber;
    }

    public function getStatus(): AnimalStatus
    {
        return $this->status;
    }

    public function setStatus(AnimalStatus $status): void
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

    /** @return Collection<int, OwnershipEntity> */
    public function getOwnerships(): Collection
    {
        return $this->ownerships;
    }

    public function addOwnership(OwnershipEntity $ownership): void
    {
        if (!$this->ownerships->contains($ownership)) {
            $this->ownerships->add($ownership);
            $ownership->setAnimal($this);
        }
    }

    public function removeOwnership(OwnershipEntity $ownership): void
    {
        if ($this->ownerships->removeElement($ownership)) {
            if ($ownership->getAnimal() === $this) {
                $ownership->setAnimal(null);
            }
        }
    }
}
