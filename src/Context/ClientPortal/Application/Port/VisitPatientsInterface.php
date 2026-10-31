<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Visit → patient → animal hops (Admission and Patient contexts). */
interface VisitPatientsInterface
{
    public function patientOfAdmission(string $clinicId, string $admissionId): ?string;

    /** Null for an unknown or not yet reconciled patient. */
    public function animalOfPatient(string $clinicId, string $patientId): ?string;
}
