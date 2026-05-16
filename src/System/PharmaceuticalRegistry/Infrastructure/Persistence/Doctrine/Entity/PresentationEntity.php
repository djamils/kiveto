<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_pharma_presentation_gtin', columns: ['gtin'])]
class PresentationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AuthorizationEntity::class, inversedBy: 'presentations')]
    #[ORM\JoinColumn(name: 'authorization_id', nullable: false, onDelete: 'CASCADE')]
    private AuthorizationEntity $authorization;

    #[ORM\Column(name: 'description', type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(name: 'unit_count', type: 'string', length: 20, nullable: true)]
    private ?string $unitCount;

    #[ORM\Column(name: 'packaging', type: 'string', length: 255, nullable: true)]
    private ?string $packaging;

    #[ORM\Column(name: 'gtin', type: 'string', length: 14, nullable: true)]
    private ?string $gtin;

    #[ORM\Column(name: 'eu_pack_identifier', type: 'string', length: 36, nullable: true)]
    private ?string $euPackIdentifier;

    #[ORM\Column(name: 'prescription_required', type: 'boolean', options: ['default' => false])]
    private bool $prescriptionRequired = false;

    #[ORM\Column(name: 'prescription_class', type: 'string', length: 32)]
    private string $prescriptionClass;

    #[ORM\Column(name: 'prescription_retention_years', type: 'integer', nullable: true)]
    private ?int $prescriptionRetentionYears;

    #[ORM\Column(name: 'prescription_juris_jurisdiction', type: 'string', length: 3, nullable: true)]
    private ?string $prescriptionJurisJurisdiction;

    #[ORM\Column(name: 'prescription_juris_code', type: 'string', length: 64, nullable: true)]
    private ?string $prescriptionJurisCode;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getAuthorization(): AuthorizationEntity
    {
        return $this->authorization;
    }

    public function setAuthorization(AuthorizationEntity $authorization): void
    {
        $this->authorization = $authorization;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getUnitCount(): ?string
    {
        return $this->unitCount;
    }

    public function setUnitCount(?string $unitCount): void
    {
        $this->unitCount = $unitCount;
    }

    public function getPackaging(): ?string
    {
        return $this->packaging;
    }

    public function setPackaging(?string $packaging): void
    {
        $this->packaging = $packaging;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): void
    {
        $this->gtin = $gtin;
    }

    public function getEuPackIdentifier(): ?string
    {
        return $this->euPackIdentifier;
    }

    public function setEuPackIdentifier(?string $euPackIdentifier): void
    {
        $this->euPackIdentifier = $euPackIdentifier;
    }

    public function isPrescriptionRequired(): bool
    {
        return $this->prescriptionRequired;
    }

    public function setPrescriptionRequired(bool $prescriptionRequired): void
    {
        $this->prescriptionRequired = $prescriptionRequired;
    }

    public function getPrescriptionClass(): string
    {
        return $this->prescriptionClass;
    }

    public function setPrescriptionClass(string $prescriptionClass): void
    {
        $this->prescriptionClass = $prescriptionClass;
    }

    public function getPrescriptionRetentionYears(): ?int
    {
        return $this->prescriptionRetentionYears;
    }

    public function setPrescriptionRetentionYears(?int $prescriptionRetentionYears): void
    {
        $this->prescriptionRetentionYears = $prescriptionRetentionYears;
    }

    public function getPrescriptionJurisJurisdiction(): ?string
    {
        return $this->prescriptionJurisJurisdiction;
    }

    public function setPrescriptionJurisJurisdiction(?string $prescriptionJurisJurisdiction): void
    {
        $this->prescriptionJurisJurisdiction = $prescriptionJurisJurisdiction;
    }

    public function getPrescriptionJurisCode(): ?string
    {
        return $this->prescriptionJurisCode;
    }

    public function setPrescriptionJurisCode(?string $prescriptionJurisCode): void
    {
        $this->prescriptionJurisCode = $prescriptionJurisCode;
    }
}
