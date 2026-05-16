<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Domain\Exception\UnknownAnmvCodeException;

final class AnmvDictionaryCache
{
    /** @var array<int, string> */
    private array $activeSubstances = [];
    /** @var array<int, string> */
    private array $prescriptionConditions = [];
    /** @var array<int, string> */
    private array $targetSpecies = [];
    /** @var array<int, string> */
    private array $administrationRoutes = [];
    /** @var array<int, string> */
    private array $withdrawalUnits = [];

    /**
     * @param array<int, string> $activeSubstances
     * @param array<int, string> $prescriptionConditions
     * @param array<int, string> $targetSpecies
     * @param array<int, string> $administrationRoutes
     * @param array<int, string> $withdrawalUnits
     */
    public function __construct(
        array $activeSubstances,
        array $prescriptionConditions,
        array $targetSpecies,
        array $administrationRoutes,
        array $withdrawalUnits,
    ) {
        $this->activeSubstances       = $activeSubstances;
        $this->prescriptionConditions = $prescriptionConditions;
        $this->targetSpecies          = $targetSpecies;
        $this->administrationRoutes   = $administrationRoutes;
        $this->withdrawalUnits        = $withdrawalUnits;
    }

    public function getActiveSubstanceLabel(int $id): string
    {
        return $this->activeSubstances[$id] ?? throw new UnknownAnmvCodeException($id);
    }

    public function getPrescriptionConditionLabel(int $id): string
    {
        return $this->prescriptionConditions[$id] ?? throw new UnknownAnmvCodeException($id);
    }

    public function getTargetSpeciesLabel(int $id): string
    {
        return $this->targetSpecies[$id] ?? throw new UnknownAnmvCodeException($id);
    }

    public function getAdministrationRouteLabel(int $id): string
    {
        return $this->administrationRoutes[$id] ?? throw new UnknownAnmvCodeException($id);
    }

    public function getWithdrawalUnitLabel(int $id): string
    {
        return $this->withdrawalUnits[$id] ?? throw new UnknownAnmvCodeException($id);
    }
}
