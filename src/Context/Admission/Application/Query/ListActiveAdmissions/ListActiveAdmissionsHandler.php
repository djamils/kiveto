<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\ListActiveAdmissions;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListActiveAdmissionsHandler
{
    public function __construct(
        private AdmissionReadRepositoryInterface $admissionReadRepository,
    ) {
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    public function __invoke(ListActiveAdmissions $query): array
    {
        return $this->admissionReadRepository->findAllActiveForClinic($query->clinicId);
    }
}
