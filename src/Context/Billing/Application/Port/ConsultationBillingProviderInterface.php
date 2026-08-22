<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface ConsultationBillingProviderInterface
{
    /**
     * Everything performed and prescribed during a visit, in the order it was
     * recorded, across all of its consultations.
     *
     * @return list<BillableLineDto>
     */
    public function billableLinesForAdmission(string $admissionId, string $clinicId): array;
}
