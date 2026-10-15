<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\RecordArrivalCheckIn;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Records what the desk noted when a booked patient arrived: its weight,
 * the four flags for the practitioner and a short note.
 */
final readonly class RecordArrivalCheckIn implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
        public bool $fasting = false,
        public bool $newSymptom = false,
        public bool $documentsBrought = false,
        public bool $ongoingTreatment = false,
        public ?int $weightGrams = null,
        public ?string $note = null,
    ) {
    }
}
