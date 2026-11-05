<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListMedicalAlertsForAnimals;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The alerts by animal id, in the order the animal's file lists them; an
 * animal without alerts, unknown or of another clinic, is left out.
 */
#[AsMessageHandler]
final readonly class ListMedicalAlertsForAnimalsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, list<MedicalAlertView>>
     */
    public function __invoke(ListMedicalAlertsForAnimals $query): array
    {
        $animalIds = array_values(array_unique($query->animalIds));

        if ([] === $animalIds) {
            return [];
        }

        return $this->readRepository->listMedicalAlerts(ClinicId::fromString($query->clinicId), $animalIds);
    }
}
