<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The animal block of the consultation report, already worded for paper.
 */
final readonly class ReportPatient
{
    public function __construct(
        public string $name,
        /** "Chien", "Chat", "NAC", "Autre", or the species observed on arrival. */
        public ?string $speciesLabel,
        /** "Croisé" for a mixed breed. */
        public ?string $breed = null,
        /** "♂" or "♀". */
        public ?string $sexLabel = null,
        public bool $sterilized = false,
        /** Y-m-d. */
        public ?string $birthDate = null,
        /** "3 ans", "5 mois", "12 jours". */
        public ?string $ageLabel = null,
        public ?string $microchip = null,
    ) {
    }
}
