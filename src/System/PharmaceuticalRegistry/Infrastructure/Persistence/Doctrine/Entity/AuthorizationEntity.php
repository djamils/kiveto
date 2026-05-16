<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_pharma_authz_status', columns: ['status'])]
#[ORM\Index(name: 'idx_pharma_authz_atc_vet', columns: ['atc_vet_code'])]
#[ORM\Index(name: 'idx_pharma_authz_perm_id', columns: ['permanent_identifier'])]
#[ORM\Index(name: 'idx_pharma_authz_last_imported', columns: ['last_imported_at'])]
class AuthorizationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'commercial_name', type: 'string', length: 255)]
    private string $commercialName;

    #[ORM\Column(name: 'holder_laboratory', type: 'string', length: 255)]
    private string $holderLaboratory;

    #[ORM\Column(name: 'status', type: 'string', length: 48)]
    private string $status;

    #[ORM\Column(name: 'authorization_date', type: 'date_immutable')]
    private \DateTimeImmutable $authorizationDate;

    #[ORM\Column(name: 'nature', type: 'string', length: 32)]
    private string $nature;

    #[ORM\Column(name: 'pharmaceutical_form', type: 'string', length: 128)]
    private string $pharmaceuticalForm;

    #[ORM\Column(name: 'atc_vet_code', type: 'string', length: 16, nullable: true)]
    private ?string $atcVetCode;

    #[ORM\Column(name: 'permanent_identifier', type: 'string', length: 12, nullable: true)]
    private ?string $permanentIdentifier;

    #[ORM\Column(name: 'controlled_substance_class', type: 'string', length: 32, nullable: true)]
    private ?string $controlledSubstanceClass;

    #[ORM\Column(name: 'controlled_substance_jurisdiction', type: 'string', length: 3, nullable: true)]
    private ?string $controlledSubstanceJurisdiction;

    #[ORM\Column(name: 'controlled_substance_code', type: 'string', length: 32, nullable: true)]
    private ?string $controlledSubstanceCode;

    #[ORM\Column(name: 'controlled_substance_restrictions', type: 'text', nullable: true)]
    private ?string $controlledSubstanceRestrictions;

    #[ORM\Column(name: 'content_hash', type: 'string', length: 64, options: ['fixed' => true])]
    private string $contentHash = '';

    #[ORM\Column(name: 'last_import_source', type: 'string', length: 16)]
    private string $lastImportSource;

    #[ORM\Column(name: 'last_imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastImportedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, JurisdictionEntity> */
    #[ORM\OneToMany(
        targetEntity: JurisdictionEntity::class,
        mappedBy: 'authorization',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $jurisdictions;

    /** @var Collection<int, PresentationEntity> */
    #[ORM\OneToMany(
        targetEntity: PresentationEntity::class,
        mappedBy: 'authorization',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $presentations;

    /** @var Collection<int, CompositionEntity> */
    #[ORM\OneToMany(
        targetEntity: CompositionEntity::class,
        mappedBy: 'authorization',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $compositions;

    /** @var Collection<int, TargetUsageEntity> */
    #[ORM\OneToMany(
        targetEntity: TargetUsageEntity::class,
        mappedBy: 'authorization',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $targetUsages;

    #[ORM\OneToOne(
        targetEntity: SummaryEntity::class,
        mappedBy: 'authorization',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private ?SummaryEntity $summary;

    public function __construct()
    {
        $this->jurisdictions = new ArrayCollection();
        $this->presentations = new ArrayCollection();
        $this->compositions  = new ArrayCollection();
        $this->targetUsages  = new ArrayCollection();
        $this->summary       = null;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getCommercialName(): string
    {
        return $this->commercialName;
    }

    public function setCommercialName(string $commercialName): void
    {
        $this->commercialName = $commercialName;
    }

    public function getHolderLaboratory(): string
    {
        return $this->holderLaboratory;
    }

    public function setHolderLaboratory(string $holderLaboratory): void
    {
        $this->holderLaboratory = $holderLaboratory;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAuthorizationDate(): \DateTimeImmutable
    {
        return $this->authorizationDate;
    }

    public function setAuthorizationDate(\DateTimeImmutable $authorizationDate): void
    {
        $this->authorizationDate = $authorizationDate;
    }

    public function getNature(): string
    {
        return $this->nature;
    }

    public function setNature(string $nature): void
    {
        $this->nature = $nature;
    }

    public function getPharmaceuticalForm(): string
    {
        return $this->pharmaceuticalForm;
    }

    public function setPharmaceuticalForm(string $pharmaceuticalForm): void
    {
        $this->pharmaceuticalForm = $pharmaceuticalForm;
    }

    public function getAtcVetCode(): ?string
    {
        return $this->atcVetCode;
    }

    public function setAtcVetCode(?string $atcVetCode): void
    {
        $this->atcVetCode = $atcVetCode;
    }

    public function getPermanentIdentifier(): ?string
    {
        return $this->permanentIdentifier;
    }

    public function setPermanentIdentifier(?string $permanentIdentifier): void
    {
        $this->permanentIdentifier = $permanentIdentifier;
    }

    public function getControlledSubstanceClass(): ?string
    {
        return $this->controlledSubstanceClass;
    }

    public function setControlledSubstanceClass(?string $controlledSubstanceClass): void
    {
        $this->controlledSubstanceClass = $controlledSubstanceClass;
    }

    public function getControlledSubstanceJurisdiction(): ?string
    {
        return $this->controlledSubstanceJurisdiction;
    }

    public function setControlledSubstanceJurisdiction(?string $controlledSubstanceJurisdiction): void
    {
        $this->controlledSubstanceJurisdiction = $controlledSubstanceJurisdiction;
    }

    public function getControlledSubstanceCode(): ?string
    {
        return $this->controlledSubstanceCode;
    }

    public function setControlledSubstanceCode(?string $controlledSubstanceCode): void
    {
        $this->controlledSubstanceCode = $controlledSubstanceCode;
    }

    public function getControlledSubstanceRestrictions(): ?string
    {
        return $this->controlledSubstanceRestrictions;
    }

    public function setControlledSubstanceRestrictions(?string $controlledSubstanceRestrictions): void
    {
        $this->controlledSubstanceRestrictions = $controlledSubstanceRestrictions;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): void
    {
        $this->contentHash = $contentHash;
    }

    public function getLastImportSource(): string
    {
        return $this->lastImportSource;
    }

    public function setLastImportSource(string $lastImportSource): void
    {
        $this->lastImportSource = $lastImportSource;
    }

    public function getLastImportedAt(): \DateTimeImmutable
    {
        return $this->lastImportedAt;
    }

    public function setLastImportedAt(\DateTimeImmutable $lastImportedAt): void
    {
        $this->lastImportedAt = $lastImportedAt;
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

    /** @return Collection<int, JurisdictionEntity> */
    public function getJurisdictions(): Collection
    {
        return $this->jurisdictions;
    }

    public function addJurisdiction(JurisdictionEntity $jurisdiction): void
    {
        $this->jurisdictions->add($jurisdiction);
    }

    public function clearJurisdictions(): void
    {
        $this->jurisdictions->clear();
    }

    /** @return Collection<int, PresentationEntity> */
    public function getPresentations(): Collection
    {
        return $this->presentations;
    }

    public function addPresentation(PresentationEntity $presentation): void
    {
        $this->presentations->add($presentation);
    }

    public function clearPresentations(): void
    {
        $this->presentations->clear();
    }

    /** @return Collection<int, CompositionEntity> */
    public function getCompositions(): Collection
    {
        return $this->compositions;
    }

    public function addComposition(CompositionEntity $composition): void
    {
        $this->compositions->add($composition);
    }

    public function clearCompositions(): void
    {
        $this->compositions->clear();
    }

    /** @return Collection<int, TargetUsageEntity> */
    public function getTargetUsages(): Collection
    {
        return $this->targetUsages;
    }

    public function addTargetUsage(TargetUsageEntity $targetUsage): void
    {
        $this->targetUsages->add($targetUsage);
    }

    public function clearTargetUsages(): void
    {
        $this->targetUsages->clear();
    }

    public function getSummary(): ?SummaryEntity
    {
        return $this->summary;
    }

    public function setSummary(?SummaryEntity $summary): void
    {
        $this->summary = $summary;
    }
}
