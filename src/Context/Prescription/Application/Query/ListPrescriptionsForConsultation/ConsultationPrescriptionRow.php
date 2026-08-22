<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation;

final readonly class ConsultationPrescriptionRow
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public int $lineCount,
    ) {
    }

    /**
     * Only a signed, uncancelled ordonnance is worth printing.
     */
    public function isPrintable(): bool
    {
        return null !== $this->number && 'CANCELLED' !== $this->status;
    }
}
