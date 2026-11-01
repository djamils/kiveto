<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\HasPatientForAnimal;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether an active patient of the clinic is linked to the animal (its
 * clinical record exists). Answers a bool.
 */
final readonly class HasPatientForAnimal implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
