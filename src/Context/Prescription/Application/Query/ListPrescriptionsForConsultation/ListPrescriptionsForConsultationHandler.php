<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPrescriptionsForConsultationHandler
{
    public function __construct(private PrescriptionReadRepositoryInterface $prescriptions)
    {
    }

    /**
     * @return list<ConsultationPrescriptionRow>
     */
    public function __invoke(ListPrescriptionsForConsultation $query): array
    {
        return $this->prescriptions->forConsultation(
            ClinicId::fromString($query->clinicId),
            ConsultationId::fromString($query->consultationId),
        );
    }
}
