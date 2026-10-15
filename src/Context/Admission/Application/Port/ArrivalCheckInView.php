<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

/** What the desk noted when the patient arrived (see ArrivalCheckIn). */
final readonly class ArrivalCheckInView
{
    public function __construct(
        public bool $fasting,
        public bool $newSymptom,
        public bool $documentsBrought,
        public bool $ongoingTreatment,
        public ?int $weightGrams,
        public ?string $note,
    ) {
    }
}
