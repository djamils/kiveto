<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Repository;

use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;

interface AdmissionRepositoryInterface
{
    public function save(Admission $admission): void;

    /**
     * @throws AdmissionNotFoundException
     */
    public function get(ClinicId $clinicId, AdmissionId $admissionId): Admission;

    public function findById(ClinicId $clinicId, AdmissionId $admissionId): ?Admission;

    /** Whether the patient has an admission still open in the clinic. */
    public function hasActiveAdmissionForPatient(ClinicId $clinicId, string $patientId): bool;
}
