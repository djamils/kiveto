<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalPreventiveCare;

final readonly class PreventiveCareRecordView
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $label,
        public \DateTimeImmutable $administeredOn,
        public ?string $batchNumber,
        public ?\DateTimeImmutable $nextDueOn,
        public string $authorName,
    ) {
    }
}
