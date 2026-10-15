<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Repository;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;

interface ConsultationRepositoryInterface
{
    public function save(Consultation $consultation): void;

    public function findById(ConsultationId $id): ?Consultation;

    /**
     * Re-points every consultation of the clinic held by `$source` to
     * `$target` (patient records merged); a no-op once already done.
     */
    public function transferToPatient(ClinicId $clinicId, PatientId $source, PatientId $target): void;
}
