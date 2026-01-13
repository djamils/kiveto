<?php

declare(strict_types=1);

namespace App\Shared\Application\Context;

use App\Clinic\Domain\ValueObject\ClinicId;

interface CurrentClinicContextInterface
{
    public function setCurrentClinicId(ClinicId $clinicId): void;

    public function getCurrentClinicId(): ?ClinicId;

    public function hasCurrentClinic(): bool;

    public function clearCurrentClinic(): void;
}
