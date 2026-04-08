<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\ListClinicGroups;

use App\Context\Clinic\Application\Port\ClinicGroupReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicGroupsHandler
{
    public function __construct(
        private ClinicGroupReadRepositoryInterface $clinicGroupReadRepository,
    ) {
    }

    public function __invoke(ListClinicGroups $query): ClinicGroupCollection
    {
        return $this->clinicGroupReadRepository->findAllFiltered($query->status);
    }
}
