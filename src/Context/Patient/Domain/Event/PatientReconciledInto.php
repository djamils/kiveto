<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PatientReconciledInto extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'patient';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $sourcePatientId,
        public string $targetPatientId,
        public string $clinicId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->sourcePatientId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'sourcePatientId' => $this->sourcePatientId,
            'targetPatientId' => $this->targetPatientId,
            'clinicId'        => $this->clinicId,
        ];
    }
}
