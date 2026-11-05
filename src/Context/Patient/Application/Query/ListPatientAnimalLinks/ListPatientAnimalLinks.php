<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\ListPatientAnimalLinks;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Several patients' links at once, as GetPatientAnimalLink reads one: a page
 * naming many patients reads them in one query. Answers
 * `array<string, PatientAnimalLinkDto>` by patient id.
 */
final readonly class ListPatientAnimalLinks implements QueryInterface
{
    /**
     * @param list<string> $patientIds UUID strings; duplicates are read once
     */
    public function __construct(
        public string $clinicId,
        public array $patientIds,
    ) {
    }
}
