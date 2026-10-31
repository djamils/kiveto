<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalPrescription;

use App\Context\ClientPortal\Application\Port\PrescriptionContentLine;

final readonly class PortalPrescription
{
    /**
     * @param \DateTimeImmutable            $issuedAt in the clinic's time zone
     * @param list<PrescriptionContentLine> $lines
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $issuedAt,
        public string $vet,
        public ?string $petId,
        public string $petName,
        public array $lines,
    ) {
    }
}
