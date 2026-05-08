<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Command\ReconcilePatientToAnimal;

use App\Context\Patient\Application\Service\PatientIdentityResolutionService;
use App\Context\Patient\Domain\ValueObject\PatientId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReconcilePatientToAnimalHandler
{
    public function __construct(
        private PatientIdentityResolutionService $service,
    ) {
    }

    public function __invoke(ReconcilePatientToAnimal $command): void
    {
        $this->service->linkOrReconcileToAnimal(
            PatientId::fromString($command->sourcePatientId),
            $command->clinicId,
            $command->animalId,
            $command->animalName,
        );
    }
}
