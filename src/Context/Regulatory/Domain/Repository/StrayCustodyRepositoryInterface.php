<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Repository;

use App\Context\Regulatory\Domain\StrayCustody;

interface StrayCustodyRepositoryInterface
{
    public function save(StrayCustody $custody): void;

    public function findByAdmissionId(string $admissionId): ?StrayCustody;

    public function findActiveByPatientId(string $patientId): ?StrayCustody;
}
