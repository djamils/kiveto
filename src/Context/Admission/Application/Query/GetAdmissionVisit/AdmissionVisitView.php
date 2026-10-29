<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\GetAdmissionVisit;

final readonly class AdmissionVisitView
{
    public function __construct(
        public string $admissionId,
        public string $patientId,
        /** UTC. */
        public \DateTimeImmutable $openedAt,
    ) {
    }
}
