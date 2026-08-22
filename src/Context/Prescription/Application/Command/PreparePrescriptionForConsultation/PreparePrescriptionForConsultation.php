<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Opens — or reopens — the ordonnance of a consultation, returning its id.
 */
final readonly class PreparePrescriptionForConsultation implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
        public string $prescriberUserId,
    ) {
    }
}
