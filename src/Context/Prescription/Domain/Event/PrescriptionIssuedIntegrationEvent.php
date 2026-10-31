<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * A prescription was signed. Thin payload: consumers (the Client Portal
 * publication registry) read whatever else they need through queries.
 */
final readonly class PrescriptionIssuedIntegrationEvent extends AbstractIntegrationEvent
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
