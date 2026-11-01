<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\HasPatientForAnimal;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class HasPatientForAnimalHandler
{
    public function __construct(
        private PatientReadRepositoryInterface $patientReadRepository,
    ) {
    }

    public function __invoke(HasPatientForAnimal $query): bool
    {
        return $this->patientReadRepository->existsActiveForAnimal($query->clinicId, $query->animalId);
    }
}
