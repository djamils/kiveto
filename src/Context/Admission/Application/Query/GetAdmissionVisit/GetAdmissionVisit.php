<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\GetAdmissionVisit;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One admission of the clinic as other contexts name the visit: its patient
 * and when it opened. Returns an AdmissionVisitView, or null when the
 * admission is unknown to the clinic.
 */
final readonly class GetAdmissionVisit implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
    ) {
    }
}
