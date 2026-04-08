<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\ListClinics;

use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicsHandler
{
    public function __construct(
        private ClinicReadRepositoryInterface $clinicReadRepository,
    ) {
    }

    public function __invoke(ListClinics $query): ClinicCollection
    {
        return $this->clinicReadRepository->findAllFiltered(
            $query->status,
            $query->clinicGroupId,
            $query->search,
        );
    }
}
