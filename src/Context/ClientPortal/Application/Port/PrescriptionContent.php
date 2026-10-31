<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class PrescriptionContent
{
    /**
     * @param list<PrescriptionContentLine> $lines
     */
    public function __construct(
        public string $prescriptionId,
        public \DateTimeImmutable $issuedAt,
        public string $prescriberName,
        public ?string $animalId,
        public string $patientName,
        public array $lines,
    ) {
    }
}
