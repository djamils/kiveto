<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PrescriptionCancelled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'prescription';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $prescriptionId,
        public string $clinicId,
        public string $reason,
        public string $cancelledAt,
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
            'prescriptionId' => $this->prescriptionId,
            'clinicId'       => $this->clinicId,
            'reason'         => $this->reason,
            'cancelledAt'    => $this->cancelledAt,
        ];
    }
}
