<?php

declare(strict_types=1);

namespace App\Context\Patient\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_patient_clinic_status', columns: ['clinic_id', 'status'])]
#[ORM\Index(name: 'idx_patient_clinic_animal', columns: ['clinic_id', 'animal_link_id'])]
class PatientEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(type: 'string', length: 16, enumType: PatientStatus::class)]
    private PatientStatus $status;

    #[ORM\Column(name: 'display_label_kind', type: 'string', length: 16, enumType: DisplayLabelKind::class)]
    private DisplayLabelKind $displayLabelKind;

    #[ORM\Column(name: 'display_label_value', type: 'string', length: 255)]
    private string $displayLabelValue;

    #[ORM\Column(name: 'animal_link_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $animalLinkId = null;

    #[ORM\Column(name: 'observed_species', type: 'string', length: 64, nullable: true)]
    private ?string $observedSpecies = null;

    #[ORM\Column(name: 'observed_color', type: 'string', length: 64, nullable: true)]
    private ?string $observedColor = null;

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

    public function getStatus(): PatientStatus
    {
        return $this->status;
    }

    public function setStatus(PatientStatus $status): void
    {
        $this->status = $status;
    }

    public function getDisplayLabelKind(): DisplayLabelKind
    {
        return $this->displayLabelKind;
    }

    public function setDisplayLabelKind(DisplayLabelKind $displayLabelKind): void
    {
        $this->displayLabelKind = $displayLabelKind;
    }

    public function getDisplayLabelValue(): string
    {
        return $this->displayLabelValue;
    }

    public function setDisplayLabelValue(string $displayLabelValue): void
    {
        $this->displayLabelValue = $displayLabelValue;
    }

    public function getAnimalLinkId(): ?Uuid
    {
        return $this->animalLinkId;
    }

    public function setAnimalLinkId(?Uuid $animalLinkId): void
    {
        $this->animalLinkId = $animalLinkId;
    }

    public function getObservedSpecies(): ?string
    {
        return $this->observedSpecies;
    }

    public function setObservedSpecies(?string $observedSpecies): void
    {
        $this->observedSpecies = $observedSpecies;
    }

    public function getObservedColor(): ?string
    {
        return $this->observedColor;
    }

    public function setObservedColor(?string $observedColor): void
    {
        $this->observedColor = $observedColor;
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
