<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\RecordPreventiveCare;

final readonly class RecordPreventiveCare
{
    /**
     * @param string      $administeredOn Y-m-d, in clinic time
     * @param string|null $nextDueOn      Y-m-d, in clinic time
     */
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $kind,
        public string $label,
        public string $administeredOn,
        public ?string $batchNumber,
        public ?string $nextDueOn,
        public string $authorName,
    ) {
    }
}
