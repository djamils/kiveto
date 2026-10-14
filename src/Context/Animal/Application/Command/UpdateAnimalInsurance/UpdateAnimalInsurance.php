<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\UpdateAnimalInsurance;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Records the animal's health insurance; a null insurer means it has none.
 */
final readonly class UpdateAnimalInsurance implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public ?string $insurerName,
        public ?string $policyNumber,
    ) {
    }
}
