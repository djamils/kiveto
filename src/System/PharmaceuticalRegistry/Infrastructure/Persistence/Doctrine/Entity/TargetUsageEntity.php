<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_pharma_target_usage_species', columns: ['target_species_code'])]
class TargetUsageEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AuthorizationEntity::class, inversedBy: 'targetUsages')]
    #[ORM\JoinColumn(name: 'authorization_id', nullable: false, onDelete: 'CASCADE')]
    private AuthorizationEntity $authorization;

    #[ORM\Column(name: 'administration_route', type: 'string', length: 32)]
    private string $administrationRoute;

    #[ORM\Column(name: 'target_species_code', type: 'string', length: 34)]
    private string $targetSpeciesCode;

    #[ORM\Column(name: 'food_production_purpose', type: 'string', length: 32, nullable: true)]
    private ?string $foodProductionPurpose;

    #[ORM\Column(name: 'withdrawal_period_value', type: 'integer', nullable: true)]
    private ?int $withdrawalPeriodValue;

    #[ORM\Column(name: 'withdrawal_period_unit', type: 'string', length: 8, nullable: true)]
    private ?string $withdrawalPeriodUnit;

    #[ORM\Column(name: 'jurisdictional_note', type: 'text', nullable: true)]
    private ?string $jurisdictionalNote;

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

    public function getAdministrationRoute(): string
    {
        return $this->administrationRoute;
    }

    public function setAdministrationRoute(string $administrationRoute): void
    {
        $this->administrationRoute = $administrationRoute;
    }

    public function getTargetSpeciesCode(): string
    {
        return $this->targetSpeciesCode;
    }

    public function setTargetSpeciesCode(string $targetSpeciesCode): void
    {
        $this->targetSpeciesCode = $targetSpeciesCode;
    }

    public function getFoodProductionPurpose(): ?string
    {
        return $this->foodProductionPurpose;
    }

    public function setFoodProductionPurpose(?string $foodProductionPurpose): void
    {
        $this->foodProductionPurpose = $foodProductionPurpose;
    }

    public function getWithdrawalPeriodValue(): ?int
    {
        return $this->withdrawalPeriodValue;
    }

    public function setWithdrawalPeriodValue(?int $withdrawalPeriodValue): void
    {
        $this->withdrawalPeriodValue = $withdrawalPeriodValue;
    }

    public function getWithdrawalPeriodUnit(): ?string
    {
        return $this->withdrawalPeriodUnit;
    }

    public function setWithdrawalPeriodUnit(?string $withdrawalPeriodUnit): void
    {
        $this->withdrawalPeriodUnit = $withdrawalPeriodUnit;
    }

    public function getJurisdictionalNote(): ?string
    {
        return $this->jurisdictionalNote;
    }

    public function setJurisdictionalNote(?string $jurisdictionalNote): void
    {
        $this->jurisdictionalNote = $jurisdictionalNote;
    }
}
