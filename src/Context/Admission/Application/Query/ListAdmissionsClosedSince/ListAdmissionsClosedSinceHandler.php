<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\ListAdmissionsClosedSince;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAdmissionsClosedSinceHandler
{
    public function __construct(
        private AdmissionReadRepositoryInterface $admissionReadRepository,
    ) {
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    public function __invoke(ListAdmissionsClosedSince $query): array
    {
        return $this->admissionReadRepository->findClosedForClinicSince($query->clinicId, $query->since);
    }
}
