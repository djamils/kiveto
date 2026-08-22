<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\GetPrescriptionDetails;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPrescriptionDetailsHandler
{
    public function __construct(private PrescriptionReadRepositoryInterface $prescriptions)
    {
    }

    public function __invoke(GetPrescriptionDetails $query): PrescriptionDetails
    {
        return $this->prescriptions->details(
            ClinicId::fromString($query->clinicId),
            PrescriptionId::fromString($query->prescriptionId),
        );
    }
}
