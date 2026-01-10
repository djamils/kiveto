<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\ListClinics;

use App\Clinic\Application\Port\ClinicReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicsHandler
{
    public function __construct(
        private ClinicReadRepositoryInterface $clinicReadRepository,
    ) {
    }

    public function __invoke(ListClinics $query): ClinicsCollection
    {
        return $this->clinicReadRepository->findAllFiltered(
            $query->status,
            $query->clinicGroupId,
            $query->search,
        );
    }
}
