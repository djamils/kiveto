<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Query\GetConsultationDetails;

use App\ClinicalCare\Application\Port\ConsultationReadRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetConsultationDetailsHandler
{
    public function __construct(
        private ConsultationReadRepositoryInterface $consultationReadRepository,
    ) {
    }

    public function __invoke(GetConsultationDetails $query): ConsultationDetailsDTO
    {
        return $this->consultationReadRepository->findById(
            ConsultationId::fromString($query->consultationId)
        );
    }
}
