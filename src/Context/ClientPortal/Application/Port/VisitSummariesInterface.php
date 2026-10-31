<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Start and title of the consultations behind shared reports (Consultation context). */
interface VisitSummariesInterface
{
    /**
     * One call per timeline page; unknown consultations are left out.
     *
     * @param list<string> $consultationIds
     *
     * @return array<string, VisitSummary> by consultation id
     */
    public function summariesOf(string $clinicId, array $consultationIds): array;
}
