<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

final readonly class DrugDetailsDto
{
    /**
     * @param list<string> $activeSubstances
     */
    public function __construct(
        public ?string $pharmaceuticalForm = null,
        public array $activeSubstances = [],
        public string $prescriptionClass = 'NONE',
    ) {
    }

    /**
     * What is known about a free-text line, or one whose catalog entry has
     * vanished: nothing, and no reason to hold up the ordonnance for it.
     */
    public static function unknown(): self
    {
        return new self();
    }
}
