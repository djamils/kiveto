<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

final readonly class PatientLinkedToAnimalIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'patient';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $patientId,
        public string $animalId,
        public string $clinicId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->patientId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'patientId' => $this->patientId,
            'animalId'  => $this->animalId,
            'clinicId'  => $this->clinicId,
        ];
    }
}
