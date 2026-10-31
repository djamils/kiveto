<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Shared\Application\Document\DocumentClinic;

/** Everything a vaccination booklet prints: nothing else may change the PDF. */
final readonly class VaccinationBooklet
{
    /**
     * @param list<VaccinationRecord> $vaccinations latest first; empty prints « Aucune vaccination enregistrée »
     */
    public function __construct(
        public ?DocumentClinic $clinic,
        public PetIdentity $pet,
        public array $vaccinations,
    ) {
    }
}
