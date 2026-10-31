<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPrescriptionPortalSummariesHandler
{
    public function __construct(private PrescriptionReadRepositoryInterface $prescriptions)
    {
    }

    /**
     * @return list<PrescriptionPortalSummary>
     */
    public function __invoke(ListPrescriptionPortalSummaries $query): array
    {
        if ([] === $query->prescriptionIds) {
            return [];
        }

        return $this->prescriptions->portalSummaries(
            ClinicId::fromString($query->clinicId),
            $query->prescriptionIds,
        );
    }
}
