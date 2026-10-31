<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * A prescription was cancelled: consumers withdraw whatever they published
 * from it. Thin payload, like its issue counterpart.
 */
final readonly class PrescriptionCancelledIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'prescription';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $prescriptionId,
        public string $clinicId,
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
        ];
    }
}
