<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\ListAllMemberships;

use App\ClinicAccess\Application\Port\MembershipAdminRepositoryInterface;

final readonly class ListAllMembershipsHandler
{
    public function __construct(
        private MembershipAdminRepositoryInterface $repository,
    ) {
    }

    public function __invoke(ListAllMemberships $query): MembershipCollection
    {
        return $this->repository->listAll(
            clinicId: $query->clinicId,
            userId: $query->userId,
            status: $query->status,
            role: $query->role,
            engagement: $query->engagement,
        );
    }
}
