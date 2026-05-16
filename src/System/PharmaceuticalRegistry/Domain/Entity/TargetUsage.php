<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Entity;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\AdministrationRoute;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\FoodProductionPurpose;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\TargetSpeciesCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\WithdrawalPeriod;

final class TargetUsage
{
    private AdministrationRoute $administrationRoute;
    private TargetSpeciesCode $targetSpeciesCode;
    private ?FoodProductionPurpose $foodProductionPurpose;
    private ?WithdrawalPeriod $withdrawalPeriod;
    private ?string $jurisdictionalNote;

    private function __construct(
        AdministrationRoute $administrationRoute,
        TargetSpeciesCode $targetSpeciesCode,
        ?FoodProductionPurpose $foodProductionPurpose,
        ?WithdrawalPeriod $withdrawalPeriod,
        ?string $jurisdictionalNote,
    ) {
        $this->administrationRoute   = $administrationRoute;
        $this->targetSpeciesCode     = $targetSpeciesCode;
        $this->foodProductionPurpose = $foodProductionPurpose;
        $this->withdrawalPeriod      = $withdrawalPeriod;
        $this->jurisdictionalNote    = $jurisdictionalNote;
    }

    public static function of(
        AdministrationRoute $administrationRoute,
        TargetSpeciesCode $targetSpeciesCode,
        ?FoodProductionPurpose $foodProductionPurpose,
        ?WithdrawalPeriod $withdrawalPeriod,
        ?string $jurisdictionalNote,
    ): self {
        return new self(
            $administrationRoute,
            $targetSpeciesCode,
            $foodProductionPurpose,
            $withdrawalPeriod,
            $jurisdictionalNote,
        );
    }

    public function administrationRoute(): AdministrationRoute
    {
        return $this->administrationRoute;
    }

    public function targetSpeciesCode(): TargetSpeciesCode
    {
        return $this->targetSpeciesCode;
    }

    public function foodProductionPurpose(): ?FoodProductionPurpose
    {
        return $this->foodProductionPurpose;
    }

    public function withdrawalPeriod(): ?WithdrawalPeriod
    {
        return $this->withdrawalPeriod;
    }

    public function jurisdictionalNote(): ?string
    {
        return $this->jurisdictionalNote;
    }
}
