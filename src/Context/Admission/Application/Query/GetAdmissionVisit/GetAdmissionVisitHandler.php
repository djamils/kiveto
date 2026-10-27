<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\GetAdmissionVisit;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAdmissionVisitHandler
{
    public function __construct(
        private AdmissionReadRepositoryInterface $admissionReadRepository,
    ) {
    }

    public function __invoke(GetAdmissionVisit $query): ?AdmissionVisitView
    {
        return $this->admissionReadRepository->findVisit($query->clinicId, $query->admissionId);
    }
}
