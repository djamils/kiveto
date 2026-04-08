<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Domain\Repository;

use App\Context\ClinicalCare\Domain\Consultation;
use App\Context\ClinicalCare\Domain\ValueObject\ConsultationId;

interface ConsultationRepositoryInterface
{
    public function save(Consultation $consultation): void;

    public function findById(ConsultationId $id): ?Consultation;
}
