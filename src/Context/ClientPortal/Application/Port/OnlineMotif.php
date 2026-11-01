<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** A booking motif open online. */
final readonly class OnlineMotif
{
    /**
     * @param ?string $preventiveCareKind vaccination|internal_parasite|external_parasite|other
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $description,
        public int $durationMin,
        public ?string $preventiveCareKind,
    ) {
    }
}
