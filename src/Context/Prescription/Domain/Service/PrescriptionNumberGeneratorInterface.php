<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Service;

use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;

/**
 * Hands out the next prescription number of a clinic for a given year.
 *
 * Gap-free and never twice the same, which makes it a persistence concern
 * rather than something the aggregate can settle on its own.
 */
interface PrescriptionNumberGeneratorInterface
{
    public function next(ClinicId $clinicId, int $year): PrescriptionNumber;
}
