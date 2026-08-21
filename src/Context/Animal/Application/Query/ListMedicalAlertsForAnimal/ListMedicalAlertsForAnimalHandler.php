<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal;

use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\MedicalAlert;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListMedicalAlertsForAnimalHandler
{
    public function __construct(
        private AnimalRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<MedicalAlertView>
     */
    public function __invoke(ListMedicalAlertsForAnimal $query): array
    {
        $animal = $this->repository->findById(
            ClinicId::fromString($query->clinicId),
            AnimalId::fromString($query->animalId),
        );

        if (null === $animal) {
            return [];
        }

        return array_map(
            static fn (MedicalAlert $alert): MedicalAlertView => new MedicalAlertView(
                id: $alert->id,
                kind: $alert->kind->value,
                label: $alert->label,
                note: $alert->note,
            ),
            $animal->medicalAlerts(),
        );
    }
}
