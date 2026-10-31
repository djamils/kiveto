<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The live state of a page of prescriptions shown on the Client Portal, read
 * in one go. Ids of another clinic, or unknown ones, are simply left out.
 */
final readonly class ListPrescriptionPortalSummaries implements QueryInterface
{
    /**
     * @param list<string> $prescriptionIds
     */
    public function __construct(
        public string $clinicId,
        public array $prescriptionIds,
    ) {
    }
}
