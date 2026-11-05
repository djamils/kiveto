<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\ListPatientAnimalLinks;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPatientAnimalLinksHandler
{
    public function __construct(
        private PatientReadRepositoryInterface $patientReadRepository,
    ) {
    }

    /**
     * @return array<string, PatientAnimalLinkDto>
     */
    public function __invoke(ListPatientAnimalLinks $query): array
    {
        $patientIds = array_values(array_unique($query->patientIds));

        return [] === $patientIds ? [] : $this->patientReadRepository->findAnimalLinks($query->clinicId, $patientIds);
    }
}
