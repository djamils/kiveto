<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Repository;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;

interface ConsultationRepositoryInterface
{
    public function save(Consultation $consultation): void;

    public function findById(ConsultationId $id): ?Consultation;
}
