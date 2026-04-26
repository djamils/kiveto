<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PatientCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'patient';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $patientId,
        public string $clinicId,
        public string $displayLabelKind,
        public string $displayLabelValue,
        public ?string $animalId,
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
            'patientId'         => $this->patientId,
            'clinicId'          => $this->clinicId,
            'displayLabelKind'  => $this->displayLabelKind,
            'displayLabelValue' => $this->displayLabelValue,
            'animalId'          => $this->animalId,
        ];
    }
}
