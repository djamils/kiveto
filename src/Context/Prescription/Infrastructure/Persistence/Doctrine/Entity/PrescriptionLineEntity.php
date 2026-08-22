<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_prescription_line_prescription', columns: ['prescription_id', 'position'])]
class PrescriptionLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'prescription_id', type: UuidType::NAME)]
    private Uuid $prescriptionId;

    #[ORM\Column(name: 'article_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $articleId = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $label;

    #[ORM\Column(name: 'pharmaceutical_form', type: 'string', length: 128, nullable: true)]
    private ?string $pharmaceuticalForm = null;

    /** @var list<string> */
    #[ORM\Column(name: 'active_substances', type: 'json')]
    private array $activeSubstances = [];

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $dose = null;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $frequency = null;

    #[ORM\Column(name: 'duration_days', type: 'integer', nullable: true)]
    private ?int $durationDays = null;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $quantity;

    #[ORM\Column(name: 'prescription_class', type: 'string', length: 20, enumType: DrugPrescriptionClass::class)]
    private DrugPrescriptionClass $prescriptionClass;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions = null;

    #[ORM\Column(type: 'smallint')]
    private int $position = 0;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getPrescriptionId(): Uuid
    {
        return $this->prescriptionId;
    }

    public function setPrescriptionId(Uuid $prescriptionId): void
    {
        $this->prescriptionId = $prescriptionId;
    }

    public function getArticleId(): ?Uuid
    {
        return $this->articleId;
    }

    public function setArticleId(?Uuid $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getPharmaceuticalForm(): ?string
    {
        return $this->pharmaceuticalForm;
    }

    public function setPharmaceuticalForm(?string $pharmaceuticalForm): void
    {
        $this->pharmaceuticalForm = $pharmaceuticalForm;
    }

    /**
     * @return list<string>
     */
    public function getActiveSubstances(): array
    {
        return $this->activeSubstances;
    }

    /**
     * @param list<string> $activeSubstances
     */
    public function setActiveSubstances(array $activeSubstances): void
    {
        $this->activeSubstances = $activeSubstances;
    }

    public function getDose(): ?string
    {
        return $this->dose;
    }

    public function setDose(?string $dose): void
    {
        $this->dose = $dose;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(?string $frequency): void
    {
        $this->frequency = $frequency;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function setDurationDays(?int $durationDays): void
    {
        $this->durationDays = $durationDays;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): void
    {
        $this->route = $route;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrescriptionClass(): DrugPrescriptionClass
    {
        return $this->prescriptionClass;
    }

    public function setPrescriptionClass(DrugPrescriptionClass $prescriptionClass): void
    {
        $this->prescriptionClass = $prescriptionClass;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): void
    {
        $this->instructions = $instructions;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
