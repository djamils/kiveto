<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * Cross-BC integration event published when an admission is opened for an unidentified patient.
 * Consumed by the Patient BC and Regulatory BC to trigger reconciliation workflows.
 */
final readonly class AdmissionOpenedWithUnidentifiedPatient extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'admission';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $admissionId,
        public string $patientId,
        public string $clinicId,
        public string $openedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->admissionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'admissionId' => $this->admissionId,
            'patientId'   => $this->patientId,
            'clinicId'    => $this->clinicId,
            'openedAt'    => $this->openedAt,
        ];
    }
}
