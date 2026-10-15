<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\TransferConsultationsToPatient;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Moves every consultation of a patient record onto the record it was merged
 * into — an emergency admitted unidentified, then recognised as an animal the
 * clinic already follows: its consultations belong to that animal's record.
 */
final readonly class TransferConsultationsToPatient implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $sourcePatientId,
        public string $targetPatientId,
    ) {
    }
}
