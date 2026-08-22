<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PrescriptionIssued extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'prescription';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $prescriptionId,
        public string $clinicId,
        public string $consultationId,
        public string $number,
        public int $lineCount,
        public bool $carriesControlledSubstance,
        public string $validUntil,
        public string $issuedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->prescriptionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'prescriptionId'             => $this->prescriptionId,
            'clinicId'                   => $this->clinicId,
            'consultationId'             => $this->consultationId,
            'number'                     => $this->number,
            'lineCount'                  => $this->lineCount,
            'carriesControlledSubstance' => $this->carriesControlledSubstance,
            'validUntil'                 => $this->validUntil,
            'issuedAt'                   => $this->issuedAt,
        ];
    }
}
