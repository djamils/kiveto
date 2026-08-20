<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationsForPatient;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Past consultations of one patient, keyed through the animal when the patient
 * is reconciled so that pre-reconciliation records stay visible.
 */
final readonly class ListConsultationsForPatient implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $patientId,
        public ?string $animalId = null,
        public ?string $excludeConsultationId = null,
    ) {
    }
}
