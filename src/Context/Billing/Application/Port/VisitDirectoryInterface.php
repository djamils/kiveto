<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface VisitDirectoryInterface
{
    /** The visit (admission) of the clinic, or null when unknown. */
    public function visitOf(string $clinicId, string $admissionId): ?VisitSummary;
}
