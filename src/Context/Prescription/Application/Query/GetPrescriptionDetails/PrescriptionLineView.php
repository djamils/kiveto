<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\GetPrescriptionDetails;

final readonly class PrescriptionLineView
{
    /**
     * @param list<string> $activeSubstances
     */
    public function __construct(
        public string $id,
        public ?string $code,
        public string $label,
        public ?string $pharmaceuticalForm,
        public array $activeSubstances,
        public string $posology,
        public ?string $dose,
        public ?string $frequency,
        public ?int $durationDays,
        public ?string $route,
        public string $quantity,
        public string $prescriptionClass,
        public string $prescriptionClassLabel,
        public bool $isControlled,
        public ?string $instructions,
    ) {
    }
}
