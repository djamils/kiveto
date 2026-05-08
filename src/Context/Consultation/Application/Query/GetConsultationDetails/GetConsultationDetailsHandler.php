<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
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
            ConsultationId::fromString($query->consultationId),
            ClinicId::fromString($query->clinicId),
        );
    }
}
