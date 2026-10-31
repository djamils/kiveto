<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** A vaccination entered on the animal file. */
final readonly class VaccinationRecord
{
    /**
     * @param \DateTimeImmutable  $administeredOn a date, no time of day
     * @param ?\DateTimeImmutable $nextDueOn      a date, no time of day
     */
    public function __construct(
        public string $id,
        public string $label,
        public \DateTimeImmutable $administeredOn,
        public ?string $batchNumber,
        public ?\DateTimeImmutable $nextDueOn,
    ) {
    }
}
