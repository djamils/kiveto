<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\RecordAnimalWeight;

final readonly class RecordAnimalWeight
{
    /**
     * @param string $measuredOn Y-m-d, in clinic time
     */
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public int $weightGrams,
        public string $measuredOn,
        public ?int $bodyConditionScore,
        public string $authorName,
    ) {
    }
}
