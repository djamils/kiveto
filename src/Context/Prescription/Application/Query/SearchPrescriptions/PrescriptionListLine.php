<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\SearchPrescriptions;

/**
 * One medicine of a listed ordonnance: what the register prints in its
 * « Prescription » column and how tightly it is controlled.
 */
final readonly class PrescriptionListLine
{
    public function __construct(
        public string $label,
        public string $prescriptionClass,
    ) {
    }
}
