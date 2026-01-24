<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'animal__animal')]
#[ORM\Index(columns: ['clinic_id'], name: 'idx_animal_clinic')]
#[ORM\Index(columns: ['status'], name: 'idx_animal_status')]
#[ORM\Index(columns: ['microchip_number'], name: 'idx_animal_microchip')]
#[ORM\UniqueConstraint(name: 'uniq_animal_microchip_clinic', columns: ['clinic_id', 'microchip_number'])]
class AnimalEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    public string $id;

    #[ORM\Column(type: Types::STRING, length: 36, name: 'clinic_id')]
    public string $clinicId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $species;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $sex;

    #[ORM\Column(type: Types::STRING, length: 50, name: 'reproductive_status')]
    public string $reproductiveStatus;

    #[ORM\Column(type: Types::BOOLEAN, name: 'is_mixed_breed')]
    public bool $isMixedBreed;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, name: 'breed_name')]
    public ?string $breedName = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true, name: 'birth_date')]
    public ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $color = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, name: 'photo_url')]
    public ?string $photoUrl = null;

    // Identification fields (inline)
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, name: 'microchip_number')]
    public ?string $microchipNumber = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, name: 'tattoo_number')]
    public ?string $tattooNumber = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, name: 'passport_number')]
    public ?string $passportNumber = null;

    #[ORM\Column(type: Types::STRING, length: 50, name: 'registry_type')]
    public string $registryType;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, name: 'registry_number')]
    public ?string $registryNumber = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, name: 'sire_number')]
    public ?string $sireNumber = null;

    // LifeCycle fields
    #[ORM\Column(type: Types::STRING, length: 50, name: 'life_status')]
    public string $lifeStatus;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'deceased_at')]
    public ?\DateTimeImmutable $deceasedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'missing_since')]
    public ?\DateTimeImmutable $missingSince = null;

    // Transfer fields
    #[ORM\Column(type: Types::STRING, length: 50, name: 'transfer_status')]
    public string $transferStatus;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'sold_at')]
    public ?\DateTimeImmutable $soldAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'given_at')]
    public ?\DateTimeImmutable $givenAt = null;

    // AuxiliaryContact fields (inline)
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, name: 'auxiliary_contact_first_name')]
    public ?string $auxiliaryContactFirstName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, name: 'auxiliary_contact_last_name')]
    public ?string $auxiliaryContactLastName = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, name: 'auxiliary_contact_phone_number')]
    public ?string $auxiliaryContactPhoneNumber = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $status;

    /**
     * @var Collection<int, OwnershipEntity>
     */
    #[ORM\OneToMany(targetEntity: OwnershipEntity::class, mappedBy: 'animal', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $ownerships;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'updated_at')]
    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->ownerships = new ArrayCollection();
    }
}
