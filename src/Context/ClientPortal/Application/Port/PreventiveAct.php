<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The latest act of a preventive protocol (kind + label) of an animal. */
final readonly class PreventiveAct
{
    /**
     * @param \DateTimeImmutable  $administeredOn a date, no time of day
     * @param ?\DateTimeImmutable $nextDueOn      a date, no time of day
     */
    public function __construct(
        public string $animalId,
        public string $kind,
        public string $label,
        public \DateTimeImmutable $administeredOn,
        public ?\DateTimeImmutable $nextDueOn,
    ) {
    }
}
