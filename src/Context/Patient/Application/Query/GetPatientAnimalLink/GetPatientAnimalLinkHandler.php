<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\GetPatientAnimalLink;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPatientAnimalLinkHandler
{
    public function __construct(
        private PatientReadRepositoryInterface $patientReadRepository,
    ) {
    }

    public function __invoke(GetPatientAnimalLink $query): ?PatientAnimalLinkDto
    {
        return $this->patientReadRepository->findAnimalLink($query->clinicId, $query->patientId);
    }
}
