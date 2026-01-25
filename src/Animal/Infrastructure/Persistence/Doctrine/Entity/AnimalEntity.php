<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'animal__animal')]
#[ORM\Index(name: 'idx_animal_clinic', columns: ['clinic_id'])]
#[ORM\Index(name: 'idx_animal_status', columns: ['status'])]
#[ORM\Index(name: 'idx_animal_microchip', columns: ['microchip_number'])]
#[ORM\UniqueConstraint(name: 'uniq_animal_microchip_clinic', columns: ['clinic_id', 'microchip_number'])]
class AnimalEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    public string $id;

    #[ORM\Column(name: 'clinic_id', type: Types::STRING, length: 36)]
    public string $clinicId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $species;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $sex;

    #[ORM\Column(name: 'reproductive_status', type: Types::STRING, length: 50)]
    public string $reproductiveStatus;

    #[ORM\Column(name: 'is_mixed_breed', type: Types::BOOLEAN)]
    public bool $isMixedBreed;

    #[ORM\Column(name: 'breed_name', type: Types::STRING, length: 255, nullable: true)]
    public ?string $breedName = null;

    #[ORM\Column(name: 'birth_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $color = null;

    #[ORM\Column(name: 'photo_url', type: Types::STRING, length: 500, nullable: true)]
    public ?string $photoUrl = null;

    // Identification fields (inline)
    #[ORM\Column(name: 'microchip_number', type: Types::STRING, length: 50, nullable: true)]
    public ?string $microchipNumber = null;

    #[ORM\Column(name: 'tattoo_number', type: Types::STRING, length: 50, nullable: true)]
    public ?string $tattooNumber = null;

    #[ORM\Column(name: 'passport_number', type: Types::STRING, length: 50, nullable: true)]
    public ?string $passportNumber = null;

    #[ORM\Column(name: 'registry_type', type: Types::STRING, length: 50)]
    public string $registryType;

    #[ORM\Column(name: 'registry_number', type: Types::STRING, length: 100, nullable: true)]
    public ?string $registryNumber = null;

    #[ORM\Column(name: 'sire_number', type: Types::STRING, length: 50, nullable: true)]
    public ?string $sireNumber = null;

    // LifeCycle fields
    #[ORM\Column(name: 'life_status', type: Types::STRING, length: 50)]
    public string $lifeStatus;

    #[ORM\Column(name: 'deceased_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $deceasedAt = null;

    #[ORM\Column(name: 'missing_since', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $missingSince = null;

    // Transfer fields
    #[ORM\Column(name: 'transfer_status', type: Types::STRING, length: 50)]
    public string $transferStatus;

    #[ORM\Column(name: 'sold_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $soldAt = null;

    #[ORM\Column(name: 'given_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $givenAt = null;

    // AuxiliaryContact fields (inline)
    #[ORM\Column(name: 'auxiliary_contact_first_name', type: Types::STRING, length: 255, nullable: true)]
    public ?string $auxiliaryContactFirstName = null;

    #[ORM\Column(name: 'auxiliary_contact_last_name', type: Types::STRING, length: 255, nullable: true)]
    public ?string $auxiliaryContactLastName = null;

    #[ORM\Column(name: 'auxiliary_contact_phone_number', type: Types::STRING, length: 50, nullable: true)]
    public ?string $auxiliaryContactPhoneNumber = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $status;

    /**
     * @var Collection<int, OwnershipEntity>
     */
    #[ORM\OneToMany(targetEntity: OwnershipEntity::class, mappedBy: 'animal', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $ownerships;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->ownerships = new ArrayCollection();
    }
}
