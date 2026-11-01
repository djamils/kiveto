<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses\RenewalStatus;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;

/** The latest renewal request of each prescription, one read per page. */
final readonly class RenewalStatuses
{
    public function __construct(private PrescriptionRenewalRequestRepositoryInterface $renewals)
    {
    }

    /**
     * @param list<string> $prescriptionIds
     *
     * @return array<string, RenewalStatus> by prescription id
     */
    public function of(string $clinicId, array $prescriptionIds): array
    {
        $statuses = [];

        foreach ($this->renewals->latestByPrescriptions($clinicId, $prescriptionIds) as $prescriptionId => $request) {
            $statuses[$prescriptionId] = new RenewalStatus($request->status()->value, $request->id()->toString(), $request->resultingPrescriptionId());
        }

        return $statuses;
    }
}
