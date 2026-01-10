<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\ListClinicGroups;

use App\Clinic\Application\Port\ClinicGroupReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicGroupsHandler
{
    public function __construct(
        private ClinicGroupReadRepositoryInterface $clinicGroupReadRepository,
    ) {
    }

    public function __invoke(ListClinicGroups $query): ClinicGroupsCollection
    {
        return $this->clinicGroupReadRepository->findAllFiltered($query->status);
    }
}
