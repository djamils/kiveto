<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Command\CreateUnidentifiedPatient;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateUnidentifiedPatient implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $provisionalLabel,
        public ?string $observedSpecies,
        public ?string $observedColor,
    ) {
    }
}
