<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Repository;

use App\Context\Patient\Domain\Exception\PatientNotFoundException;
use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\PatientId;

interface PatientRepositoryInterface
{
    public function save(Patient $patient): void;

    /**
     * @throws PatientNotFoundException
     */
    public function get(ClinicId $clinicId, PatientId $patientId): Patient;

    public function findById(ClinicId $clinicId, PatientId $patientId): ?Patient;

    /** @return list<Patient> */
    public function findActiveByAnimalId(ClinicId $clinicId, string $animalId): array;
}
