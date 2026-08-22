<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Repository;

use App\Context\Prescription\Domain\Exception\PrescriptionNotFoundException;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;

interface PrescriptionRepositoryInterface
{
    public function save(Prescription $prescription): void;

    /**
     * @throws PrescriptionNotFoundException
     */
    public function get(ClinicId $clinicId, PrescriptionId $prescriptionId): Prescription;

    public function findById(ClinicId $clinicId, PrescriptionId $prescriptionId): ?Prescription;

    /**
     * The prescription a consultation is writing, whatever its status.
     *
     * A consultation writes one ordonnance; finding the existing one is what
     * keeps a second visit to the cockpit from opening a second draft.
     */
    public function findForConsultation(ClinicId $clinicId, ConsultationId $consultationId): ?Prescription;
}
