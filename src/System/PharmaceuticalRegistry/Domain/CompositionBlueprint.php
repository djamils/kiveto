<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain;

final readonly class CompositionBlueprint
{
    public function __construct(
        public string $activeSubstanceLabel,
        public ?string $quantityValue,
        public ?string $quantityUnitLabel,
        public ?string $quantityUnitCode,
        public bool $isExcipient,
    ) {
    }
}
