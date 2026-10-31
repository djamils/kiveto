<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListLatestPreventiveCare;

final readonly class LatestPreventiveCareView
{
    public function __construct(
        public string $animalId,
        public string $kind,
        public string $label,
        public \DateTimeImmutable $administeredOn,
        public ?string $batchNumber,
        public ?\DateTimeImmutable $nextDueOn,
    ) {
    }
}
