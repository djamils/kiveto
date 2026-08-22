<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListRow;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;

interface PrescriptionReadRepositoryInterface
{
    /**
     * @throws \DomainException when the prescription is unknown to this clinic
     */
    public function details(ClinicId $clinicId, PrescriptionId $prescriptionId): PrescriptionDetails;

    /**
     * One page of the clinic's prescriptions, plus the unpaginated total.
     *
     * @return array{items: list<PrescriptionListRow>, total: int}
     */
    public function search(ClinicId $clinicId, SearchPrescriptionsCriteria $criteria): array;
}
