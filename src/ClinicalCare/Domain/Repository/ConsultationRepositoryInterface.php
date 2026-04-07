<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\Repository;

use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;

interface ConsultationRepositoryInterface
{
    public function save(Consultation $consultation): void;

    public function findById(ConsultationId $id): ?Consultation;
}
