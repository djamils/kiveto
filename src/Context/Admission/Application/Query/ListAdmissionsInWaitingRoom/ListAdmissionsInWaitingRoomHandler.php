<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\ListAdmissionsInWaitingRoom;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAdmissionsInWaitingRoomHandler
{
    public function __construct(
        private AdmissionReadRepositoryInterface $admissionReadRepository,
    ) {
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    public function __invoke(ListAdmissionsInWaitingRoom $query): array
    {
        return $this->admissionReadRepository->findActiveInWaitingRoom($query->clinicId);
    }
}
