<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_medical_alert_animal', columns: ['animal_id'])]
#[ORM\Index(name: 'idx_medical_alert_kind', columns: ['kind'])]
class MedicalAlertEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AnimalEntity::class, inversedBy: 'medicalAlerts')]
    #[ORM\JoinColumn(name: 'animal_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AnimalEntity $animal = null;

    #[ORM\Column(type: 'string', length: 30, enumType: MedicalAlertKind::class)]
    private MedicalAlertKind $kind;

    #[ORM\Column(type: 'string', length: 120)]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getAnimal(): ?AnimalEntity
    {
        return $this->animal;
    }

    public function setAnimal(?AnimalEntity $animal): void
    {
        $this->animal = $animal;
    }

    public function getKind(): MedicalAlertKind
    {
        return $this->kind;
    }

    public function setKind(MedicalAlertKind $kind): void
    {
        $this->kind = $kind;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }
}
